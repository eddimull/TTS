<x-PDF-layout>
    <x-pdf.header>
        <div class="text-7xl">Receipt</div>
        <div>
            <x-pdf.headersub title="Date">
                {{date('y/m/d')}}
            </x-pdf.headersub>
            <x-pdf.headersub title="Invoice Number">
                1234
            </x-pdf.headersub>
        </div>
        </x-pdf.header>
    <x-pdf.section>
        <x-pdf.sectionheader>
            Client Information
        </x-pdf.sectionheader>
        <div class="px-12 mt-4">

            <x-pdf.sectionitem>
                <x-slot name="title">
                    Full Name
                </x-slot>
                
                Andrus conrad
                
            </x-pdf.sectionitem>
            <x-pdf.sectionitem>
                <x-slot name="title">
                    Phone Number
                </x-slot>
                
                1234567890
                
            </x-pdf.sectionitem>
            <x-pdf.sectionitem>
                <x-slot name="title">
                    Email
                </x-slot>
                
                test@yahoo.com
                
            </x-pdf.sectionitem>
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
                
                $1234
                
            </x-pdf.sectionitem>
            <x-pdf.sectionitem>
                <x-slot name="title">
                    Date Received
                </x-slot>
                
                12/20/2021
                
            </x-pdf.sectionitem>
            <x-pdf.subsection>
                <x-slot name="left">
                    Total Amount
                </x-slot>
                <x-slot name="right">
                    $2234
                </x-slot>
            </x-pdf.subsection>
        </div>
    </x-pdf.section>
</x-PDF-layout>