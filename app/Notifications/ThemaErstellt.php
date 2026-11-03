<?php

namespace App\Notifications;

use App\Mail\NewThemeMail;
use App\Models\Theme;
use Illuminate\Mail\Mailable;

/**
 * Neues Thema in einer abonnierten Gruppe.
 */
class ThemaErstellt extends Benachrichtigung
{
    public function __construct(public Theme $theme)
    {
    }

    public function kategorie(): string
    {
        return 'themen';
    }

    public function titel(object $notifiable): string
    {
        return 'Neues Thema in '.($this->theme->group?->name ?? 'einer Gruppe');
    }

    public function text(object $notifiable): string
    {
        return (string) $this->theme->theme;
    }

    public function url(object $notifiable): ?string
    {
        return $this->theme->url();
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return $this->theme->group
            ? new NewThemeMail($this->theme->theme, $this->theme->id, $this->theme->group->name)
            : null;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['theme_id' => $this->theme->id];
    }
}
