{{-- Link einer Aufgabe: im PDF als QR-Code (DomPDF rendert SVG als Bild) --}}
@if(! empty($aufgabe->link))
    @php
        try { $qr = \App\Support\QrCode::dataUri($aufgabe->link, 120); } catch (\Throwable $e) { $qr = null; }
        $kurz = \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $aufgabe->link), 40);
    @endphp
    <div style="margin-top:2px;">
        @if($qr)
            <img src="{{ $qr }}" width="48" height="48" alt="QR-Code" style="vertical-align:middle;">
        @endif
        <span style="font-size:8pt;color:#555;vertical-align:middle;">{{ $kurz }}</span>
    </div>
@endif
