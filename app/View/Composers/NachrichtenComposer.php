<?php

namespace App\View\Composers;

use Illuminate\View\View;

class NachrichtenComposer
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
        $view->with('posts', auth()->user()->posts()->orderByDesc('created_at')->paginate(15));
    }
}
