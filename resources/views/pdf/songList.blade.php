@extends('layouts.PDF', ['bodyStyle' => 'width: 8.5in;', 'contentClasses' => ''])
@section('content')
<div class="px-12 py-10 text-gray-800">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b-2 border-blue-800 pb-6 mb-8">
        <div>
            <div class="text-4xl font-bold text-blue-800">{{ $band->name }}</div>
            <div class="text-2xl font-semibold text-gray-600 mt-1">Song List</div>
            <div class="text-sm text-gray-500 mt-2">
                {{ $songs->count() }} {{ \Illuminate\Support\Str::plural('song', $songs->count()) }}
            </div>
        </div>
        @if ($logoDataUri)
            <img
                src="{{ $logoDataUri }}"
                alt="{{ $band->name }} logo"
                class="max-w-[160px] max-h-[90px]" />
        @endif
    </div>

    {{-- Repertoire --}}
    @if ($songs->isEmpty())
        <div class="text-center text-gray-500 py-16">
            No songs to display yet.
        </div>
    @else
        <div style="column-count: 2; column-gap: 2.5rem;">
            @foreach ($songs as $song)
                <div class="mb-2" style="break-inside: avoid;">
                    <span class="font-semibold">{{ $song->title }}</span>
                    @if ($song->artist)
                        <span class="text-gray-500"> — {{ $song->artist }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="mt-10 pt-4 border-t border-gray-200 text-xs text-gray-400 text-center">
        {{ $band->name }} &middot; Generated {{ $generatedAt->format('F j, Y') }}
    </div>
</div>
@endsection
