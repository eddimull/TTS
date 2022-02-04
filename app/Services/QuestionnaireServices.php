<?php 

namespace App\Services;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\QuestionnaireComponents;

class QuestionnaireServices{
    protected $questionnaire;
    public function __construct($questionnaire)
    {
        $this->questionnaire = $questionnaire;
    }
   public function addNewQuestion($name,$type,$order = -1)
   {

        $data = json_encode([
            'name'=>$name,
            'type'=>$type
        ]);
        return QuestionnaireComponents::create([
            'questionnaire_id'=>$this->questionnaire->id,
            'data'=>$data,
            'order'=>$order
        ]);
   }
}