<?php

namespace App\Observers;

use App\Models\QuestionnaireComponents;
use Illuminate\Support\Facades\Log;

class QuestionnaireComponentObserver
{
    /**
     * Handle the QuestionnaireComponents "created" event.
     *
     * @param  \App\Models\QuestionnaireComponents  $questionnaireComponents
     * @return void
     */
    public function created(QuestionnaireComponents $questionnaireComponents)
    {
        $this->sortEm($questionnaireComponents);
    }

    /**
     * Handle the QuestionnaireComponents "updated" event.
     *
     * @param  \App\Models\QuestionnaireComponents  $questionnaireComponents
     * @return void
     */
    public function updated(QuestionnaireComponents $questionnaireComponents)
    {
        $this->sortEm($questionnaireComponents);
    }

    private function sortEm($parentComponent)
    {
        $greaterThanComponents = QuestionnaireComponents::where('questionnaire_id',$parentComponent->questionnaire_id)
        ->where('order','>=',$parentComponent->order)
        ->where('id','!=',$parentComponent->id)
        ->orderByDesc('order')
        ->get();

        $count = $parentComponent->componentCount;
        foreach($greaterThanComponents as $component)
        {
            $component->order = $count;
            $component->save();
            $count--;
        }
    }
}
