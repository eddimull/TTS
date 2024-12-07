<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ImageProcessingService
{
    /**
     * Process base64 image data and upload to S3
     *
     * @param string $base64Data
     * @param string $path
     * @return string|null
     */
    public function processAndUpload($base64Data, $path = 'uploads')
    {
        try
        {
            // Check if the string actually contains base64 image data
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type))
            {
                return null;
            }

            // Get file extension
            $extension = strtolower($type[1]);

            // Remove the mime type header
            $base64Data = preg_replace('/^data:image\/(\w+);base64,/', '', $base64Data);

            // Decode base64 data
            $fileData = base64_decode($base64Data);

            if (!$fileData)
            {
                return null;
            }

            // Generate unique filename
            $filename = Str::uuid() . '.' . $extension;
            $fullPath = $path . '/' . $filename;

            // Store file on S3
            Storage::disk('s3')->put($fullPath, $fileData);

            // Return the full URL to the uploaded file
            return Storage::disk('s3')->url($fullPath);
        }
        catch (\Exception $e)
        {
            \Log::error('Image processing failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process HTML content and replace base64 images with S3 URLs
     *
     * @param string $content
     * @param string $path
     * @return string
     */
    public function processContent($content, $path = 'uploads')
    {
        if (empty($content))
        {
            return $content;
        }

        // Regular expression to find base64 images in HTML content
        $pattern = '/<img[^>]*src=[\'"](data:image\/[^;]+;base64,[^\'\"]+)[\'"][^>]*>/i';

        return preg_replace_callback($pattern, function ($matches) use ($path)
        {
            $base64Data = $matches[1];
            $s3Url = $this->processAndUpload($base64Data, $path);

            if ($s3Url)
            {
                // Replace the base64 src with the S3 URL
                return str_replace($base64Data, $s3Url, $matches[0]);
            }

            return $matches[0];
        }, $content);
    }
}
