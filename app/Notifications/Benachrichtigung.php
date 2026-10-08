<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Basis aller Benachrichtigungen im MitarbeiterBoard.
 *
 * - Jede Benachrichtigung gehört zu einer Kategorie aus config/benachrichtigungen.php.
 * - Die Kanäle bestimmt die Person selbst (BenachrichtigungsService::kanaeleFuer):
 *   Glocke (Datenbank) immer, Push und Mail nach Einstellung.
 * - Unterklassen liefern nur Titel, Text und URL. Wer ein bestehendes Mail-Template
 *   behalten will, überschreibt mailable().
 */
abstract class Benachrichtigung extends Notification implements ShouldQueue
{
    use Queueable;

    /** Kategorie-Schlüssel aus config/benachrichtigungen.php */
    abstract public function kategorie(): string;

    /** Kurzer Titel (Push-Titel, Mail-Betreff) */
    abstract public function titel(object $notifiable): string;

    /** Einzeiliger Text (Glocke, Push-Text) */
    abstract public function text(object $notifiable): string;

    /** Ziel beim Anklicken */
    public function url(object $notifiable): ?string
    {
        return null;
    }

    /**
     * Zeilen der Standard-Mail (Standard: der Text).
     *
     * @return array<int, string>
     */
    public function zeilen(object $notifiable): array
    {
        return [$this->text($notifiable)];
    }

    public function aktionText(): string
    {
        return 'Im MitarbeiterBoard öffnen';
    }

    /**
     * Bestehendes Mail-Template statt der Standard-Mail verwenden.
     */
    public function mailable(object $notifiable): ?Mailable
    {
        return null;
    }

    /**
     * Zusätzliche Daten für den Datenbankeintrag.
     */
    public function zusatzdaten(object $notifiable): array
    {
        return [];
    }

    /**
     * Keine Mail an Personen, die heute abwesend sind oder Urlaub haben
     * (außer sie haben „Mails auch bei Abwesenheit“ aktiviert). Glocke und Push bleiben.
     */
    protected bool $keineMailBeiAbwesenheit = false;

    public function via(object $notifiable): array
    {
        if (!$notifiable instanceof User) {
            return ['mail'];
        }

        $kanaele = app(BenachrichtigungsService::class)->kanaeleFuer($notifiable, $this->kategorie());

        if ($this->keineMailBeiAbwesenheit && in_array('mail', $kanaele, true) && $this->istAbwesend($notifiable)) {
            $kanaele = array_values(array_diff($kanaele, ['mail']));
        }

        return $kanaele;
    }

    protected function istAbwesend(User $user): bool
    {
        if ($user->send_mails_if_absence) {
            return false;
        }

        return $user->hasAbsence(now()) || $user->hasHoliday(now());
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->zusatzdaten($notifiable), [
            'kategorie' => $this->kategorie(),
            'subject'   => $this->titel($notifiable),
            'message'   => $this->text($notifiable),
            'url'       => $this->url($notifiable),
        ]);
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->titel($notifiable))
            ->body($this->text($notifiable))
            ->icon(asset('img/'.config('config.logo_small')))
            ->tag($this->kategorie())
            ->data(['url' => $this->url($notifiable) ?? url('/')]);
    }

    public function toMail(object $notifiable): MailMessage|Mailable
    {
        $mailable = $this->mailable($notifiable);

        if ($mailable !== null) {
            return $mailable->to($notifiable->email ?? $notifiable->routeNotificationFor('mail'));
        }

        $mail = (new MailMessage)
            ->subject($this->titel($notifiable))
            ->greeting('Hallo '.($notifiable->vorname ?? $notifiable->name ?? '').',');

        foreach ($this->zeilen($notifiable) as $zeile) {
            $mail->line($zeile);
        }

        if ($url = $this->url($notifiable)) {
            $mail->action($this->aktionText(), $url);
        }

        return $mail->line('Ihre Benachrichtigungen können Sie unter „Benachrichtigungen → Einstellungen“ anpassen.');
    }
}
