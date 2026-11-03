<?php

namespace App\View\Composers;

use App\Models\personal\Holiday;
use App\Models\personal\TimesheetDays;
use Carbon\Carbon;
use Illuminate\View\View;

class UrlaubCardComposer
{
    /**
     *
     */
    public function __construct()
    {

    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();
        $unapproved = collect();

        if ($user->can('approve holidays')) {
            // Nur Anträge, über die der Benutzer entscheiden darf (Vorgesetztenkette bzw. "approve all holidays")
            $zugriff = app(\App\Services\Personal\Zeit\ZeitZugriff::class);
            $unapproved = Holiday::query()->offen()
                ->with('employe')
                ->where('employe_id', '!=', $user->id)
                ->orderBy('start_date')
                ->get()
                ->filter(fn (Holiday $h) => $h->employe !== null && $zugriff->darfUrlaubGenehmigen($user, $h->employe))
                ->values();
        }

        $view->with([
            'unapproved' => $unapproved,
            'holidays' => Holiday::where('employe_id', $user->id)
                ->whereDate('end_date', '>=', Carbon::today())
                ->orderBy('start_date', 'asc')
                ->get(),
        ]);
    }
}
