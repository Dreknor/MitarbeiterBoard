<?php

namespace App\Events\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use Illuminate\Foundation\Events\Dispatchable;

/** Ein Kinderplan wurde aus einem Klassenplan erstellt ({@see \App\Services\Wochenplan\WochenplanService::kinderplanErstellen()}). */
class WpKinderplanAngelegt
{
    use Dispatchable;

    public function __construct(public WpPlan $kinderplan)
    {
    }
}
