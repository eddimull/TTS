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

    private function questionDefaults($name, $type)
    {
        $default = [];
        switch($type)
        {
            case 'multichoice':
                $default = [
                    'title'=>$name,
                    'choices'=>[
                        'choice 1',
                        'choice 2',
                        'choice 3'
                    ]
                ];
                break;
            case 'openEnded':
                $default = [
                    'title'=>$name,
                    'input'=>'',
                    'singleLine'=>false
                ];
                break;
            case 'headerText':
                $default= [
                    'title'=>$name
                ];
                break;
        }

        return $default;
    }
   public function addNewQuestion($name,$type,$order = -1)
   {

        $data =[
            'name'=>$name,
            'type'=>$type
        ];

        $data = array_merge($data,$this->questionDefaults($name,$type));

        return QuestionnaireComponents::create([
            'questionnaire_id'=>$this->questionnaire->id,
            'data'=>json_encode($data),
            'order'=>$order
        ]);
   }
}