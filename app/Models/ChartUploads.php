<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartUploads extends Model
{
    protected $guarded = [];
    protected $table = 'chart_uploads';
    use HasFactory;
    protected $with = ['type'];
    public function type()
    {
        return $this->belongsTo(UploadTypes::class,'upload_type_id');
    }
    
}
