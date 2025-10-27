<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\ChartUploads;

class ChartsServices
{
    public function uploadData($chart, $request)
    {
        // Validate required parameters
        if (!$chart || !$request->type_id) {
            throw new \InvalidArgumentException('Chart and type_id are required');
        }

        $dataPath = $chart->band->site_name . '/charts/';

        // Handle files array (files[])
        $files = [];
        if ($request->hasFile('files')) {
            $files = $request->file('files');
        } else {
            // Fallback: Handle indexed files (files[0], files[1], etc.)
            foreach ($request->allFiles() as $key => $file) {
                if (strpos($key, 'files') === 0) {
                    if (is_array($file)) {
                        $files = array_merge($files, $file);
                    } else {
                        $files[] = $file;
                    }
                }
            }
        }

        if (empty($files)) {
            throw new \InvalidArgumentException('No files provided for upload');
        }

        $uploadedCount = 0;
        
        foreach ($files as $file) {
            // Additional file validation
            if (!$file->isValid()) {
                throw new \RuntimeException('Invalid file: ' . $file->getClientOriginalName());
            }

            // Sanitize filename
            $originalName = $file->getClientOriginalName();
            $sanitizedName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $originalName);
            
            // Ensure unique filename using timestamp and random string
            $timestamp = Carbon::now()->timestamp;
            $randomString = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
            $uploadPath = $dataPath . $timestamp . '_' . $randomString . '_' . $sanitizedName;

            // Upload file to S3
            $fileContents = file_get_contents($file->getRealPath());
            try {
                if (!Storage::disk('s3')->put($uploadPath, $fileContents)) {
                    throw new \RuntimeException('Failed to upload file: ' . $originalName);
                }
            } catch (\Exception $e) {
                \Log::error('S3 upload failed', ['error' => $e->getMessage(), 'file' => $originalName]);
                throw new \RuntimeException('Failed to upload file to S3: ' . $originalName . '. Error: ' . $e->getMessage());
            }

            // Create database record
            try {
                ChartUploads::create([
                    'chart_id' => $chart->id,
                    'upload_type_id' => $request->type_id,
                    'name' => $sanitizedName,
                    'displayName' => $originalName,
                    'fileType' => $file->getMimeType(),
                    'url' => $uploadPath
                ]);
                
                $uploadedCount++;
            } catch (\Exception $e) {
                // If database creation fails, clean up the uploaded file
                Storage::disk('s3')->delete($uploadPath);
                throw new \RuntimeException('Failed to create database record for: ' . $originalName . '. Error: ' . $e->getMessage());
            }
        }

        if ($uploadedCount === 0) {
            throw new \RuntimeException('No files were successfully uploaded');
        }

        return $uploadedCount;
    }
}
