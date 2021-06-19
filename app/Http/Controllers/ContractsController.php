<?php

namespace App\Http\Controllers;

use App\Models\Contracts;
use App\Models\Proposals;
use DocuSign\eSign\Api\EnvelopesApi\CreateEnvelopeOptions;
use Illuminate\Http\Request;
use LaravelDocusign\Facades\DocuSign;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\Rest\Api\Envelopes;
use PDF;
use Log;
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
        $client = DocuSign::create();
        // $signer = DocuSign::signer([
        //     'name'  => 'John Doe',
        //     'email' => 'Jdoe123@example.com'
        //     ]);
            
            $envelope = [
                'signer_email'=>'eddimull@gmail.com',
                'signer_name'=>'Eddie muller',
                'cc_email'=>'eddimull@yahoo.com',
                'cc_name'=>'Eddie 2'
                ];

        // dd($client);
        // dd($client->templates->listTemplates());
        // $options = new CreateEnvelopeOptions();
        $test = new \LaravelDocusign\Client;

        // $client->document
        // $options->setCdseMode(null);
        // $options->setMergeRolesOnDraft(null);
        // dd(CreateEnvelopeOptions::class);
        // $sent = $this->worker($envelope);
        // $test = new EnvelopeDefinition();
        // dd($test->envelopes);
        // $client->envelopes

        // $sent = $client->envelopes->createEnvelopeWithHttpInfo($this->make_envelope_from_docusign($envelope));

        // return $base64PDF;
        // dd($proposal);
        // dd($sent[0]['envelope_id']);

        
        $proposal = Proposals::find(1);
        // $sent = $this->make_document_for_pandadoc($proposal);
        
        $sent = Http::withHeaders([
        'Authorization'=>'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/=>https://dev.tts.band/pandadocWebhook');
            
        // dd($sent);
        return $sent;
        // $band = Bands::find($proposal->band_id);
        // return View('contract',['proposal'=>$proposal]);
        // return 
        // $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        // $base64PDF = base64_encode($pdf->output());
        // dd($proposal->name);
        // $imagePath = $band->site_name . '/' . $proposal->name . '_contract_' . time() . '.pdf';
           
        // $path = Storage::disk('s3')->put($imagePath,
        // base64_decode($base64PDF),
        // ['visibility'=>'public']);
        
        // dd(Storage::disk('s3')->url($imagePath));
        return $sent;

    }

    private function make_document_for_pandadoc($proposal)
    {
        $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        $base64PDF = base64_encode($pdf->output());
        $band = Bands::find(1);
        $imagePath = $band->site_name . '/' . $proposal->name . '_contract_' . time() . '.pdf';

        $path = Storage::disk('s3')->put($imagePath,
        base64_decode($base64PDF),
        ['visibility'=>'public']);

        $body =  [
            "name"=> "Contract for " . $proposal->band->name,
            "url"=>Storage::disk('s3')->url($imagePath),
            "tags"=> [
            "tag_1"
            ],
        "recipients"=> [  
            [  
                "email"=> $proposal->proposal_contacts[0]->email,
                "first_name"=>explode(' ',$proposal->proposal_contacts[0]->name)[0],
                "last_name"=>explode(' ',$proposal->proposal_contacts[0]->name)[1],
                "role"=> "user"
            ]
        ],
        "fields"=> [  
            "name"=> [  
                "value"=> $proposal->proposal_contacts[0]->name,
                "role"=> "user"
            ]
        ],
        "parse_form_fields"=> false
        ];

        

        $response = Http::withHeaders([
            'Authorization'=>'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])
        ->acceptJson()
        ->post('https://api.pandadoc.com/public/v1/documents',$body);
        

        sleep(5);
        $uploadedDocumentId = $response['id'];

        $sent = Http::withHeaders([
            'Authorization'=>'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/https://dev.tts.band/pandadocWebhook',[
            "messsage"=>'Please sign this contract so we can make this official!',
            "subject"=>'Contract for ' . $proposal->band->name
        ]);

        $sent = Http::withHeaders([
            'Authorization'=>'API-Key 9af58cc39e881426bb08c7664db5cade47ed110c'
        ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send',[
            "messsage"=>'Please sign this contract so we can make this official!',
            "subject"=>'Contract for ' . $proposal->band->name
        ]);

        return $sent;
    }


    // private function make_envelope_from_docusign(array $args)=> EnvelopeDefinition
    // {
    //     $proposal = Proposals::find(1);
    //     $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
    //     $base64PDF = base64_encode($pdf->output());


    //     $sender = new \DocuSign\eSign\Model\UserInfo([
    //         'user_name'=>'Doot doot',
    //         'email'=>$proposal->author->email
    //     ]);
    //     # Create the envelope definition
    //     $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
    //        'email_subject' => 'Contract for ' . $proposal->band->name,
    //        'email_blurb'=>'Please sign this contract so we can make this official!'
    //     ]);
    //     $envelope_definition->setSender($sender);
    //     // dd($envelope_definition->getSender());
    //     // $doc1_b64 = base64_encode($this->clientService->createDocumentForEnvelope($args));
    //     # read files 2 and 3 from a local directory
    //     # The reads could raise an exception if the file is not available!


    //     $document = new \DocuSign\eSign\Model\Document([  # create the DocuSign document object
    //         'document_base64' => $base64PDF,
    //         'name' => 'Contract for ' . $proposal->band->name,  # can be different from actual file name
    //         'file_extension' => 'pdf',  # many different document types are accepted
    //         'document_id' => '1'  # a label used to reference the doc
    //     ]);
    //     # The order in the docs array determines the order in the envelope
    //     $envelope_definition->setDocuments([$document]);

    //     # Create the signer recipient model
    //     $signer1 = new \DocuSign\eSign\Model\Signer([
    //         'email' => $args['signer_email'], 'name' => $args['signer_name'],
    //         'recipient_id' => "1", 'routing_order' => "1"]);
    //     # routingOrder (lower means earlier) determines the order of deliveries
    //     # to the recipients. Parallel routing order is supported by using the
    //     # same integer as the order for two or more recipients.

    //     # create a cc recipient to receive a copy of the documents
    //     $cc1 = new \DocuSign\eSign\Model\CarbonCopy([
    //         'email' => $args['cc_email'], 'name' => $args['cc_name'],
    //         'recipient_id' => "2", 'routing_order' => "2"]);

    //     # Create signHere fields (also known as tabs) on the documents,
    //     # We're using anchor (autoPlace) positioning
    //     #
    //     # The DocuSign platform searches throughout your envelope's
    //     # documents for matching anchor strings. So the
    //     # signHere2 tab will be used in both document 2 and 3 since they
    //     #  use the same anchor string for their "signer 1" tabs.
    //     $sign_here1 = new \DocuSign\eSign\Model\SignHere([
    //         'anchor_string' => 'Signature=>', 'anchor_units' => 'pixels',
    //         'anchor_y_offset' => '10', 'anchor_x_offset' => '40']);
       

    //     # Add the tabs model (including the sign_here tabs) to the signer
    //     # The Tabs object wants arrays of the different field/tab types
    //     $signer1->setTabs(new \DocuSign\eSign\Model\Tabs([
    //         'sign_here_tabs' => [$sign_here1]]));

    //     # Add the recipients to the envelope object
    //     $recipients = new \DocuSign\eSign\Model\Recipients([
    //         'signers' => [$signer1], 'carbon_copies' => [$cc1]]);
    //     $envelope_definition->setRecipients($recipients);
    //     dd($envelope_definition);
    //     # Request that the envelope be sent by setting |status| to "sent".
    //     # To request that the envelope be created as a draft, set to "created"
    //     $envelope_definition->setStatus('sent');

    //     return $envelope_definition;
    // }

    public function webhook(Request $request)
    {
        
        $contract = Contracts::where('envelope_id',$request['envelopeId'])->first();
        // dd($contract);
        if($contract)
        {
            $proposal = $contract->proposal;
            $contract->status = $request['status'];
            
            if($contract->status == 'completed')
            {

                $band = Bands::find($proposal->band_id);
                
                foreach($band->owners as $owner)
                {
                    $user = User::find($owner->user_id);
                    $user->notify(new TTSNotification([
                        'text'=>'Contract for ' . $proposal->name . ' signed and completed!',
                        'route'=>'proposals',
                        'routeParams'=>'',
                        'url'=>'/proposals/'
                        ]));
                }
                    
            }
            $contract->save();
        }
        
        return response('success');
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

}