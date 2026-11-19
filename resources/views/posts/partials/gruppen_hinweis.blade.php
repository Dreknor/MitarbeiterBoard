{{--
    Themen-Seite einer Gruppe: neueste ungelesene bzw. unbestätigte Nachrichten (max. 3).
    Erwartet $group.
--}}
@php
    $nachrichtenOffen = app(\App\Services\Posts\PostService::class)->offeneFuerGruppe($group, auth()->user());
@endphp
@if($nachrichtenOffen->isNotEmpty())
    @once
        @push('css')
            @vite('resources/css/posts.css')
        @endpush
    @endonce
    <div class="nachr-wrapper nachr-eingebettet mb-6">
        <section class="nachr-card" data-tour="nachrichten-hinweis">
            <div class="nachr-card-head">
                <h2 class="nachr-card-title"><i class="fas fa-newspaper"></i> Neue Nachrichten</h2>
                <a href="{{ route('groups.posts', $group->name) }}" class="nachr-btn nachr-btn-ghost nachr-btn-sm">
                    Alle Nachrichten <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="nachr-list">
                @foreach($nachrichtenOffen as $post)
                    @include('posts.partials.eintrag', ['post' => $post, 'zurueck' => url()->current()])
                @endforeach
            </div>
        </section>
    </div>
@endif
