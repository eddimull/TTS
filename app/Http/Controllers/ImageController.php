<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($file)
    {
        dd($file);
        if (Storage::disk('s3')->exists($file)) {
            $storage = Storage::disk('s3')->url($file);
        }
        else
        {
            $storage = Storage::disk('s3')->url('strauss.jpg');
        }
        // $contents = 
        
        // $handle = fopen($storage);

        
        return redirect($storage);
    }
    public function siteImages($band_site,$uri)
    {
        if (Storage::disk('s3')->exists($band_site.'/'.$uri)) {
            $storage = Storage::disk('s3')->url($band_site.'/'.$uri);
        }
        else
        {
            $storage = Storage::disk('s3')->url('strauss.jpg');
        }
        // $contents = 
        
        // $handle = fopen($storage);

        
        return redirect($storage);
    }    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}