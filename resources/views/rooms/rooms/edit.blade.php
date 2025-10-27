@extends('layouts.app')
@section('content')
    <a href="{{url('rooms/rooms')}}" class="btn btn-primary btn-link" >zurück</a>

    <div class="card-footer border-top">
            <form action="{{url('rooms/rooms/'.$room->id)}}" method="post" class="form-horizontal">
                @csrf
                @method('put')
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Name
                        <input class="form-control" type="text" name="name" value="{{$room->name}}" required>
                    </label>
                </div>
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Raumnummer
                        <input class="form-control" type="text" name="room_number" value="{{$room->room_number}}">
                    </label>
                </div>
                <div class="form-row mb-2">
                    <label class="label w-100">
                        Indiware Kürzel
                        <input class="form-control" type="text" name="indiware_shortname" value="{{$room->indiware_shortname}}" maxlength="10">
                    </label>
                </div>
                <div class="form-row">
                    <button class="btn btn-success btn-bg-gradient-x-blue-green btn-block" type="submit">
                        Raum speichern
                    </button>
                </div>
            </form>

            {{-- Feed management UI --}}
            <hr />
            <h5>Kalender-Feed</h5>

            @if($room->feed_token)
                <div class="form-group">
                    <label>Feed-URL (öffentlich, token-geschützt)</label>
                    <div class="input-group">
                        <input id="feedUrl" type="text" class="form-control" readonly value="{{$room->feed_url}}">
                        <div class="input-group-append">
                            <button id="copyFeedUrl" class="btn btn-outline-secondary" type="button">Kopieren</button>
                        </div>
                    </div>
                    @if($room->feed_expires_at)
                        <small class="form-text text-muted">Gültig bis: {{$room->feed_expires_at->format('d.m.Y H:i')}}</small>
                    @else
                        <small class="form-text text-muted">Kein Ablaufdatum gesetzt.</small>
                    @endif
                </div>

                <form action="{{ url('rooms/rooms/'.$room->id.'/feed/revoke') }}" method="post" style="display:inline">
                    @csrf
                    <button class="btn btn-danger" type="submit">Feed widerrufen</button>
                </form>

            @else
                <div class="form-group">
                    <p>Aktuell ist kein Feed-Token erstellt.</p>
                </div>
            @endif

            <hr />
            <form action="{{ url('rooms/rooms/'.$room->id.'/feed/generate') }}" method="post" class="form-inline">
                @csrf
                <div class="form-group mb-2">
                    <label for="expires_in_days" class="mr-2">Ablauf in Tagen (optional)</label>
                    <input type="number" min="1" name="expires_in_days" id="expires_in_days" class="form-control mr-2" placeholder="z.B. 90">
                </div>
                <button class="btn btn-primary mb-2" type="submit">Feed generieren / erneuern</button>
            </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            var btn = document.getElementById('copyFeedUrl');
            if (!btn) return;
            btn.addEventListener('click', function(){
                var input = document.getElementById('feedUrl');
                if (!input) return;
                input.select();
                input.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    btn.innerText = 'Kopiert';
                    setTimeout(function(){ btn.innerText = 'Kopieren'; }, 2000);
                } catch(e){
                    alert('Kopieren nicht möglich, bitte manuell markieren und kopieren.');
                }
            });
        });
    </script>
@endsection
