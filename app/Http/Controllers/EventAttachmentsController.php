<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\EventAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventAttachmentsController extends Controller
{
    /**
     * Upload attachments for an event
     */
    public function upload(Request $request, Events $event)
    {
        \Log::info('Upload request received', [
            'has_files' => $request->hasFile('files'),
            'all_keys' => array_keys($request->all()),
            'file_keys' => $request->file() ? array_keys($request->file()) : [],
        ]);

        $request->validate([
            'files.*' => 'required|file|max:10240', // 10MB max per file
        ]);

        $uploadedFiles = [];
        $files = $request->file('files');

        if (!$files) {
            return response()->json(['error' => 'No files uploaded'], 400);
        }

        foreach ($files as $file) {
            \Log::info('Processing file', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;

            // Store file - use s3-private for production storage
            $disk = config('filesystems.default') === 's3' ? 's3-private' : 'local';
            
            // Store the file directly from the uploaded file object
            $path = $file->storeAs('event-attachments', $filename, $disk);

            \Log::info('File stored', ['path' => $path, 'disk' => $disk]);

            // Create attachment record
            $attachment = EventAttachment::create([
                'event_id' => $event->id,
                'filename' => $file->getClientOriginalName(),
                'stored_filename' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'disk' => $disk,
            ]);

            $uploadedFiles[] = $attachment;
        }

        return response()->json([
            'message' => 'Files uploaded successfully',
            'attachments' => $uploadedFiles,
        ]);
    }

    /**
     * Serve/display an attachment inline (for images and PDFs)
     */
    public function show(EventAttachment $attachment)
    {
        try {
            $file = Storage::disk($attachment->disk)->get($attachment->stored_filename);
            
            return response($file)
                ->header('Content-Type', $attachment->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->filename . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Download an attachment
     */
    public function download(EventAttachment $attachment)
    {
        try {
            return Storage::disk($attachment->disk)->download(
                $attachment->stored_filename,
                $attachment->filename
            );
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * Delete an attachment
     */
    public function destroy(EventAttachment $attachment)
    {
        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully',
        ]);
    }

    /**
     * Get attachments for an event
     */
    public function index(Events $event)
    {
        return response()->json([
            'attachments' => $event->attachments,
        ]);
    }
}
