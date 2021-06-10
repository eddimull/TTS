<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="94R9Fxp061AJERSGHgf39YHORsa2GaDoomPXsOLM">
        <!-- tailwind -->
        <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">
    </head>
    <body style="size: legal; width: 1280px;" class="font-sans antialiased">
        <div class="border-2 min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8 background-gray-400">
               <div class="my-4 max-w-md flex justify-center align-center mx-auto">
                <img src="{{ url($proposal['band']['logo']) }}" />
                </div>
                <hr/>
                <div>
                    <p><strong>{{ $proposal['band']['name'] }}</strong> (hereinafter referred to as "Artist"), enter into this Agreement
with <strong>{{ $proposal['proposal_contacts'][0]['name'] }}</strong> (hereinafter referred to as “Buyer”), for the engagement of a live musical performance
(hereinafter referred to as the “Venue”), subject to the following conditions:
                </div>
                <div class="mt-3 mb-12">
                    <p class="text-xl font-bold my-2">Details of engagement:</p>
                    <div>
                        <ul>
                            <li><span class="font-bold">Dates:</span> {{ date('Y/m/d',strtotime($proposal['date'])) }} </li>
                            <li><span class="font-bold">Performance Time:</span> {{ date('g:i A',strtotime($proposal['date'])) }}</li>
                            <li><span class="font-bold">Performance Length:</span> {{ $proposal['hours'] }} hours</li>
                            <li><span class="font-bold">Sound Check Time: </span> at least 1 hour before performance</li>
                            <li><span class="font-bold">Venue:</span> {{ $proposal['location'] }}</li>
                            <li>
                                <span class="font-bold">Point(s) of Contact:</span>
                                <ul class="mx-4">
                                    @foreach($proposal['proposal_contacts'] as $contact)
                                    <li>{{ $contact['name'] }} - {{ $contact['email'] }} - {{ $contact['phonenumber'] }}</li>
                                    @endforeach
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">Compensation and deposit</p>
                    <p class="mb-3">Buyer will pay a total of <span class="font-bold">${{ number_format($proposal['price'],2) }}</span> to Artist as compensation for Artist’s performance. Unless otherwise specied, the contract price paid to Artist shall be inclusive of Agent’s agreed upon commission.</p>
                    <p class="mb-3">Buyer will pay Agent a deposit of <span class="font-bold">${{ number_format($proposal['price']/2,2) }}</span>, within three weeks of the execution of this Agreement. The deposit
is non-refundable after execution of this contract. The deposit shall be made payable to <strong>{{ $proposal['band']['name'] }}</strong> and
shall be in form of <strong>check, money order, Chase Quick Pay, Venmo, cashier’s check, or credit card
(additional fees may apply)</strong>. If the Buyer pays the Deposit by check, which should be mailed to:</p>
                    <div class="mb-3">
                        <ul>
                            <li>{{ $proposal['band']['name'] }}</li>
                            <li>Address</li>
                            <li>City State Zip</li>
                        </ul>
                    </div>
                    <p class="mb-3">
                    Agent shall withhold Agent’s booking fee from the deposit paid by Buyer, and shall release the remainder of
                    the deposit to Artist following the Performance. In the event Artist and Agent agree that Agent shall release
                    the Artist's compensation from the deposit prior to the performance date, Artist shall be liable to the Buyer for
                    such amounts if Artist should breach this Contract.
                    </p>
                    <p class="mb-3">
                    Buyer shall pay Agent the remaining gross compensation of <span class="font-bold">${{ number_format($proposal['price']/2,2) }}</span> at least ten (10) days before
                    Performance. <strong>If Buyer elects to pay via check, money order, or cashier's check, payment shall be made to
                    {{ $proposal['band']['name'] }} and must be received at least ten (10) days prior to Performance. If Buyer
                    elects to pay via Chase Quick Pay, Venmo, or credit card, payment shall be made to {{ $proposal['band']['name'] }}
                    ten (10) days prior to the Performance. (Additional fees may apply to credit card payments.)</strong> In the event
                    that Buyer requests that Artist perform past the end time set forth in this Agreement, and Artist chooses
                    continue performing, Buyer shall pay Artist <strong>${{ number_format(($proposal['price']/$proposal['hours'])*1.5,2) }}</strong> directly for each additional sixty minutes of the
                    Performance, payable immediately following the Performance. 
                    </p>
                </div>
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">Artistic Control</p>
                    <p>The Artist shall have sole control of the artistic content of the performance within the terms of the Agreement
                        between Artist and Buyer. The Buyer is allowed to select a reasonable amount of songs from the Artist's play
                        list. These songs must be selected no later than six weeks prior to the performance date.</p>
                </div>
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">STAGE RESTRICTIONS</p>
                    <p>The Buyer agrees that Buyer and Buyer's guests shall refrain from entering the stage and/or performance area,
                    unless specically invited to do so by Artist. In no event shall Buyer or Buyer's guests bring any food or
                    beverages onto the stage and/or performance area. In the event that Artist's equipment sustains damage as a
                    result of these prohibited actions, Buyer shall be liable for all costs to repair and/or replace such equipment.</p>
                </div>
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">PHYSICAL FACILITIES</p>
                    <p class="mb-3">The Venue and/or Buyer will provide safe working conditions and ensure all equipment and facilities are in
                    good working order. It is the responsibility of the Buyer to ensure safe and adequate power supplies operated
                    by competent persons in accordance with all statutory requirements. Accordingly, the Buyer agrees to obtain
                    all permits, consents, and licenses necessary for the Performance. The Buyer shall be liable for repair and/or
                    replacement of Artist's equipment in the event that such equipment sustains damage as a result of inadequate
                    facilities</p>
                    <p class="mb-3">
                    Artist will be responsible for providing all musical instruments, PA system, lights, Microphone, monitors,
                    cables, stands, and stage sets not specically provided for in this Agreement. Artist will be responsible for
                    assembling and disassembling Artist’s stage setup before and after the performance and for hiring and
                    compensating stage crew to perform these functions, if necessary. Artist will have at least one designated
                    microphone for announcements or speeches. Artist will also provide a connection and/or an adapter to their
                    PA system for a smart phone and/or ipod to play canned music.
                    </p>
                </div>
                @if($proposal['event_type_id'] === 1)
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">SPECIAL INSTRUCTIONS</p>
                    <p class="mb-3"><span class="underline">Song/Artist Request</span>: TBD Buyer must provide song suggestions and/or specic requests via
                    iTunes playlist and share with Agent and Artist. Suggested song lists shall be provided no later than 30 days
                    prior to the Performance. Specic Artist Request lists shall be provided no later than 60 days prior to
                    Performance.
                    </p>
                    <p class="mb-3"><span class="underline">Break Music</span>: Artist provides break music</p>
                    <p class="mb-3 font-bold">***Buyer is responsible for all pre recorded music. <span class="underline">Pre recorded Versions will be provided by the Buyer
                    no later than 30 days prior to event date</span></p>
                                        <p class="mb-3"><span class="underline">Attire</span>: Artist shall dress in SEMI-FORMAL. Please ask Agent if there are any questions.</p>
                                        <p class="mb-3"><span class="underline">Stage, Performance Area, and Size of Event:</span> <br/>Artist shall NOT be required to provide a stage on which to perform, unless otherwise agreed to in writing by
                    Artist, Agent, and Buyer. Additional fees may be incurred if Artist provides a stage.</p>
                                        <p class="mb-3"><span class="underline">Hospitality</span>:Vendor meals will be provided for Artist at discretion of buyer. TBD Guest(s) of Artist(s) are
                    permitted. Buyer must provide a dedicated, private space for Artist to dress and otherwise prepare for
                    Performance. Buyer must provide a case of water for Artist.
                    </p>
                    <p class="mb-3"><span class="underline">Dances</span>: <span class="font-bold underline"> Exact versions must be provided by buyer no later than 30 days prior to the performance.
                    </span> FIRST DANCE, FATHER BRIDE, MOTHER GROOM, MONEY DANCE, GARTER</p>
                    <p class="mb-3 underline font-bold">-All special dances will be determined by Buyer no later than 30 days prior to performance.</p>
                    <p class="mb-3"><span class="underline">Announcements</span>: TBD</p>
                </div>
                @endif
                <div class="my-3">
                <p class="text-lg font-bold my-2 uppercase">CANCELLATION:</p>
                <p class="mb-3">If Buyer cancels <strong>30 days or less</strong> before the performance, Buyer will pay Artist 100% of the guaranteed fee for
                the performance. In the event of any such cancellation, Agent shall be entitled to agreed upon commission.</p>
                </div>
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">FORCE MAJEURE</p>
                    <p class="mb-3">
                    Neither Party shall be in breach of its obligations under this Agreement (other than payment obligations) or
                    incur any liability to the other Party for any losses or damages caused by a Force Majeure Event except to the
                    extent that the relevant breach of its obligations would have occurred, or the relevant losses or damages
                    would have arisen, even if the Force Majeure Event had not occurred. In this Clause, "Force Majeure Event"
                    means an event beyond the control of the Parties, which prevents a Party from complying with any of its
                    obligations under this Contract, including but not limited to Acts of God (such as, but not limited to, res,
                    explosions, earthquakes, drought, tidal waves and oods); specic incidents of exceptional adverse weather
                    conditions (such as, but not limited to, hurricane, earthquake, tornado, or any other natural disaster of
                    overwhelming proportions); discontinuation of electricity supply; and other unforeseeable circumstances
                    beyond the control of the Parties against which it would have been unreasonable for the aected party to
                    take precautions and which the aected party cannot avoid even by using its best eorts. If a Party wishes to
                    claim protection with respect to an Event of Force Majeure, it shall, as soon as possible following the
                    occurrence, notify the other Party of the inability to perform its obligations under this Agreement.
                    </p>
                </div>
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">INCLEMENT WEATHER, ILLNESS, AND ACCIDENTS</p>
                    <p class="mb-3">
                    If performance is to be outside, Buyer shall provide cover under a dry overhang, tent, or other structure of a
                    size sucient to protect Artist(s) and equipment from inclement weather. If no such tent or structure is
                    provided, Artist, at Artist’s sole judgment, upon threat of inclement weather, may cease
                    performance, secure all equipment, and resume performance upon passing of the inclement weather if
                    desired by Buyer and the passage of time does not conict with a subsequently scheduled performance of
                    Artist’s. If no structure is provided and equipment is damaged by inclement weather, the Buyer may be held
                    responsible for repairs or replacement of Artist’s equipment.
                    Artist’s agreement to perform is subject to proven detention by serious illness or serious accident, not
                    including any minor vehicle accidents that may occur on the way to the performance. In the event of such
                    non-performance, the deposit payment (if any) advanced to the Artist shall be returned promptly.
                    Artist is responsible for securing reliable transportation to the performance, and any transportation issues that
                    arise do not in any way aect Artist's duty to perform pursuant to the terms of this Agreement. Artist shall not
                    be reimbursed or compensated by Agent or Buyer for additional costs resulting from transportation issues,
                    either foreseen or unforeseen.
                    </p>
                </div>     
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">SEVERABILITY OF INVALID PROVISIONS</p>
                    <p class="mb-3">
                    If any provision of this Agreement is deemed unenforceable, that provision will be omitted only to the extent
                    necessary to make this agreement valid and enforceable, and the remaining provisions will remain in full force
                    and effect.
                    </p>
                </div>      
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">NON-DISCLOSURE OF FINANCIAL INFORMATION</p>
                    <p class="mb-3">
                    Buyer hereby agrees to refrain from discussing or otherwise disclosing to any third party the details of the
                    financial aspects of this Agreement, including but not limited to price negotiations and nal agreed upon price.
                    </p>
                </div>       
                <div class="my-3">
                    <p class="text-lg font-bold my-2 uppercase">GOVERNING LAW</p>
                    <p class="mb-3">
                    This agreement will be governed in all respects by the laws of the State of Louisiana, and the courts of East
                    Baton Rouge Parish, State of Louisiana, shall have exclusive jurisdiction.
                    </p>
                </div>   
                <div class="my-4">
                    <p class="text-lg font-bold my-2">Buyer</p>
                    <p>I Agree to the terms and conditions of this contract<p>
                    <div>
                        <strong class="underline">{{ $proposal['proposal_contacts'][0]['name'] }}</strong> - <strong>{{ date('m/d/Y') }}</strong>
                    </div>
                </div>                                              
        </div>
    </body>
</html>