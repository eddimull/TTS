<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cache;

class ProposalPhases extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function proposal()
    {
        return $this->belongsTo(Proposals::class);
    }

    public static function all($columns = ['*'])
    {
        return Cache::rememberForever('all_proposal_phases', function () use ($columns) {
            return parent::all($columns);
        });
    }
}
