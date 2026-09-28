<?php

namespace App\Services\Tickets;

use App\Mail\newTicketCommentMail;
use App\Mail\newTicketMail;
use App\Mail\TicketAssignmentMail;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\Push;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

/**
 * Zentrale Logik des Ticketsystems: Anlegen, Kommentieren, Statuswechsel,
 * Zuweisung sowie alle Benachrichtigungen (Mail + Push).
 *
 * Benachrichtigungsregeln:
 * - Neues Ticket → alle Bearbeiter (edit tickets)
 * - Öffentlicher Kommentar eines Bearbeiters → Ersteller
 * - Kommentar des Erstellers → zugewiesene Person, sonst alle Bearbeiter
 * - Interner Kommentar → nur zugewiesene Person (nie der Ersteller)
 * - Schließen/Wiedereröffnen → jeweils die "andere Seite"
 * Der Auslöser selbst wird nie benachrichtigt.
 */
class TicketService
{
    public const CATEGORY_CACHE_KEY = 'ticket_categories';

    /**
     * @param  UploadedFile[]  $files
     */
    public function create(User $author, array $data, array $files = []): Ticket
    {
        $ticket = DB::transaction(function () use ($author, $data) {
            $ticket = new Ticket([
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'medium',
                'category_id' => $data['category_id'] ?? null,
            ]);
            $ticket->user_id = $author->id;
            $ticket->status = Ticket::STATUS_OPEN;
            $ticket->save();

            return $ticket;
        });

        $this->attachFiles($ticket, $files, 'ticket_files');

        $ticket->load('user', 'category');

        $this->notify(
            $this->editors(),
            $author,
            fn () => new newTicketMail($ticket),
            'Neues Ticket',
            'Ein neues Ticket wurde erstellt: '.$ticket->title,
        );

        return $ticket;
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function addComment(
        Ticket $ticket,
        User $author,
        string $text,
        bool $internal = false,
        ?Carbon $waitingUntil = null,
        array $files = [],
    ): TicketComment {
        $canManage = $author->can('manage', $ticket);
        $internal = $internal && $canManage;
        $waitingUntil = $canManage ? $waitingUntil : null;
        $isOwner = (int) $author->id === (int) $ticket->user_id;

        $comment = DB::transaction(function () use ($ticket, $author, $text, $internal, $waitingUntil, $isOwner) {
            $comment = $ticket->comments()->create([
                'comment' => $text,
                'internal' => $internal,
                'user_id' => $author->id,
            ]);

            if ($waitingUntil !== null) {
                $ticket->status = Ticket::STATUS_WAITING;
                $ticket->waiting_until = $waitingUntil->copy()->endOfDay();
                $ticket->save();

                $this->systemComment($ticket, $author,
                    'Ticket wartet auf Rückmeldung bis '.$ticket->waiting_until->format('d.m.Y').'.');
            } elseif ($ticket->isWaiting() && $isOwner) {
                $ticket->status = Ticket::STATUS_OPEN;
                $ticket->waiting_until = null;
                $ticket->save();

                $this->systemComment($ticket, $author, 'Rückmeldung erhalten – Ticket ist wieder offen.');
            } else {
                $ticket->touch();
            }

            return $comment;
        });

        $this->attachFiles($comment, $files, 'comment_files');

        $ticket->loadMissing('user', 'assigned');
        $comment->setRelation('user', $author);

        if ($internal) {
            $recipients = collect([$ticket->assigned]);
        } elseif ($isOwner) {
            $recipients = $ticket->assigned ? collect([$ticket->assigned]) : $this->editors();
        } else {
            $recipients = collect([$ticket->user]);
            // Kommentiert ein anderer Bearbeiter, soll die zugewiesene Person es mitbekommen
            if ($ticket->assigned) {
                $recipients->push($ticket->assigned);
            }
        }

        $this->notify(
            $recipients,
            $author,
            fn () => new newTicketCommentMail($comment, $ticket),
            'Neuer Kommentar',
            'Neuer Kommentar zu Ticket: '.$ticket->title,
        );

        return $comment;
    }

    public function assign(Ticket $ticket, ?User $assignee, User $by): Ticket
    {
        $previous = $ticket->assigned;

        if ((int) $previous?->id === (int) $assignee?->id) {
            return $ticket;
        }

        $ticket->assigned_to = $assignee?->id;
        $ticket->save();
        $ticket->setRelation('assigned', $assignee);

        $text = match (true) {
            $assignee === null => 'Zuweisung an '.$previous->name.' aufgehoben.',
            $previous !== null => 'Ticket von '.$previous->name.' an '.$assignee->name.' übertragen.',
            default => 'Ticket zugewiesen an '.$assignee->name.'.',
        };
        $this->systemComment($ticket, $by, $text);

        if ($assignee !== null) {
            $ticket->loadMissing('user', 'category');
            $this->notify(
                collect([$assignee]),
                $by,
                fn () => new TicketAssignmentMail($ticket),
                'Ticket zugewiesen',
                'Dir wurde das Ticket "'.$ticket->title.'" zugewiesen.',
            );
        }

        if ($previous !== null) {
            $this->notify(
                collect([$previous]),
                $by,
                null,
                'Ticket neu zugewiesen',
                $assignee
                    ? 'Das Ticket "'.$ticket->title.'" wurde an '.$assignee->name.' übertragen.'
                    : 'Deine Zuweisung zum Ticket "'.$ticket->title.'" wurde aufgehoben.',
            );
        }

        return $ticket;
    }

    /**
     * Kategorie und/oder Priorität ändern; Änderungen werden im Verlauf protokolliert.
     */
    public function updateDetails(Ticket $ticket, array $data, User $by): Ticket
    {
        $changes = [];

        if (array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority) {
            $old = $ticket->priority_label;
            $ticket->priority = $data['priority'];
            $changes[] = 'Priorität von "'.$old.'" auf "'.$ticket->priority_label.'" geändert';
        }

        if (array_key_exists('category_id', $data) && (int) $data['category_id'] !== (int) $ticket->category_id) {
            $old = $ticket->category?->name ?? 'keine';
            $ticket->category_id = $data['category_id'] ?: null;
            $ticket->load('category');
            $changes[] = 'Kategorie von "'.$old.'" auf "'.($ticket->category?->name ?? 'keine').'" geändert';
        }

        if (array_key_exists('title', $data) && filled($data['title']) && $data['title'] !== $ticket->title) {
            $ticket->title = $data['title'];
            $changes[] = 'Titel geändert';
        }

        if ($changes !== []) {
            $ticket->save();
            $this->systemComment($ticket, $by, implode(', ', $changes).'.');
        }

        return $ticket;
    }

    public function close(Ticket $ticket, ?User $by, ?string $reason = null): Ticket
    {
        if ($ticket->isClosed()) {
            return $ticket;
        }

        $comment = DB::transaction(function () use ($ticket, $by, $reason) {
            $ticket->status = Ticket::STATUS_CLOSED;
            $ticket->waiting_until = null;
            $ticket->closed_at = now();
            $ticket->closed_by = $by?->id;
            $ticket->save();

            $text = $reason ?: 'Ticket geschlossen.';

            return $this->systemComment($ticket, $by, $text);
        });

        $ticket->loadMissing('user', 'assigned');
        $byOwner = $by !== null && (int) $by->id === (int) $ticket->user_id;

        $this->notify(
            $byOwner ? collect([$ticket->assigned]) : collect([$ticket->user]),
            $by,
            fn () => new newTicketCommentMail($comment, $ticket),
            'Ticket geschlossen',
            'Das Ticket "'.$ticket->title.'" wurde geschlossen.',
        );

        return $ticket;
    }

    public function reopen(Ticket $ticket, User $by): Ticket
    {
        if (!$ticket->isClosed()) {
            return $ticket;
        }

        $comment = DB::transaction(function () use ($ticket, $by) {
            $ticket->status = Ticket::STATUS_OPEN;
            $ticket->closed_at = null;
            $ticket->closed_by = null;
            $ticket->save();

            return $this->systemComment($ticket, $by, 'Ticket wieder geöffnet.');
        });

        $ticket->loadMissing('user', 'assigned');
        $byOwner = (int) $by->id === (int) $ticket->user_id;

        $recipients = $byOwner
            ? ($ticket->assigned ? collect([$ticket->assigned]) : $this->editors())
            : collect([$ticket->user]);

        $this->notify(
            $recipients,
            $by,
            fn () => new newTicketCommentMail($comment, $ticket),
            'Ticket wieder geöffnet',
            'Das Ticket "'.$ticket->title.'" wurde wieder geöffnet.',
        );

        return $ticket;
    }

    /**
     * @return bool true, wenn das Ticket danach angepinnt ist
     */
    public function togglePin(Ticket $ticket, User $user): bool
    {
        $result = $user->pinned_tickets()->toggle($ticket->id);

        return in_array($ticket->id, $result['attached']);
    }

    /**
     * Schließt wartende Tickets, deren Wartezeit seit der eingestellten Anzahl
     * an Tagen abgelaufen ist (Scheduler, täglich).
     */
    public function closeExpiredWaiting(): int
    {
        if (!filter_var(settings('ticket_closed_automatic'), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }

        $days = (int) (settings('ticket_closed_automatic_days') ?? 7);

        $tickets = Ticket::query()
            ->where('status', Ticket::STATUS_WAITING)
            ->whereNotNull('waiting_until')
            ->where('waiting_until', '<', now()->subDays(max($days, 0)))
            ->get();

        $closed = 0;
        foreach ($tickets as $ticket) {
            try {
                $this->close($ticket, null, 'Das Ticket wurde automatisch geschlossen, da keine Rückmeldung erfolgte.');
                $closed++;

                Log::info('Ticketsystem: Ticket wurde automatisch geschlossen', ['ticket' => $ticket->id]);
            } catch (\Throwable $e) {
                Log::error('Ticketsystem: Ticket konnte nicht automatisch geschlossen werden', [
                    'ticket' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closed;
    }

    /**
     * Alle Nutzer mit der Berechtigung "edit tickets" (direkt oder über Rollen).
     */
    public function editors(): Collection
    {
        if (!Permission::where('name', 'edit tickets')->exists()) {
            return collect();
        }

        return User::permission('edit tickets')->orderBy('name')->get();
    }

    public function categories(): Collection
    {
        return Cache::remember(self::CATEGORY_CACHE_KEY, 3600, fn () => TicketCategory::orderBy('name')->get());
    }

    public function forgetCategoryCache(): void
    {
        Cache::forget(self::CATEGORY_CACHE_KEY);
    }

    private function systemComment(Ticket $ticket, ?User $by, string $text): TicketComment
    {
        return $ticket->comments()->create([
            'comment' => e($text),
            'internal' => false,
            'user_id' => $by?->id,
        ]);
    }

    /**
     * @param  UploadedFile[]  $files
     */
    private function attachFiles($model, array $files, string $collection): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            try {
                $model->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->toMediaCollection($collection);
            } catch (\Throwable $e) {
                Log::error('Ticketsystem: Datei konnte nicht gespeichert werden', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Versendet Mail (optional) und Push an alle Empfänger außer dem Auslöser.
     * Fehler beim Versand werden geloggt, brechen aber nie die eigentliche Aktion ab.
     */
    private function notify(Collection $recipients, ?User $actor, ?callable $mailFactory, string $pushTitle, string $pushBody): void
    {
        $recipients = $recipients
            ->filter()
            ->reject(fn (User $user) => $actor !== null && (int) $user->id === (int) $actor->id)
            ->reject(fn (User $user) => $user->trashed())
            ->unique('id');

        foreach ($recipients as $user) {
            if ($mailFactory !== null && filled($user->email)) {
                try {
                    /** @var Mailable $mail */
                    $mail = $mailFactory();
                    Mail::to($user->email)->queue($mail);
                } catch (\Throwable $e) {
                    Log::error('Ticketsystem: Mail konnte nicht versendet werden', [
                        'user' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $user->notify(new Push($pushTitle, $pushBody));
            } catch (\Throwable $e) {
                Log::warning('Ticketsystem: Push-Benachrichtigung fehlgeschlagen', [
                    'user' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
