<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\ChartUploads;

class ChartsServices
{
    public function uploadData($chart, $request)
    {
        $dataPath = $chart->band->site_name . '/charts/';

        foreach ($request->file('files') as $fileArray)
        {
            foreach ($fileArray as $file)
            {
                $uploadPath = $dataPath . Carbon::now()->timestamp . '_' . $file->getClientOriginalName();
                Storage::disk('s3')->put($uploadPath, fopen($file->getRealPath(), 'r+'));

                ChartUploads::create([
                    'chart_id' => $chart->id,
                    'upload_type_id' => $request->type_id,
                    'name' => $file->getClientOriginalName(),
                    'displayName' => $file->getClientOriginalName(),
                    'fileType' => $file->getMimeType(),
                    'url' => $uploadPath
                ]);
            }
        }
    }
}
