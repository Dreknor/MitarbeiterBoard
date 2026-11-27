<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\Posts\PostService;

/**
 * Zugriff auf Nachrichten.
 *
 * - Lesen: Verfassende, "manage posts", Ersteller:innen einer Zielgruppe und alle,
 *   die eine Zielgruppe sehen (veröffentlichte Nachrichten).
 * - Bearbeiten/Veröffentlichen/Archivieren: Verfassende und "manage posts".
 *   Löschen nur, solange die Nachricht nie veröffentlicht war.
 * - Bestätigungsliste: Verfassende, "manage posts", Ersteller:innen einer Zielgruppe.
 * - Bestätigen: Empfänger:innen (Mitglieder einer Zielgruppe, nicht die verfassende Person).
 */
class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        if ($this->verwaltet($user, $post) || $this->istGruppenErsteller($user, $post)) {
            return true;
        }

        if (! $post->released) {
            return false;
        }

        $sichtbar = $user->groups()->pluck('id');

        return $post->groups->pluck('id')->intersect($sichtbar)->isNotEmpty();
    }

    public function update(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post);
    }

    public function release(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post) && ! $post->released;
    }

    public function archive(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post) && $post->released && ! $post->archived;
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post) && $post->archived;
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post) && ! $post->warVeroeffentlicht();
    }

    public function viewConfirmations(User $user, Post $post): bool
    {
        return $post->bestaetigung_erforderlich
            && ($this->verwaltet($user, $post) || $this->istGruppenErsteller($user, $post));
    }

    public function remind(User $user, Post $post): bool
    {
        return $this->verwaltet($user, $post)
            && $post->bestaetigung_erforderlich
            && $post->released
            && ! $post->archived;
    }

    public function confirm(User $user, Post $post): bool
    {
        return $post->bestaetigung_erforderlich
            && $post->released
            && ! $post->archived
            && app(PostService::class)->istEmpfaenger($post, $user);
    }

    private function verwaltet(User $user, Post $post): bool
    {
        return $post->author_id === $user->id || $user->can('manage posts');
    }

    private function istGruppenErsteller(User $user, Post $post): bool
    {
        return $post->groups->contains(fn ($gruppe) => (int) $gruppe->creator_id === (int) $user->id);
    }
}
