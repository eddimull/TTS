<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartUploads extends Model
{
    use \App\Models\Traits\BroadcastsBandChanges;

    protected $guarded = [];
    protected $table = 'chart_uploads';
    use HasFactory;
    protected $with = ['type'];
    public function type()
    {
        return $this->belongsTo(UploadTypes::class,'upload_type_id');
    }

    public function chart()
    {
        return $this->belongsTo(Charts::class, 'chart_id');
    }

    protected function broadcastBandId(): ?int
    {
        $bandId = $this->chart?->band_id;

        return $bandId ? (int) $bandId : null;
    }

    protected function broadcastParent(): ?array
    {
        return $this->chart_id
            ? ['model' => 'charts', 'id' => (int) $this->chart_id]
            : null;
    }
    
}
