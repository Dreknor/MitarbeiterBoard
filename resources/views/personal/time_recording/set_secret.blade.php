@extends('personal.time_recording.layout')

@section('content')
    <div class="rounded-3xl bg-white/10 px-6 py-8 text-center">
        <p class="text-white/70">Willkommen</p>
        <h1 class="text-2xl font-bold mb-2">{{ $user->name }}</h1>
        <p class="text-white/80 mb-1">Für deinen Chip ist noch keine PIN hinterlegt.</p>
        <p class="mb-6 text-lg font-semibold" id="pin-hinweis">Neue PIN (6–10 Ziffern)</p>

        @include('personal.time_recording._pinpad', [
            'felder' => ['secret_key', 'secret_key_confirmation'],
            'texte' => ['Neue PIN (6–10 Ziffern)', 'PIN zur Bestätigung wiederholen'],
        ])

        <form action="{{ route('time_recording.storeSecret') }}" method="post" id="pinForm" autocomplete="off">
            @csrf
            <input type="hidden" name="secret_key">
            <input type="hidden" name="secret_key_confirmation">
        </form>

        <div class="zw-timer mt-8"><span style="animation-duration: 60s;"></span></div>
        <a href="{{ route('time_recording.logout') }}" class="inline-block mt-6 text-white/80 underline">Abbrechen</a>
    </div>
@endsection
