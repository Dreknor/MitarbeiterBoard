@extends('personal.time_recording.layout')

@section('content')
    <div class="rounded-3xl bg-white/10 px-6 py-8 text-center">
        <p class="text-white/70">Hallo</p>
        <h1 class="text-2xl font-bold mb-2">{{ $user->name }}</h1>
        <p class="mb-6 text-lg" id="pin-hinweis">Bitte PIN eingeben</p>

        @include('personal.time_recording._pinpad', ['felder' => ['secret_key'], 'texte' => ['Bitte PIN eingeben']])

        <form action="{{ route('time_recording.login') }}" method="post" id="pinForm" autocomplete="off">
            @csrf
            <input type="hidden" name="secret_key">
        </form>

        <div class="zw-timer mt-8"><span style="animation-duration: 60s;"></span></div>
        <a href="{{ route('time_recording.logout') }}" class="inline-block mt-6 text-white/80 underline">Abbrechen</a>
    </div>
@endsection
