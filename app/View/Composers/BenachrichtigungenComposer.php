<?php

namespace App\View\Composers;

use Illuminate\View\View;

class BenachrichtigungenComposer
{
    public function compose(View $view): void
    {
        $notifications = auth()->user()
            ->unreadNotifications()
            ->take(10)
            ->get();

        $unreadCount = auth()->user()->unreadNotifications()->count();

        $view->with([
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }
}

