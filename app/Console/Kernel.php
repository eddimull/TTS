<?php

namespace App\Console;

use App\Mail\EventReminder;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Contracts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\AdvanceReminderService;
use App\Services\ProposalServices;
use Symfony\Component\ErrorHandler\Debug;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function(){
            
            $contracts = Contracts::where('status','!=','completed')->get();
            
            foreach($contracts as $contract)
            {
                $response = Http::withHeaders([
                    'Authorization'=>'API-Key ' . env('PANDADOC_KEY')
                ])
                ->acceptJson()
                ->get('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id);
                
                if($response['status'] == "document.completed")
                {
                    $proposal = $contract->proposal;
                    $proposal->phase_id = 6;
                    $proposal->save();
                    $contract->status = 'completed';

        
                    $opts = array(
                        'http'=>array(
                            'method'=>"GET",
                            'header'=>"Authorization: API-Key " . env('PANDADOC_KEY') 
                        )
                        );
                    $context = stream_context_create($opts);

                    $imagePath = $proposal->band->site_name . '/' . $proposal->name . '_signed_contract_' . time() . '.pdf';

                    $path = Storage::disk('s3')->put($imagePath,
                    file_get_contents('https://api.pandadoc.com/public/v1/documents/' . $contract->envelope_id . '/download',false,$context),
                    ['visibility'=>'public']);
                    $contract->image_url = Storage::disk('s3')->url($imagePath);

                    foreach($proposal->band->owners as $owner)
                    {
                        $user = User::find($owner->user_id);
                        $user->notify(new TTSNotification([
                            'text'=>'Contract for ' . $proposal->name . ' signed and completed!',
                            'route'=>'proposals',
                            'routeParams'=>'',
                            'url'=>'/proposals/'
                            ]));
                    }  
                    
                    $contract->save();

                    $proposalService = new ProposalServices($proposal);
                    $proposalService->writeToCalendar();
                }
            }
        })->everyMinute();


        $schedule->call(function(){
            $BandEvents = BandEvents::whereDate('event_time', Carbon::today())->get();
            
            foreach($BandEvents as $event)
            {

                $band = $event->band;
                $owners = $band->owners;
                $members = $band->members;
                foreach($owners as $person)
                {
                    $member = $person->user;
                    Mail::to($member->email)->send(new EventReminder($event));
                }
                foreach($members as $person)
                {
                    $member = $person->user;
                    Mail::to($member->email)->send(new EventReminder($event));
                }
            }
        })->dailyAt('9:00');

        $schedule->call(function(){
            $bands = Bands::all();

            foreach($bands as $band)
            {
                $reminder = new AdvanceReminderService($band);
                $reminder->searchAndSend();
            }
        })->weeklyOn(2, '23:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
