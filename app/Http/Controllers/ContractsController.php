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
        $signer = DocuSign::signer([
            'name'  => 'John Doe',
            'email' => 'Jdoe123@example.com'
            ]);
            
            $envelope = [
                'template_id'=>'397acf0b-9fe1-41a4-871e-8aca5719e53e',
                'signer_email'=>'eddimull@gmail.com',
                'signer_name'=>'Eddizzle Muller',
                'cc_email'=>'eddimull@yahoo.com',
                'cc_name'=>'Eddie 2'
                ];
        // dd($client->templates->listTemplates());
        // $options = new CreateEnvelopeOptions();
        $test = new \LaravelDocusign\Client;
        // $options->setCdseMode(null);
        // $options->setMergeRolesOnDraft(null);
        // dd(CreateEnvelopeOptions::class);
        // $sent = $this->worker($envelope);
        // $test = new EnvelopeDefinition();
        // dd($test->envelopes);
        // $client->envelopes

        // $sent = $client->envelopes->createEnvelopeWithHttpInfo($this->make_envelope($envelope));
        $proposal = Proposals::find(1);
        // dd($proposal);

        $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        
        return $pdf->download('test.pdf');
        // return View();
    }


    private function make_envelope($args)
    {
        # Create the envelope definition with the template_id
        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
           'status' => 'sent', 'template_id' => $args['template_id'],
           'email_subject'=> 'Contract for Three Thirty Seven',
           'email_blurb'=>'This is the body of the email that gets sent out'
        ]);
        # Create the template role elements to connect the signer and cc recipients
        # to the template
        $signer = new \DocuSign\eSign\Model\TemplateRole([
            'email' => $args['signer_email'], 'name' => $args['signer_name'],
            'role_name' => 'signer'
        ]);
        # Create a cc template role.
        $cc = new \DocuSign\eSign\Model\TemplateRole([
            'email' => $args['cc_email'], 'name' => $args['cc_name'],
            'role_name' => 'cc'
        ]);

        
        # Add the TemplateRole objects to the envelope object
        $envelope_definition->setTemplateRoles([$signer, $cc]);
        // dd('got here?');
        return $envelope_definition;
    }

    private function worker($args)
    {
        $envelope_args = $args["envelope_args"];
        # Create the envelope request object
        $envelope_definition = $this->make_envelope($envelope_args);
        # Call Envelopes::create API method
        # Exceptions will be caught by the calling function
        // $config = new \DocuSign\eSign\Configuration();
        // $config->setHost($args['base_path']);
        // $config->addDefaultHeader('Authorization', 'Bearer ' . $args['ds_access_token']);
        // $api_client = new \DocuSign\eSign\client\ApiClient($config);
        $client = DocuSign::create();
        // dd();
        $envelope_api = new \DocuSign\eSign\Api\EnvelopesApi($client);
        $results = $client->envelopes->createEnvelope('14017186',$envelope_definition,CreateEnvelopeOptions::class);
        $envelope_id = $results->getEnvelopeId();
        return ['envelope_id' => $envelope_id];
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

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Contracts  $contracts
     * @return \Illuminate\Http\Response
     */
    public function show(Contracts $contracts)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Contracts  $contracts
     * @return \Illuminate\Http\Response
     */
    public function edit(Contracts $contracts)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Contracts  $contracts
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contracts $contracts)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Contracts  $contracts
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contracts $contracts)
    {
        //
    }
}
