<x-PDF-layout>
    <x-pdf.header>
        <div class="text-7xl">Receipt</div>
        <div>
            <x-pdf.headersub title="Date">
                {{date('Y-m-d')}}
            </x-pdf.headersub>
            <x-pdf.headersub title="Invoice Number">
                {{$booking->id}}
            </x-pdf.headersub>
        </div>
    </x-pdf.header>
    <x-pdf.section>
        <x-pdf.sectionheader>
            Contract Information - {{$booking->name}}
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">
            <div class="flex flex-row justify-evenly">
                <div>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Provider
                        </x-slot>
                        {{$booking->band->name}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Date
                        </x-slot>
                        {{$booking->date}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Venue
                        </x-slot>
                        {{$booking->venue_name}}
                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Price
                        </x-slot>
                        ${{$booking->price}}
                    </x-pdf.sectionitem>
                </div>
                <div>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Point of Contact
                        </x-slot>
                        <div>
                            {{$booking->author->name}}
                        </div>
                        <div>{{$booking->author->email}}</div>
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

                @foreach($booking->contacts as $contact)
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
            Payments
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">
            <ol class="list-decimal list-outside marker:text-xl">
                @foreach($booking->payments as $payment)
                <li class="pl-2 mb-4">
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Amount Received
                        </x-slot>

                        ${{$payment->amount}}

                    </x-pdf.sectionitem>
                    <x-pdf.sectionitem>
                        <x-slot name="title">
                            Date Received
                        </x-slot>

                        {{$payment->date}}

                    </x-pdf.sectionitem>
                </li>
                @endforeach
                <ol>
                    <x-pdf.subsection>
                        <x-slot name="left">
                            Amount Left
                        </x-slot>
                        <x-slot name="right">
                            @if($booking->amountLeft === '0.00')
                            <span class="text-green-400">
                                ${{$booking->amountLeft}}
                            </span>
                            @else
                            <span class="text-green-600">
                                ${{$booking->amountLeft}}
                            </span>
                            @endif
                        </x-slot>
                    </x-pdf.subsection>
        </div>
    </x-pdf.section>
</x-PDF-layout>