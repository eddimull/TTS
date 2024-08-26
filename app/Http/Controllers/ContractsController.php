<?php

namespace App\Http\Controllers;

use App\Models\Contracts;
use Illuminate\Http\Request;
use PDF;
use App\Models\Bands;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Notifications\TTSNotification;
use Illuminate\Support\Facades\Http;

class ContractsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $sent = Http::withHeaders([
            'Authorization' => 'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/=>https://dev.tts.band/pandadocWebhook');

        return $sent;
    }

    private function make_document_for_pandadoc($proposal)
    {
        $pdf = PDF::loadView('contract', ['proposal' => $proposal]);
        $base64PDF = base64_encode($pdf->output());
        $band = Bands::find(1);
        $imagePath = $band->site_name . '/' . $proposal->name . '_contract_' . time() . '.pdf';

        $path = Storage::disk('s3')->put(
            $imagePath,
            base64_decode($base64PDF),
            ['visibility' => 'public']
        );

        $body =  [
            "name" => "Contract for " . $proposal->band->name,
            "url" => Storage::disk('s3')->url($imagePath),
            "tags" => [
                "tag_1"
            ],
            "recipients" => [
                [
                    "email" => $proposal->proposal_contacts[0]->email,
                    "first_name" => explode(' ', $proposal->proposal_contacts[0]->name)[0],
                    "last_name" => explode(' ', $proposal->proposal_contacts[0]->name)[1],
                    "role" => "user"
                ]
            ],
            "fields" => [
                "name" => [
                    "value" => $proposal->proposal_contacts[0]->name,
                    "role" => "user"
                ]
            ],
            "parse_form_fields" => false
        ];



        $response = Http::withHeaders([
            'Authorization' => 'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])
            ->acceptJson()
            ->post('https://api.pandadoc.com/public/v1/documents', $body);


        sleep(5);
        $uploadedDocumentId = $response['id'];

        $sent = Http::withHeaders([
            'Authorization' => 'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/https://dev.tts.band/pandadocWebhook', [
            "messsage" => 'Please sign this contract so we can make this official!',
            "subject" => 'Contract for ' . $proposal->band->name
        ]);

        $sent = Http::withHeaders([
            'Authorization' => 'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send', [
            "messsage" => 'Please sign this contract so we can make this official!',
            "subject" => 'Contract for ' . $proposal->band->name
        ]);

        return $sent;
    }

    public function webhook(Request $request)
    {

        $contract = Contracts::where('envelope_id', $request['envelopeId'])->first();
        if ($contract)
        {
            $proposal = $contract->proposal;
            $contract->status = $request['status'];

            if ($contract->status == 'completed')
            {

                $band = Bands::find($proposal->band_id);

                foreach ($band->owners as $owner)
                {
                    $user = User::find($owner->user_id);
                    $user->notify(new TTSNotification([
                        'text' => 'Contract for ' . $proposal->name . ' signed and completed!',
                        'route' => 'proposals',
                        'routeParams' => '',
                        'url' => '/proposals/'
                    ]));
                }
            }
            $contract->save();
        }

        return response('success');
    }
}
