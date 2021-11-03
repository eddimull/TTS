<?php 

namespace App\Services;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\ChartUploads;

class ChartsServices{
    public function uploadData($chart,$request)
    {
        $dataPath = $chart->band->site_name . '/charts/' ;
        
        foreach($request->file('files') as $i=>$file)
        {
            $uploadPath = $dataPath. Carbon::now() . $file[$i]->getClientOriginalName();
            Storage::disk('s3')->put($uploadPath,fopen($file[$i]->getRealPath(), 'r+'));

            ChartUploads::create([
                'chart_id' => $chart->id,
                'upload_type_id'=>$request->type_id,
                'name'=>$file[$i]->getClientOriginalName(),
                'displayName'=>$file[$i]->getClientOriginalName(),
                'fileType'=>Storage::mimeType($uploadPath),
                'url'=>$uploadPath
            ]);
        }
    }
}