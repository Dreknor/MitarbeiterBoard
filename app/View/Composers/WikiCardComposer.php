<?php

namespace App\View\Composers;

use App\Models\WikiSite;
use Illuminate\View\View;

class WikiCardComposer
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
        $view->with('sites', WikiSite::query()->orderByDesc('updated_at')->limit(6)->get());
    }
}
