<?php

namespace App\View\Composers;

use App\Models\DailyNews;
use Illuminate\View\View;

class TagesinfosComposer
{
    public function compose(View $view): void
    {
        $tagesinfos = DailyNews::where(function ($q) {
            $q->whereDate('date_start', '<=', today())
              ->where(function ($q2) {
                  $q2->whereNull('date_end')
                     ->orWhereDate('date_end', '>=', today());
              });
        })->orderByDesc('date_start')->get();

        $view->with('tagesinfos', $tagesinfos);
    }
}

