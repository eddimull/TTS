<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreContractsRequest;
use App\Http\Requests\UpdateContractsRequest;
use App\Http\Requests\SendBookingContractRequest;

class ContractsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContractsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Contracts $contract)
    {
        $filePath = \urldecode($contract->asset_url); // Adjust this based on your actual model structure
        $filePath = Str::replace('https://bandapp.s3.us-east-2.amazonaws.com/', '', $filePath);
        // dd($filePath);
        // Check if the file exists
        if (!Storage::disk('s3')->exists($filePath))
        {
            abort(404);
        }

        // Stream the file from S3
        $stream = Storage::disk('s3')->readStream($filePath);

        return response()->stream(
            function () use ($stream)
            {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => Storage::disk('s3')->mimeType($filePath),
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contracts $contracts)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContractsRequest $request, Bands $band, Bookings $booking)
    {
        $contract = $booking->contract()->firstOrCreate([], ['author_id' => Auth::id()]);

        $contract->update($request->validated());

        return redirect()->back()->with('successMessage', 'Contract Saved.');
    }

    public function downloadBookingContract(Bands $band, Bookings $booking)
    {
        // return 'test';
        $contract = $booking->contract;
        dd($contract);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Bands  $band
     * @param  Bookings  $booking
     * @return \Illuminate\Http\Response
     */
    public function sendBookingContract(SendBookingContractRequest $request, Bands $band, Bookings $booking,)
    {
        $contact = $booking->contacts()->find($request->signer);

        if (!$contact)
        {
            return redirect()->back()
                ->withErrors(['Contact not found' => 'The specified signer contact was not found for this booking.'])
                ->withInput();
        }

        $contractPdf = $booking->getContractPdf($contact);
        $booking->storeContractPdf($contractPdf);
        $contract = $booking->contract;

        if (!$contract)
        {
            return redirect()->back()
                ->withErrors(['Contract not found' => 'No contract found for this booking.'])
                ->withInput();
        }

        try
        {
            $contract->sendToPandaDoc(
                $contact,
                $request->has('cc') ? $booking->contacts()->find($request->cc) : null
            );

            $booking->status = 'pending';
            $booking->save();

            return redirect()->back()
                ->with('successMessage', 'Contract sent successfully to PandaDoc.');
        }
        catch (\Exception $e)
        {
            return redirect()->back()
                ->withErrors(['Failed to send contract:' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contracts $contracts)
    {
        //
    }

    public function getHistory(Contracts $contract)
    {
        return response()->json(['history' => $contract->auditTrail()]);
    }
}
