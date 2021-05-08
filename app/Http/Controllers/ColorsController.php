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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $band = Bands::find($request->data['band_id']);
        $colorway = Colorways::create([
            'band_id' => $request->data['band_id'],
            'color_title' => $request->data['color_title'],
            'color_tags' => $request->data['color_tags'],
            'colorway_description' => $request->data['colorway_description']
        ]);
        
        // $images = $request->file('color_photos');
        foreach($request->files as $files)
        {
            foreach($files as $image)
            {
                $imagePath = $band->site_name . '/' . time() . str_replace($image[0]->getClientOriginalName(),' ','_');
                
                $path = Storage::disk('s3')->put($imagePath,
                file_get_contents($image[0]),
                ['visibility'=>'public']);
                $color_photo = ColorwayPhotos::create([
                    'colorway_id'=>$colorway->id,
                    'photo_name'=>$imagePath
                    ]);
            }
        }
        return redirect()->route('colors')->with('successMessage',$colorway->color_title . ' was successfully added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($request)
    {
        $this->band = Bands::find($request->band_id);
        $this->colorway = Colorways::create([
            'band_id' => $request->band_id,
            'color_title' => $request->color_title,
            'color_tags' => implode(',',$request->color_tags),
            'colorway_description' => $request->colorway_description
        ]);

        $images = $request->file('color_photos');

        $this->storeImages($images);
       
        return redirect()->route('colors')->with('successMessage',$request->color_title . 'was successfully edited');
    }

    private function storeImages($images)
    {
        foreach($images as $image)
        {
            $imagePath = $this->band->site_name . '/' . time() . str_replace($image->getClientOriginalName(),' ','_');
            
            $path = Storage::disk('s3')->put($imagePath,
                            file_get_contents($image),
                            ['visibility'=>'public']);
            $color_photo = ColorwayPhotos::create([
                'colorway_id'=>$this->colorway->id,
                'photo_name'=>$imagePath
            ]);
        }
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
        $this->colorway = Colorways::find($id);
        $this->band = Bands::find($this->colorway->band_id);

        $this->colorway->color_title = $request->data['color_title'];
        $this->colorway->color_tags = $request->data['color_tags'];
        $this->colorway->colorway_description = $request->data['colorway_description'];

        $this->colorway->save();

        // $images = $request->file('color_photos');
        // $this->storeImages($images);
        return redirect()->route('colors')->with('successMessage',$this->colorway->color_title . ' was successfully updated');
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
        // dd($id);

        $colorway = Colorways::find($id);
        $colorway->photos()->delete();
        $colorway->delete();

        return redirect()->route('colors')->with('successMessage','Colorway was successfully deleted');
    }
}
