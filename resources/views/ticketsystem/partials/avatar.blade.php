{{-- Avatar mit Profilbild oder Initialen; ohne Nutzer = Systemeintrag --}}
@php
    $avatarUrl = $user?->photo();
    $hasPhoto = $avatarUrl && !str_ends_with($avatarUrl, 'img/avatar.png');
    $initials = $user
        ? mb_strtoupper(collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''))
        : '';
@endphp
<span class="tkt-avatar {{ ($small ?? false) ? 'is-sm' : '' }} {{ $user ? '' : 'is-system' }}" title="{{ $user?->name ?? 'System' }}" aria-hidden="true">
    @if($hasPhoto)
        <img src="{{ $avatarUrl }}" alt="" loading="lazy">
    @elseif($user)
        {{ $initials }}
    @else
        <i class="fas fa-cog"></i>
    @endif
</span>
