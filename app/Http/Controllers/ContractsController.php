<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreContractsRequest;
use App\Http\Requests\UpdateContractsRequest;

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

    public function sendBookingContract(Bands $band, Bookings $booking)
    {
        $contractPdf = $booking->getContractPdf();
        $booking->storeContractPdf($contractPdf);
        $contract = $booking->contract;


        if (!$contract)
        {
            return redirect()->back()->withErrors(['Contract not found' => 'No contract found for this booking.']);
        }

        try
        {
            $result = $contract->sendToPandaDoc();
            $booking->status = 'pending';
            $booking->save();
            return redirect()->back()->with('successMessage', 'Contract sent successfully to PandaDoc.');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors(['Failed to send contract:' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contracts $contracts)
    {
        //
    }
}
