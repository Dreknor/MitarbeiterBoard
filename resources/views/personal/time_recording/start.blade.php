@extends('personal.time_recording.layout')

@section('content')
    <form action="{{ route('time_recording.read_key') }}" method="post" autocomplete="off" id="chipForm"
          class="rounded-3xl bg-white/10 px-4 py-5 text-center sm:px-6 sm:py-7" style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.75rem; width:100%;">
        @csrf
        <i class="fas fa-id-card text-4xl opacity-[.85] sm:text-5xl"></i>
        <h1 class="text-lg font-bold sm:text-xl" id="hinweis">Bitte Chip an das Lesegerät halten</h1>
        <p class="zeit-terminal-subline text-sm text-white/70 sm:text-base">Danach die persönliche PIN eingeben.</p>
        <input type="password" id="key_input" name="key" autofocus autocomplete="off" inputmode="none"
               class="w-full max-w-sm rounded-2xl border-0 bg-white/90 px-4 py-2 text-center text-base text-gray-900 sm:py-2.5 sm:text-lg"
               placeholder="Chip scannen" aria-label="Chip-Nummer">
    </form>
@endsection

@push('js')
    <script>
        (function () {
            const eingabe = document.getElementById('key_input');
            // Fokus halten – das Lesegerät tippt die Nummer wie eine Tastatur
            setInterval(() => { if (document.activeElement !== eingabe) eingabe.focus(); }, 1500);
            document.getElementById('chipForm').addEventListener('submit', () => {
                eingabe.style.visibility = 'hidden';
                document.getElementById('hinweis').textContent = 'Bitte warten …';
            });
        })();
    </script>
@endpush
