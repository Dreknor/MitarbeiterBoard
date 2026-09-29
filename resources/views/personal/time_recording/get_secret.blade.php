@extends('personal.time_recording.layout')

@section('content')
    <div class="rounded-3xl bg-white/10 px-4 py-5 text-center sm:px-6 sm:py-7">
        <p class="zeit-terminal-subline text-sm text-white/70 sm:text-base">Hallo</p>
        <h1 class="mb-1 text-lg font-bold sm:mb-2 sm:text-xl">{{ $user->name }}</h1>
        <p class="mb-3 text-sm sm:mb-4 sm:text-base" id="pin-hinweis">Bitte PIN eingeben</p>

        @include('personal.time_recording._pinpad', ['felder' => ['secret_key'], 'texte' => ['Bitte PIN eingeben']])

        <form action="{{ route('time_recording.login') }}" method="post" id="pinForm" autocomplete="off">
            @csrf
            <input type="hidden" name="secret_key">
        </form>

        <div class="zw-timer mt-4 sm:mt-6"><span style="animation-duration: 60s;"></span></div>
        <a href="{{ route('time_recording.logout') }}" class="zeit-terminal-subline mt-3 inline-block text-xs text-white/80 underline sm:mt-4 sm:text-sm">Abbrechen</a>
    </div>
@endsection
