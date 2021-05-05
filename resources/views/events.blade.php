<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    {{$event}}
                    <div class="text-center underline">
                        {{$event->event_type_name->name}}
                    </div>
                    <div class="text-center text-xl italic">
                        <h2>{{$event->band->name}} Advance</h2>
                    </div>
                    <div class="bg-white w-full rounded-lg shadow-xl">
                            <div>
                                <div class="md:grid md:grid-cols-2 hover:bg-gray-50 md:space-y-0 space-y-1 p-4 border-b">
                                    <p class="text-gray-600">
                                        <label for="name">Name</label>
                                    </p>
                                    <div class="mb-4">
                                        <p type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name">{{$event->event_name}}</p>
                                    </div>
                                </div>
                                
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
