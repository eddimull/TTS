<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($file)
    {
        if (Storage::disk('s3')->exists($file))
        {
            $storage = Storage::disk('s3')->get($file);
            $mimeType = Storage::disk('s3')->mimeType($file);
        }
        else
        {
            $storage = Storage::disk('s3')->get('default.png');
            $mimeType = Storage::disk('s3')->mimeType('default.png');
        }

        return (new Response($storage, 200))
            ->header('Content-Type', $mimeType);
    }

    public function siteImages($band_site, $path = null)
    {
        $path = $band_site . '/' . $path;

        if (Storage::disk('s3')->exists($path))
        {
            $storage = Storage::disk('s3')->get($path);
            $mimeType = Storage::disk('s3')->mimeType($path);
        }
        else
        {
            $storage = Storage::disk('s3')->get('default.png');
            $mimeType = Storage::disk('s3')->mimeType('default.png');
        }

        return (new Response($storage, 200))
            ->header('Content-Type', $mimeType);
    }
}
