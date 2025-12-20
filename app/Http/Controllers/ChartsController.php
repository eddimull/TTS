<?php

namespace App\Http\Controllers;

use App\Models\Charts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Models\ChartUploads;
use App\Services\ChartsServices;
use Illuminate\Support\Carbon;
use Auth;
use Illuminate\Support\Facades\Redirect;

class ChartsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $user->bands = $user->bands();
        $charts = $user->charts();
        return Inertia::render('Charts/Index', [
            'charts' => $charts,
            'availableBands' => $user->bands()
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $chart = Charts::create([
            'title' => $request->name,
            'composer' => $request->composer,
            'price' => $request->price ? $request->price : 0,
            'band_id' => $request->band_id
        ]);

        return redirect('/charts/' . $chart->id);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Charts  $chart
     * @return \Illuminate\Http\Response
     */
    public function show(Charts $chart)
    {
        $user = Auth::user();
        $canEdit = $user->canWrite('charts', $chart->band_id);
        
        return Inertia::render('Charts/Show', [
            'chart' => $chart,
            'canEdit' => $canEdit
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Charts  $charts
     * @return \Illuminate\Http\Response
     */
    public function edit(Charts $chart)
    {
        // Force fresh data by reloading the chart with uploads
        $chartData = $chart->fresh()->load('uploads.type');

        return Inertia::render('Charts/Edit', ['chart' => $chartData]);
    }

    public function uploadChartData(Charts $chart, Request $request)
    {
        // Validate the request with comprehensive rules
        $request->validate([
            'type_id' => 'required|integer|in:1,2,3', // 1=audio, 2=video, 3=sheet music
            'band_id' => 'required|integer|exists:bands,id',
            'files.*' => [
                'required',
                'file',
                'max:50240', // 50MB max per file
                function ($attribute, $value, $fail) use ($request) {
                    $typeId = $request->input('type_id');
                    $mimeType = $value->getMimeType();
                    
                    // Validate file types based on upload type
                    switch ($typeId) {
                        case 1: // Audio files
                            $allowedAudioTypes = ['audio/mpeg', 'audio/wav', 'audio/mp3', 'audio/mp4', 'audio/x-m4a'];
                            if (!in_array($mimeType, $allowedAudioTypes)) {
                                $fail('Audio files must be MP3, WAV, or M4A format.');
                            }
                            break;
                        
                        case 2: // Video files
                            $allowedVideoTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/quicktime'];
                            if (!in_array($mimeType, $allowedVideoTypes)) {
                                $fail('Video files must be MP4, AVI, MOV, or WMV format.');
                            }
                            break;
                        
                        case 3: // Sheet music/documents
                            $allowedDocTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                                              'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                            if (!in_array($mimeType, $allowedDocTypes)) {
                                $fail('Sheet music must be PDF, image files (JPG, PNG, GIF), or Word documents.');
                            }
                            break;
                    }
                    
                    // Check file size limits based on type
                    $maxSizes = [
                        1 => 20480, // 20MB for audio
                        2 => 50240, // 50MB for video
                        3 => 10240  // 10MB for documents
                    ];
                    
                    if ($value->getSize() > ($maxSizes[$typeId] * 1024)) {
                        $fail('File size exceeds the maximum allowed for this file type.');
                    }
                }
            ]
        ]);

        // Check if user has permission to upload to this chart's band
        $user = Auth::user();
        if (!$user->canWrite('charts', $chart->band_id)) {
            return Redirect::back()->with('errorMessage', 'You do not have permission to upload files to this chart.');
        }

        // Debug: Log what we received
        \Log::info('Upload request received', [
            'all_files' => array_keys($request->allFiles()),
            'request_data' => $request->except(['files']),
            'has_files' => !empty($request->allFiles())
        ]);

        // Check if any files were uploaded
        $hasFiles = false;
        foreach ($request->allFiles() as $key => $file) {
            if (strpos($key, 'files') === 0) {
                $hasFiles = true;
                break;
            }
        }

        if (!$hasFiles) {
            \Log::warning('No files found in request', [
                'all_files' => array_keys($request->allFiles()),
                'request_keys' => array_keys($request->all())
            ]);
            return Redirect::back()->with('errorMessage', 'No files were uploaded');
        }

        try {
            $chartService = new ChartsServices();
            $uploadCount = $chartService->uploadData($chart, $request);

            \Log::info('Chart upload successful', [
                'chart_id' => $chart->id,
                'uploads_created' => $uploadCount,
                'total_uploads' => $chart->fresh()->uploads()->count()
            ]);

            // Explicitly redirect to the edit page with fresh data
            return redirect()->route('charts.edit', $chart->id)->with('successMessage', 'Files uploaded successfully');
        } catch (\Exception $e) {
            \Log::error('Chart upload error: ' . $e->getMessage(), [
                'chart_id' => $chart->id,
                'user_id' => Auth::id(),
                'request_files' => array_keys($request->allFiles()),
                'request_data' => $request->except(['files']),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Redirect::back()->with('errorMessage', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function getResource(Charts $chart, ChartUploads $upload)
    {
        $user = Auth::user();

        if (!$user || !$user->canRead('charts', $chart->band_id)) {
            abort(403, 'You do not have permission to download this file');
        }

        try {
            if (!Storage::disk('s3')->exists($upload->url)) {
                abort(404, 'File not found');
            }

            $contents = Storage::disk('s3')->get($upload->url);
            
            // Use the stored fileType from database instead of trying to detect it
            $mimeType = $upload->fileType ?: 'application/octet-stream';
            
            // Set appropriate headers for download
            return response($contents, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $upload->displayName . '"')
                ->header('Cache-Control', 'no-cache, must-revalidate');
                
        } catch (\Exception $e) {
            \Log::error('File download error', [
                'upload_id' => $upload->id,
                'url' => $upload->url,
                'error' => $e->getMessage()
            ]);
            abort(500, 'Unable to download file');
        }
    }

    public function updateResource(Charts $chart, ChartUploads $upload, Request $request)
    {
        $upload->displayName = $request->displayName;
        $upload->notes = is_null($request->notes) ? '' : $request->notes;
        $upload->save();
        return Redirect::back()->with('successMessage', $upload->displayName . ' has been updated');
    }

    public function deleteResource(Charts $chart, ChartUploads $upload)
    {
        $upload->delete();
        return back()->with('successMessage', 'Removed ' . $upload->displayName);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Charts  $charts
     * @return \Illuminate\Http\Response
     */
    public function update(Charts $chart, Request $request)
    {
        // dd($request->public === true);
        $chart->title = $request->title;
        $chart->composer = $request->composer;
        $chart->description = $request->description;
        $chart->public = $request->public === true;
        $chart->save();

        return back()->with('successMessage', 'Updated ' . $chart->title);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Charts  $charts
     * @return \Illuminate\Http\Response
     */
    public function destroy(Charts $chart)
    {
        $chart->delete();
        return redirect('/charts/')->with('successMessage', $chart->title . ' has been deleted');
    }

    /**
     * Get charts for the authenticated user (API endpoint)
     *
     * @return \Illuminate\Http\Response
     */
    public function getChartsForUser()
    {
        $user = Auth::user();
        $charts = $user->charts();
        return response()->json($charts);
    }
}
