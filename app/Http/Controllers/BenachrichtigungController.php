<?php

namespace App\Http\Controllers;

use App\Http\Requests\BenachrichtigungEinstellungRequest;
use App\Models\WikiSite;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use App\Services\Benachrichtigungen\TagesvorschauService;
use App\Services\OxCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Glocke, Verlauf, Einstellungen und Tagesübersicht der eigenen Benachrichtigungen.
 * Jede Person sieht ausschließlich ihre eigenen Einträge.
 */
class BenachrichtigungController extends Controller
{
    public function __construct(
        private readonly BenachrichtigungsService $service,
        private readonly TagesvorschauService $tagesvorschau,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $kategorien = $this->service->kategorien();

        $filterKategorie = array_key_exists((string) $request->query('kategorie'), $kategorien)
            ? $request->query('kategorie')
            : null;
        $nurUngelesen = $request->query('status') === 'ungelesen';

        $benachrichtigungen = $user->notifications()
            ->when($filterKategorie, fn ($q) => $q->where('kategorie', $filterKategorie))
            ->when($nurUngelesen, fn ($q) => $q->whereNull('read_at'))
            ->paginate(25)
            ->withQueryString();

        return view('benachrichtigungen.index', [
            'benachrichtigungen' => $benachrichtigungen,
            'kategorien'         => $kategorien,
            'filterKategorie'    => $filterKategorie,
            'nurUngelesen'       => $nurUngelesen,
            'ungelesen'          => $user->unreadNotifications()->count(),
            'wikiUrl'            => $this->wikiUrl(),
        ]);
    }

    /**
     * JSON für das Glocken-Dropdown in der Topbar.
     */
    public function neueste(Request $request): JsonResponse
    {
        $user = $request->user();
        $kategorien = $this->service->kategorien();

        $eintraege = $user->notifications()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $n) => [
                'id'       => $n->id,
                'titel'    => self::titel($n),
                'text'     => self::text($n),
                'icon'     => $kategorien[$n->kategorie]['icon'] ?? 'fa-bell',
                'zeit'     => $n->created_at->diffForHumans(),
                'gelesen'  => $n->read_at !== null,
                'url'      => route('benachrichtigungen.oeffnen', $n->id),
            ]);

        return response()->json([
            'ungelesen' => $user->unreadNotifications()->count(),
            'eintraege' => $eintraege,
        ]);
    }

    public function oeffnen(Request $request, string $id): RedirectResponse
    {
        /** @var DatabaseNotification $benachrichtigung */
        $benachrichtigung = $request->user()->notifications()->findOrFail($id);
        $benachrichtigung->markAsRead();

        $ziel = $benachrichtigung->data['url'] ?? null;

        if (self::istInternesZiel($ziel)) {
            return redirect()->to($ziel);
        }

        return redirect()->route('benachrichtigungen.index');
    }

    /**
     * Nur Ziele auf dieser Anwendung zulassen (keine offene Weiterleitung).
     */
    private static function istInternesZiel(mixed $ziel): bool
    {
        if (!is_string($ziel) || $ziel === '') {
            return false;
        }

        if (str_starts_with($ziel, '/')) {
            return !str_starts_with($ziel, '//') && !str_starts_with($ziel, '/\\');
        }

        $basis = rtrim(url('/'), '/');

        return $ziel === $basis || str_starts_with($ziel, $basis.'/') || str_starts_with($ziel, $basis.'?');
    }

    public function alleGelesen(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirectBack('success', 'Alle Benachrichtigungen wurden als gelesen markiert.');
    }

    public function einstellungen(Request $request, OxCalendarService $kalender): View
    {
        $user = $request->user();
        $einstellung = $this->tagesvorschau->einstellung($user);
        // Eigene Kalenderfarben (Kalender-Modul), sonst Standardfarbe des Kalenders
        $farben = DB::table('user_calendar_colors')->where('user_id', $user->id)->pluck('farbe', 'ox_calendar_id');

        return view('benachrichtigungen.einstellungen', [
            'kategorien'   => $this->service->sichtbareKategorien($user),
            'werte'        => $this->service->alleEinstellungen($user),
            'mailModi'     => config('benachrichtigungen.mail_modi'),
            'einstellung'  => $einstellung,
            'quellen'      => $this->tagesvorschau->sichtbareQuellen($user),
            'kalender'     => $kalender->sichtbareKalender($user)->sortBy('name')->values(),
            'farben'       => $farben,
            'fenster'      => config('benachrichtigungen.tagesvorschau.fenster'),
            'hatPush'      => $user->pushSubscriptions()->exists(),
            'vapidKey'     => config('webpush.vapid.public_key'),
            'wikiUrl'      => $this->wikiUrl(),
        ]);
    }

    public function einstellungenSpeichern(BenachrichtigungEinstellungRequest $request): RedirectResponse
    {
        $user = $request->user();
        $daten = $request->validated();

        $this->service->speichern($user, $daten['kategorien'] ?? []);
        $this->tagesvorschau->speichern($user, $daten['tagesvorschau']);

        return redirect()->route('benachrichtigungen.einstellungen')
            ->with(['type' => 'success', 'Meldung' => 'Einstellungen gespeichert.']);
    }

    /**
     * Tagesübersicht als Seite (Linkziel aus Mail und Push, „Vorschau anzeigen“).
     */
    public function tag(Request $request, ?string $datum = null): View
    {
        $user = $request->user();

        try {
            $tag = $datum ? Carbon::createFromFormat('Y-m-d', $datum)->startOfDay() : today();
        } catch (\Throwable) {
            abort(404);
        }

        return view('benachrichtigungen.tag', [
            'tag'       => $tag,
            'bereiche'  => $this->tagesvorschau->fuer($user, $tag),
            'vorher'    => $tag->copy()->subDay(),
            'nachher'   => $tag->copy()->addDay(),
            'arbeitstag' => $this->tagesvorschau->istArbeitstag($tag),
        ]);
    }

    public static function titel(DatabaseNotification $n): string
    {
        return (string) ($n->data['subject'] ?? $n->data['title'] ?? 'Benachrichtigung');
    }

    public static function text(DatabaseNotification $n): string
    {
        return (string) ($n->data['message'] ?? $n->data['nachricht'] ?? $n->data['subject'] ?? '');
    }

    private function wikiUrl(): ?string
    {
        $seite = WikiSite::where('title', config('benachrichtigungen.wiki_titel'))->first();

        return $seite ? url('wiki/'.$seite->slug) : null;
    }
}
