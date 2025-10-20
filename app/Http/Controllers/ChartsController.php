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
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Charts  $charts
     * @return \Illuminate\Http\Response
     */
    public function edit(Charts $chart)
    {
        $chartData = $chart;

        // dd($chartData);
        return Inertia::render('Charts/Edit', ['chart' => $chartData]);
    }

    public function uploadChartData(Charts $chart, Request $request)
    {
        // dd($request->type_id);

        $chartService = new ChartsServices();
        $chartService->uploadData($chart, $request);

        return Redirect::back()->with('successMessage', 'Files Uploaded');
    }

    public function getResource(Charts $chart, ChartUploads $upload)
    {
        $contents = Storage::disk('s3')->get($upload->url);
        $mimeType = Storage::mimeType($upload->url);
        return response($contents, 200)->header('Content-Type', $mimeType);
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
