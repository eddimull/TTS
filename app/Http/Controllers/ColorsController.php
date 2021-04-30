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
        $bands = Bands::select('bands.*')->join('band_owners','bands.id','=','band_owners.band_id')->where('user_id',Auth::id())->get();
        $colors = DB::select(DB::raw('SELECT B.name AS band_name, B.id AS band_id, CW.color_title, CW.color_tags,CP.photo_name FROM colorways CW 
        JOIN colorway_photos CP ON CP.colorway_id = CW.id
        JOIN band_owners BO ON BO.band_id = CW.band_id
        JOIN bands B ON B.id = BO.band_id
        WHERE user_id = ?'),[Auth::id()]);


        // foreach($colors as $color)
        // {

        // }
        return Inertia::render('Colors/Index',[
            'bands'=>$bands,
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
            $path = Storage::disk('s3')->put($band->site_name . '/' . urlencode($image->getClientOriginalName()),
                            file_get_contents($image),
                            ['visibility'=>'public']);
            $color_photo = ColorwayPhotos::create([
                'colorway_id'=>$colorway->id,
                'photo_name'=>$path
            ]);
        }
        return response()->json([
            'message' => 'Successfully added color',
        ]);
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
