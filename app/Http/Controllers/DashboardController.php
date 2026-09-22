<?php

namespace App\Http\Controllers;

use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\UserQuicklink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use League\CommonMark\CommonMarkConverter;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function up(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->row = $dashBoardUser->row - 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function down(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->row = $dashBoardUser->row + 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function left(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->col = $dashBoardUser->col - 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function right(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->col = $dashBoardUser->col + 1;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function toggle(DashBoardUser $dashBoardUser)
    {
        $dashBoardUser->active = !$dashBoardUser->active;
        $dashBoardUser->save();

        return redirect()->back();
    }

    public function disableCard(Request $request)
    {
        $card = DashBoardUser::where('id', $request->id)->first();
        if ($card and $card->user_id == auth()->id()) {
            $card->active = false;
            $card->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * PUT /dashboard/layout
     * Layout (Order, Width, Active) für alle Cards des Auth-Users speichern.
     */
    public function updateLayout(Request $request)
    {
        $validated = $request->validate([
            'cards'          => ['required', 'array'],
            'cards.*.id'     => ['required', 'integer'],
            'cards.*.order'  => ['required', 'integer', 'min:0'],
            'cards.*.width'  => ['required', 'string', 'in:sm,md,lg,full'],
            'cards.*.active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['cards'] as $cardData) {
                // Nur eigene Cards aktualisieren
                DashBoardUser::where('id', $cardData['id'])
                    ->where('user_id', auth()->id())
                    ->update([
                        'order'  => $cardData['order'],
                        'width'  => $cardData['width'],
                        'active' => $cardData['active'],
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * POST /dashboard/layout/reset
     * Alle Dashboard-Cards des Auth-Users löschen (werden beim nächsten Aufruf neu angelegt).
     */
    public function resetLayout()
    {
        DashBoardUser::where('user_id', auth()->id())->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /dashboard/card/{dashBoardUser}
     * Eine einzelne Card als HTML laden (Lazy-Loading).
     */
    public function loadCard(DashBoardUser $dashBoardUser)
    {
        abort_unless($dashBoardUser->user_id === auth()->id(), 403);

        $dashBoardUser->load('dashboardCard');
        $view = $dashBoardUser->dashboardCard->view;

        // v2-Variante bevorzugen wenn User Permission hat und View existiert
        if (auth()->user()->can('use dashboard v2')) {
            $v2View = $view . '-v2';
            if (view()->exists($v2View)) {
                $view = $v2View;
            }
        }

        return view($view, ['card' => $dashBoardUser]);
    }

    /**
     * GET /dashboard/hilfe
     * Hilfe-Seite für das Dashboard.
     */
    public function hilfe()
    {
        $html = Cache::remember('dashboard.hilfe.html', now()->addHours(24), function () {
            $path = base_path('docs/anleitung-dashboard.md');
            if (!file_exists($path)) {
                return '<p>Hilfe-Datei nicht gefunden.</p>';
            }
            $markdown = file_get_contents($path);
            $converter = new CommonMarkConverter([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);
            return $converter->convert($markdown)->getContent();
        });

        return view('dashboard.hilfe', compact('html'));
    }

    /**
     * POST /dashboard/quicklinks
     * Neuen Schnellzugriff-Link anlegen.
     */
    public function storeQuicklink(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url'   => ['required', 'string', 'max:500'],
            'icon'  => ['nullable', 'string', 'max:50'],
        ]);

        UserQuicklink::create([
            'user_id' => auth()->id(),
            'label'   => $validated['label'],
            'url'     => $validated['url'],
            'icon'    => $validated['icon'] ?? 'fas fa-link',
            'order'   => UserQuicklink::where('user_id', auth()->id())->max('order') + 1,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /dashboard/quicklinks/{quicklink}
     * Schnellzugriff-Link löschen.
     */
    public function destroyQuicklink(UserQuicklink $quicklink)
    {
        abort_unless($quicklink->user_id === auth()->id(), 403);
        $quicklink->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /notifications/mark-all-read
     * Alle Benachrichtigungen des Auth-Users als gelesen markieren.
     */
    public function markNotificationsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}

