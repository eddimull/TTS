<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Questionnairres;
use App\Models\QuestionnaireComponents;
use App\Services\QuestionnaireServices;
use Illuminate\Foundation\Testing\RefreshDatabase;


class QuestionairreTest extends TestCase
{
    // use RefreshDatabase;
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_canCreateQuestionnaire()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);
        $this->assertGreaterThan(0,$quest->id);
    }

    public function test_checkUppercase()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);
        $this->assertEquals('This Is A Test',$quest->name);
    }

    public function test_checkSlug()
    {
        $this->markTestIncomplete(
            'This feature has not been implemented yet.'
          );
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);
        $this->assertEquals('this-is-a-test',$quest->slug);
    }

    public function test_checkDuplicateSlug()
    {
        $this->markTestIncomplete(
            'This feature has not been implemented yet.'
          );
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);
        $quest2 = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);
        $this->assertEquals('this-is-a-test-2',$quest2->slug);
    }

    public function test_canCreateComponent()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);

        $service->addNewQuestion('doot','header');

        $this->assertDatabaseHas('questionnaire_components',[
            'questionnaire_id'=>$quest->id
        ]);
    }

    public function test_componentsOrderedFineAuto()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);


        $service->addNewQuestion('doot','header');
        $component = $service->addNewQuestion('doot2','header2');
        $this->assertEquals($component->order,2);
    }

    public function test_componentsOrderedInBounds()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);

        
        $service->addNewQuestion('doot1','header1');
        $service->addNewQuestion('doot2','header2');
        $component = $service->addNewQuestion('NWO','header',999);

        $this->assertEquals($component->order,3);
    }

    public function test_componentsOrderedFineSpecified()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);

        
        $service->addNewQuestion('doot1','header1');
        $service->addNewQuestion('doot2','header2');
        $service->addNewQuestion('doot4','header4');
        $component5 = $service->addNewQuestion('doot5','header5');
        $component = $service->addNewQuestion('NWO','header',3);
        
        $this->assertEquals($component->order,3);


        $reQueried = QuestionnaireComponents::find($component5->id);
        $this->assertEquals($reQueried->order,5);
    }


    public function test_componentsOrderedFineViaUpdate()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);

        
        $secondComponent = $service->addNewQuestion('doot1','header1');
        $service->addNewQuestion('doot2','header2');
        $service->addNewQuestion('doot3','header3');
        $service->addNewQuestion('doot4','header4');
        $component = $service->addNewQuestion('doot5','header5');
        

        $component->order = 1;
        $component->save();

        $reQueried = QuestionnaireComponents::find($secondComponent->id);
        $this->assertEquals($reQueried->order,2);
    }


    public function test_getComponentCount()
    {
        $band = Bands::factory()->create();
        $quest = Questionnairres::create([
            'band_id'=>$band->id,
            'name'=>'this is a test'
        ]);

        $service = new QuestionnaireServices($quest);

        
        $service->addNewQuestion('doot1','header1');
        $service->addNewQuestion('doot2','header2');
        $component = $service->addNewQuestion('doot3','header3');

        $this->assertEquals($component->componentCount,3);

    }

    
}
