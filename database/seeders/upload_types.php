<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UploadTypes;

class upload_types extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $upload_types = [
            [
                'name'=>'recording'
            ],
            [
                'name'=>'personal_recording'
            ],
            [
                'name'=>'sheet_music'
            ]
            ];

        UploadTypes::insert($upload_types);
        
    }
}
