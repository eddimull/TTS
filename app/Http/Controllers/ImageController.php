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
        if (Storage::disk('s3')->exists($file)) {
            
            $storage = Storage::disk('s3')->get($file);
            $mimeType = Storage::mimeType($file);
        }
        else
        {
            $storage = Storage::disk('s3')->get('default.png');
            $mimeType = Storage::mimeType('default.png');
        }
        
        return (new Response($storage, 200))
        ->header('Content-Type', $mimeType);
    }
    public function siteImages($band_site,$uri)
    {
        
        if (Storage::disk('s3')->exists($band_site.'/'.$uri)) {
            $storage = Storage::disk('s3')->get($band_site.'/'.$uri);
            $mimeType = Storage::mimeType($band_site.'/'.$uri);
        }
        else
        {
            $storage = Storage::disk('s3')->get('default.png');
            $mimeType = Storage::mimeType('default.png');
        }
        // $contents = 
        
        // $handle = fopen($storage);

        
        return (new Response($storage, 200))
        ->header('Content-Type', $mimeType);
    }    

}
