<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionnaireComponents extends Model
{
    use HasFactory;
    protected $table = "questionnaire_components";
    protected $fillable = ['questionnaire_id','data','order'];


    public function questionnaire()
    {
        return $this->belongsTo(Questionnairres::class);
    }

    public function getcomponentCountAttribute()
    {
        return count(QuestionnaireComponents::where('questionnaire_id',$this->questionnaire->id)->get());
    }

    public function setOrderAttribute($value)
    {
        $this->attributes['order'] = $this->getProperOrder($value);
    }

    private function getProperOrder($value)
    {
        $componentCount = count($this->questionnaire->components) + 1;
        
        
        //prevent the order from being out of bounds with the amount of components
        if($componentCount >= $value && $value !== -1)
        {
            $componentCount = $value;
        }
        
        return $componentCount;

    }
}
