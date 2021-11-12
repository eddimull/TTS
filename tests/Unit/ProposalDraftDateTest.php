<?php

namespace Tests\Unit;

use App\Models\Bands;
use App\Models\ProposalPayments;
use App\Models\Proposals;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class ProposalDraftDate extends TestCase
{
    // use RefreshDatabase;


    public function testCanGetTheRightDate()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        $proposal = Proposals::factory()->create([
            'band_id'=>$band->id,
            'price'=>'10000.00',
            'paid'=>false,
            'author_id'=>$user->id
        ]);
        
        

        $this->assertEquals(Carbon::now()->format('Y-m-d'),$proposal->formattedDraftDate);
    }
}
