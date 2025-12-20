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

        // Get band from event's booking relationship
        $band = $event->eventable_type === 'App\\Models\\Bookings' 
            ? $event->eventable->band 
            : $event->eventable->band;

        // Use consistent storage location pattern
        $storagePath = $band->site_name . '/event_uploads';
        $disk = config('filesystems.default');

        foreach ($files as $file) {
            \Log::info('Processing file', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'storage_path' => $storagePath,
            ]);

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Store the file directly from the uploaded file object
            $path = $file->storeAs($storagePath, $filename, $disk);

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
        $user = \Auth::user();
        $band_id = $attachment->event->eventable_type === 'App\\Models\\Bookings'
            ? $attachment->event->eventable->band_id
            : $attachment->event->eventable->band_id;

        if (!$user || !$user->canRead('events', $band_id)) {
            abort(403, 'You do not have permission to view this file');
        }

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
        $user = \Auth::user();
        $band_id = $attachment->event->eventable_type === 'App\\Models\\Bookings'
            ? $attachment->event->eventable->band_id
            : $attachment->event->eventable->band_id;

        if (!$user || !$user->canRead('events', $band_id)) {
            abort(403, 'You do not have permission to download this file');
        }

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

    /**
     * Convert an image URL from old rich-text format to an attachment
     */
    public function convertImageToAttachment(Request $request, Events $event)
    {
        $request->validate([
            'image_url' => 'required|string',
        ]);

        $imageUrl = $request->input('image_url');

        \Log::info('Converting image to attachment', [
            'image_url' => $imageUrl,
            'event_id' => $event->id,
        ]);

        // Extract the path from the URL (remove domain if present)
        $path = parse_url($imageUrl, PHP_URL_PATH);
        
        // If path starts with /images/, it's a public file
        if (!str_starts_with($path, '/images/')) {
            \Log::warning('Invalid image URL - must start with /images/', ['path' => $path]);
            return response()->json(['error' => 'Invalid image URL'], 400);
        }

        // Remove leading slash to get relative path from public directory
        $publicPath = ltrim($path, '/');

        \Log::info('Attempting to fetch image', [
            'path' => $path,
            'public_path' => $publicPath,
        ]);

        // Try to fetch from default disk (s3 or local public storage)
        $disk = config('filesystems.default');
        
        // For S3 storage, the /images/ prefix is just a URL convention
        // Actual storage path doesn't include "images/"
        $storagePath = $publicPath;
        if (str_starts_with($publicPath, 'images/')) {
            $storagePath = substr($publicPath, 7); // Remove "images/" prefix
        }
        
        \Log::info('Checking storage paths', [
            'public_path' => $publicPath,
            'storage_path' => $storagePath,
            'disk' => $disk,
        ]);
        
        // Check if file exists on the storage disk
        if (!Storage::disk($disk)->exists($storagePath)) {
            // Try local public path as fallback
            $fullPath = public_path($publicPath);
            
            \Log::info('File not on default disk, checking public path', [
                'disk' => $disk,
                'public_path' => $publicPath,
                'full_path' => $fullPath,
                'exists' => file_exists($fullPath),
            ]);
            
            if (!file_exists($fullPath)) {
                \Log::warning('Image file not found for conversion', [
                    'disk' => $disk,
                    'tried_path' => $publicPath,
                    'tried_public_path' => $fullPath,
                ]);
                return response()->json(['error' => 'File not found at: ' . $publicPath], 404);
            }

            // File found in local public directory
            $filename = basename($publicPath);
            $mimeType = mime_content_type($fullPath);
            $fileSize = filesize($fullPath);
            $fileContents = file_get_contents($fullPath);
        } else {
            // File found on storage disk (S3/MinIO)
            \Log::info('File found on storage disk', [
                'disk' => $disk,
                'path' => $storagePath,
            ]);

            try {
                $filename = basename($storagePath);
                $fileContents = Storage::disk($disk)->get($storagePath);
                $fileSize = Storage::disk($disk)->size($storagePath);
                $mimeType = Storage::disk($disk)->mimeType($storagePath);
            } catch (\Exception $e) {
                \Log::error('Failed to fetch file from storage', [
                    'disk' => $disk,
                    'path' => $publicPath,
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['error' => 'Failed to fetch file from storage'], 500);
            }
        }

        try {
            // Instead of duplicating the file, reference the existing storage location
            // The file is already in the correct band's event_uploads folder
            $attachmentDisk = config('filesystems.default');
            
            // Use the existing storage path (without images/ prefix for S3)
            $storedPath = $storagePath ?? $publicPath;

            \Log::info('Creating attachment record', [
                'stored_path' => $storedPath,
                'disk' => $attachmentDisk,
                'filename' => $filename,
            ]);

            // Create attachment record pointing to the existing file
            $attachment = EventAttachment::create([
                'event_id' => $event->id,
                'filename' => $filename,
                'stored_filename' => $storedPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'disk' => $attachmentDisk,
            ]);

            return response()->json([
                'message' => 'Image converted to attachment successfully',
                'attachment' => $attachment,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to convert image to attachment', [
                'error' => $e->getMessage(),
                'path' => $publicPath,
            ]);
            
            return response()->json(['error' => 'Failed to convert image'], 500);
        }
    }
}
