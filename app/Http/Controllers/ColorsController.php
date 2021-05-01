<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\Bands;
use App\Models\ColorwayPhotos;
use App\Models\Colorways;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ColorsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;
        
        // dd($bands);
        // $test = Bands::first();
        $colors = [];
        foreach($bands as $band)
        {
            // dd($band->colorways);
            // dd($band->colorways());
            $bandColor = $band->colorways;
            foreach($bandColor as $color)
            {
                // dd($color);
                $photos = $color->photos;
                $color->photos = $photos;
                array_push($colors,$color);
            }

        }

        return Inertia::render('Colors/Index',[
            'bands'=>$user->bandOwner,
            'colors'=>$colors
        ]);
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
        $band = Bands::find($request->band_id);
        $colorway = Colorways::create([
            'band_id' => $request->band_id,
            'color_title' => $request->color_title,
            'color_tags' => implode(',',$request->color_tags),
            'colorway_description' => $request->colorway_description
        ]);

        $images = $request->file('color_photos');
        foreach($images as $image)
        {
            $imagePath = $band->site_name . '/' . time() . str_replace($image->getClientOriginalName(),' ','_');
            
            $path = Storage::disk('s3')->put($imagePath,
                            file_get_contents($image),
                            ['visibility'=>'public']);
            $color_photo = ColorwayPhotos::create([
                'colorway_id'=>$colorway->id,
                'photo_name'=>$imagePath
            ]);
        }
        redirect()->route('Colors/Index')->with('successMessage','Color was successfully added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($request)
    {
        $band = Bands::find($request->band_id);
        $colorway = Colorways::create([
            'band_id' => $request->band_id,
            'color_title' => $request->color_title,
            'color_tags' => implode(',',$request->color_tags),
            'colorway_description' => $request->colorway_description
        ]);

        $images = $request->file('color_photos');
        foreach($images as $image)
        {
            $imagePath = $band->site_name . '/' . time() . str_replace($image->getClientOriginalName(),' ','_');
            
            $path = Storage::disk('s3')->put($imagePath,
                            file_get_contents($image),
                            ['visibility'=>'public']);
            $color_photo = ColorwayPhotos::create([
                'colorway_id'=>$colorway->id,
                'photo_name'=>$imagePath
            ]);
        }
        redirect()->route('Colors/Index')->with('successMessage',$request->color_title . 'was successfully edited');
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
