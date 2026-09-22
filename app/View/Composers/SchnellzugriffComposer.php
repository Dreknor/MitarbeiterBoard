<?php

namespace App\View\Composers;

use App\Models\UserQuicklink;
use Illuminate\View\View;

class SchnellzugriffComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user) {
            $view->with('quicklinks', collect());
            return;
        }

        $quicklinks = UserQuicklink::where('user_id', $user->id)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $view->with('quicklinks', $quicklinks);
    }
}

