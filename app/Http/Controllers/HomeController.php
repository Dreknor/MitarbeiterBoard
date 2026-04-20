<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\DashboardCard;
use App\Models\DashBoardUser;
use App\Models\personal\Roster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;


class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index(){

        $defaultCards = \Cache::remember('dashboard_cards', 60*60*24, function (){
            return DashboardCard::all();
        });

        // ─── Dashboard v2 – Permission-Weiche ──────────────────────────────
        if (auth()->user()->can('use dashboard v2')) {
            $cards = auth()->user()->dashboardCards()->get();
            $cards->load('dashboardCard');

            // Falls User noch keine Cards hat (erster Aufruf): Defaults anlegen
            if ($cards->isEmpty()) {
                foreach ($defaultCards as $card) {
                    if ($card->permission === null || auth()->user()->can($card->permission)) {
                        DashBoardUser::insert([
                            'dashboard_card_id' => $card->id,
                            'user_id'           => auth()->id(),
                            'row'               => $card->default_row,
                            'col'               => $card->default_col,
                            'order'             => ($card->default_row * 10) + $card->default_col,
                            'width'             => $card->default_width ?? 'md',
                            'active'            => true,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                }
                $cards = auth()->user()->dashboardCards()->with('dashboardCard')->get();
            }

            $cards = $cards->sortBy('order');

            // JSON-Daten für Alpine.js aufbereiten
            $cardsJson = $cards->map(fn($c) => [
                'id'       => $c->id,
                'order'    => $c->order,
                'width'    => $c->width ?? 'md',
                'active'   => (bool) $c->active,
                'title'    => $c->dashboardCard->title ?? '',
                'skeleton' => $c->dashboardCard->skeleton ?? 'default',
                'icon'     => $c->dashboardCard->icon ?? 'fas fa-th',
            ])->values()->toArray();

            return view('dashboard.dashboard-v2', [
                'cards'     => $cards,
                'cardsJson' => $cardsJson,
            ]);
        }
        // ─── Ende Dashboard v2 ──────────────────────────────────────────────



        $cards = auth()->user()->dashboardCards;

        foreach ($defaultCards as $card){

            if ($cards->where('dashboard_card_id', $card->id)->count() < 1 and ($card->permission == null or auth()->user()->can($card->permission))){

                if ($cards->where('row', $card->default_row)->where('col', $card->default_col)->count() == 0){
                    $row = $card->default_row;
                    $col = $card->default_col;
                } else {
                    $row = $cards->last()->row + 1;
                    $col = $cards->last()->col + 1;
                }

                Log::info('DashboardCard: neue Karte hinzugefügt', [
                    'card' => $card,
                    'user' => auth()->user(),
                    'row' => $row,
                    'col' => $col
                ]);

                DashBoardUser::insert([
                    'dashboard_card_id' => $card->id,
                    'user_id' => auth()->id(),
                    'row' => $card->default_row,
                    'col' => $card->default_col,
                    'active' => true
                ]);
            }
        }

        $cards = auth()->user()->dashboardCards->filter(function ($value, $key) {
            return $value->active;
        })->sortBy('col')->sortBy('row');
        $cards->load('dashboardCard');

        return view('dashboard.dashboard', [
            'cards' => $cards
                ]);
    }

}
