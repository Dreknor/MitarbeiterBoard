@extends('personal.time_recording.layout')

@section('content')
    <form action="{{ route('time_recording.read_key') }}" method="post" autocomplete="off" id="chipForm"
          class="rounded-3xl bg-white/10 px-6 py-10 text-center" style="min-height: 50vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1.5rem;">
        @csrf
        <i class="fas fa-id-card" style="font-size: 4rem; opacity: .85;"></i>
        <h1 class="text-2xl font-bold" id="hinweis">Bitte Chip an das Lesegerät halten</h1>
        <p class="text-white/70">Danach die persönliche PIN eingeben.</p>
        <input type="password" id="key_input" name="key" autofocus autocomplete="off" inputmode="none"
               class="w-full max-w-sm rounded-2xl border-0 bg-white/90 px-4 py-3 text-center text-lg text-gray-900"
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
