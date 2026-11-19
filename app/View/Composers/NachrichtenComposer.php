<?php

namespace App\View\Composers;

use App\Services\Posts\PostService;
use Illuminate\View\View;

/**
 * Dashboard-Karte „Nachrichten“: Nachrichten mit „auf Dashboard“ aus den eigenen Gruppen,
 * offene eigene Lesebestätigungen angeheftet (PostService::dashboardNachrichten()).
 */
class NachrichtenComposer
{
    public function __construct(private readonly PostService $posts)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();

        $view->with([
            'posts' => $this->posts->dashboardNachrichten($user),
            'nachrichtenZaehler' => $this->posts->zaehler($user),
            'darfPosten' => $this->posts->darfPosten($user),
            'postGruppen' => $this->posts->zielGruppen($user),
        ]);
    }
}
