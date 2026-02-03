@extends('layouts.PDF', ['bodyStyle' => 'size: legal; width: 1200px;', 'contentClasses' => ''])
@section('content')
<x-pdf.contractHeader>
    <img
        src="{{ $logoDataUri }}"
        alt="Band Logo"
        class="max-w-[200px] max-h-[100px] mx-auto" />
</x-pdf.contractHeader>
<x-pdf.section>
    <strong>{{ $booking->band->name }}</strong> (hereinafter referred to as "Artist"), enter into this Agreement
    with <strong>{{ $signer->name }}</strong> (hereinafter referred to as "Buyer"), for the engagement of a live musical performance
    (hereinafter referred to as the "Venue"), subject to the following conditions:
</x-pdf.section>
<x-pdf.section>
    <div class="mb-4">
        <h2 class="text-xl font-bold mb-2">
            Details of engagement:
        </h2>
        <ul class="list-disc pl-5">
            <li><span class="font-bold">Date:</span> {{ date('m/d/Y',strtotime($booking->date)) }}</li>
            <li><span class="font-bold">Performance Length:</span> {{ $booking->duration }} hours</li>
            <li><span class="font-bold">Sound Check Time:</span> at least 1 hour before performance</li>
            <li><span class="font-bold">Venue:</span> {{ $booking->venue_name }}</li>
            <li>
                <span class="font-bold">Point(s) of Contact:</span>
                <ul class="list-disc pl-5">
                    @foreach($booking->contacts as $contact)
                    <li>{{ $contact['name'] }} - {{ $contact['email'] }}</li>
                    @endforeach
                </ul>
            </li>
        </ul>
    </div>
</x-pdf.section>
<x-pdf.section>
    <x-pdf.contractSectionHeader>
        Contract Information - {{$booking->name}}
    </x-pdf.contractSectionHeader>
    <div class="my-3">
        <p class="text-lg font-bold my-2 uppercase">Compensation and deposit</p>
        <p class="mb-3">Buyer will pay a total of <span class="font-bold">${{ number_format($booking->price,2) }}</span> to Artist as compensation for Artist’s performance.</p>
        <p class="mb-3">Buyer will pay a deposit of <span class="font-bold">${{ number_format($booking->price/2,2) }}</span>, within three weeks of the execution of this Agreement. The deposit
            is non-refundable after execution of this contract. The deposit shall be made payable to <strong>{{ $booking->band->name }}</strong> and
            shall be in form of <strong>check, money order, Venmo, cashier’s check, invoice, or credit card
                (additional fees may apply)</strong>. If the Buyer pays the Deposit by check, which should be mailed to:</p>
        <div class="mb-3">
            <ul>
                <li>{{ $booking->band->name }}</li>
                <li>{{ $booking->band->address }}</li>
                <li>{{ $booking->band->city }}, {{ $booking->band->state }} {{ $booking->band->zip }}</li>
            </ul>
        </div>
        <p class="mb-3">
            Buyer shall pay the remaining gross compensation of <span class="font-bold">${{ number_format($booking->price/2,2) }}</span> at least ten (10) days before
            Performance. <strong>If Buyer elects to pay via check, money order, or cashier's check, payment shall be made to
                {{ $booking->band->name }} and must be received at least ten (10) days prior to Performance. If Buyer
                elects to pay via Invoice, Venmo, or credit card, payment shall be made to {{ $booking->band->name }}
                ten (10) days prior to the Performance. (Additional fees may apply to credit card payments.)</strong> In the event
            that Buyer requests that Artist perform past the end time set forth in this Agreement, and Artist chooses
            to continue performing, Buyer shall pay Artist <strong>${{ number_format(($booking->price/$booking->duration)*1.5,2) }}</strong> directly for each additional sixty minutes of the
            Performance, limited to one additional hour, payable immediately following the Performance.
        </p>
    </div>
</x-pdf.section>
@foreach($booking->contract->custom_terms as $term)
<x-pdf.section>
    <x-pdf.contractSectionHeader>
        {{$term['title']}}
    </x-pdf.contractSectionHeader>
    <div class="my-3">
        {!! $term['content'] !!}
    </div>
</x-pdf.section>
@endforeach
@if ($booking->event_type_id === 1)
<x-pdf.section>
    <div class="my-3">
        <p class="text-lg font-bold my-2 uppercase">SPECIAL INSTRUCTIONS</p>
        <p class="mb-3"><span class="underline">Song/Artist Request</span>: TBD Buyer must provide song suggestions and/or specic requests via questionnaire sent prior to the event. Suggested song lists shall be provided no later than 30 days
            prior to the Performance. Specific Artist Request lists shall be provided no later than 60 days prior to
            Performance.
        </p>
        <p class="mb-3"><span class="underline">Break Music</span>: Artist provides break music</p>
        <p class="mb-3"><span class="underline">Attire</span>: Artist shall dress in SEMI-FORMAL. Please ask if there are any questions.</p>
        <p class="mb-3"><span class="underline">Stage, Performance Area, and Size of Event:</span> <br />Artist shall NOT be required to provide a stage on which to perform, unless otherwise agreed to in writing by
            Artist and Buyer. Additional fees may be incurred if Artist provides a stage.</p>
        <p class="mb-3"><span class="underline">Hospitality</span>:Vendor meals will be provided for Artist at discretion of buyer. TBD Guest(s) of Artist(s) are
            permitted.
        </p>
        <p class="mb-3"><span class="underline">Dances</span>: <span class="font-bold underline"> Exact versions must be provided by buyer no later than 30 days prior to the performance.
            </span> FIRST DANCE, FATHER BRIDE, MOTHER GROOM, MONEY DANCE, GARTER</p>
        <p class="mb-3 underline font-bold">-All special dances will be determined by Buyer no later than 30 days prior to performance.</p>
    </div>
</x-pdf.section>
@endif
<x-pdf.section>
    <div class="mt-8">
        <p class="font-bold">
            Buyer
        </p>
        <p>I Agree to the terms and conditions of this contract</p>
        <div>
            <strong class="underline">{{ $signer->name }}</strong> - <strong>{{ date('m/d/Y') }}</strong>
        </div>
        <br />
        <br />
        <div>
            <span>Signature:</span>
            <div> {signature:user___________________________}</div>
        </div>

    </div>
</x-pdf.section>
@endsection