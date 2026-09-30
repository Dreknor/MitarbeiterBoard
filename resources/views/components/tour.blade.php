{{--
    Geführte Tour – wiederverwendbar in allen Bereichen (Logik: resources/js/tour.js)

    <x-tour id="urlaub" :steps="[['target' => 'urlaub-antrag', 'title' => '…', 'text' => '…'], …]" />
    Start-Knopf beliebig gestalten:  <button type="button" data-tour-start="urlaub">Tour</button>

    Props:
      id       eindeutiger Name der Tour
      steps    Schritte: target (data-tour="…") oder selector, activate, title, text
      next     optional ['label' => …, 'url' => …] – Knopf zur nächsten Seite im letzten Schritt
      version  erhöhen, damit eine geänderte Tour allen erneut automatisch angezeigt wird
      auto     false = nur über den Start-Knopf bzw. ?tour=<id>
--}}
@props(['id', 'steps' => [], 'next' => null, 'version' => 1, 'auto' => true])

<script type="application/json" data-tour-config>{!! json_encode([
    'id' => $id,
    'user' => auth()->id(),
    'version' => $version,
    'auto' => $auto,
    'next' => $next,
    'steps' => array_values(array_filter($steps)),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

@once
    @push('js')
        @vite('resources/js/tour.js')
    @endpush
@endonce
