<?php

namespace App\Notifications;

use App\Mail\newThemeAssignMail;
use App\Models\Theme;
use Illuminate\Mail\Mailable;

/**
 * Ein Thema wurde der Person zugewiesen.
 */
class ThemaZugewiesen extends Benachrichtigung
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
        return 'Thema zugewiesen';
    }

    public function text(object $notifiable): string
    {
        return 'Ihnen wurde das Thema „'.$this->theme->theme.'“ zugewiesen.';
    }

    public function url(object $notifiable): ?string
    {
        return $this->theme->url();
    }

    public function mailable(object $notifiable): ?Mailable
    {
        return $this->theme->group ? new newThemeAssignMail($this->theme, $notifiable) : null;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return ['theme_id' => $this->theme->id];
    }
}
