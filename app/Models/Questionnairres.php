<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Questionnairres extends Model
{
    use HasFactory;
    protected $table = 'questionnairres';
    protected $fillable = [
        'name', 'slug', 'band_id', 'description'
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = Str::title($value);
        $this->attributes['slug'] = $this->createSlug($value);
    }

    private function createSlug($name)
    {
        $slug = Str::slug($name);
        if (static::whereSlug($slug)->exists()) {

            $max = static::whereName($name)->latest('id')->skip(1)->value('slug');

            if (isset($max[-1]) && is_numeric($max[-1])) {

                return preg_replace_callback('/(\d+)$/', function ($mathces) {

                    return $mathces[1] + 1;
                }, $max);
            }
            return "{$slug}-2";
        }
        return $slug;
    }

    public function components()
    {
        return $this->hasMany(QuestionnaireComponents::class,'questionnaire_id');
    }

}
