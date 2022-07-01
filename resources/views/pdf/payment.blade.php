<x-PDF-layout>
    <x-pdf.header>
        <div class="text-7xl">Receipt</div>
        <div>
            <x-pdf.headersub title="Date">
                {{date('Y-m-d')}}
            </x-pdf.headersub>
            <x-pdf.headersub title="Invoice Number">
                {{$payment->id}}
            </x-pdf.headersub>
        </div>
    </x-pdf.header>
    <x-pdf.section>    
        <x-pdf.sectionheader>
            Contract Information - {{ $payment->proposal->name}}
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">
            <div class="flex flex-row justify-evenly">
                <div>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Provider
                        </x-slot>
                        {{$payment->proposal->band->name}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Date
                        </x-slot>
                        {{$payment->proposal->formattedPerformanceDate}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Venue
                        </x-slot>
                        {{$payment->proposal->location}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Price
                        </x-slot>
                        ${{$payment->proposal->formattedPrice}}
                    </x-pdf.sectionitem>
                </div>
                <div>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                        Point of Contact
                        </x-slot>
                        <div>
                            {{$payment->proposal->author->name}}
                        </div>
                        <div>{{$payment->proposal->author->email}}</div>
                    </x-pdf.sectionitem>
                </div>
            </div>
        </div>
    </x-pdf.section>
    <x-pdf.section>
        <x-pdf.sectionheader>
            Client Information
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">
            <div class="flex flex-row justify-evenly">

                @foreach($payment->proposal->ProposalContacts as $contact)
                <div>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Full Name
                        </x-slot>
                        
                        {{$contact->name}}
                        
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Phone Number
                        </x-slot>
                        
                        {{$contact->phonenumber}}
                        
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Email
                        </x-slot>
                        
                        {{$contact->email}}
                        
                    </x-pdf.sectionitem>
                    </div>
                @endforeach
            </div>
        </div>
    </x-pdf.section>
    <x-pdf.section>
        <x-pdf.sectionheader>
           Previous Payments
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">
            <ol class="list-decimal list-outside marker:text-xl">
                @foreach($payment->proposal->payments as $proposalPayment)
                <li class="pl-2 mb-4">
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Amount Received
                        </x-slot>
                        
                        ${{$proposalPayment->formattedPaymentAmount}}
                        
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Date Received
                        </x-slot>
                        
                        {{$proposalPayment->formattedPaymentDate}}
                        
                    </x-pdf.sectionitem>
                </li>
                @endforeach
            <ol>
            <x-pdf.subsection>
                <x-slot name="left">
                    Amount Left
                </x-slot>
                <x-slot name="right">
                    @if($payment->proposal->amountLeft === '0.00')
                    <span class="text-green-400">
                        ${{$payment->proposal->amountLeft}}
                    </span>
                    @else
                    <span class="text-green-600">
                        ${{$payment->proposal->amountLeft}}
                    </span>
                    @endif
                </x-slot>
            </x-pdf.subsection>
        </div>
    </x-pdf.section>
    <x-pdf.section>
        <x-pdf.sectionheader>
            Payment Information
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">

            <x-pdf.sectionitem>
                <x-slot name="title">
                    Amount Received
                </x-slot>
                
                ${{$payment->formattedPaymentAmount}}
                
            </x-pdf.sectionitem>
            <x-pdf.sectionitem>
                <x-slot name="title">
                    Date Received
                </x-slot>
                
                {{$payment->formattedPaymentDate}}
                
            </x-pdf.sectionitem>
            <x-pdf.subsection>
                <x-slot name="left">
                    Total Amount
                </x-slot>
                <x-slot name="right">
                    ${{$payment->proposal->amountPaid}}
                </x-slot>
            </x-pdf.subsection>
        </div>
    </x-pdf.section>
</x-PDF-layout>