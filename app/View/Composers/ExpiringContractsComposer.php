<?php

namespace App\View\Composers;

use App\Models\personal\Employment;
use Illuminate\View\View;

class ExpiringContractsComposer
{
    public function compose(View $view): void
    {
        if (!auth()->check() || !auth()->user()->can('view contracts')) {
            $view->with('expiringContracts', collect());
            return;
        }

        $expiringContracts = Employment::active()
            ->whereNotNull('end')
            ->where('end', '<=', now()->addMonths(3))
            ->with(['employe:id,name', 'department:id,name'])
            ->orderBy('end')
            ->get();

        $view->with('expiringContracts', $expiringContracts);
    }
}

