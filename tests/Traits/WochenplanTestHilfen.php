<?php

namespace Tests\Traits;

use App\Models\Klasse;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Services\Wochenplan\Nextcloud\WpNextcloudSpeicher;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Fakes\WpNextcloudFake;

trait WochenplanTestHilfen
{
    protected ?WpNextcloudFake $nextcloud = null;

    /** Pädagog:in mit Rechten und zugeordneten Klassen (klasse_user). */
    protected function paedagoge(array $klassen = [], array $rechte = ['create wochenplan', 'view wochenplan']): User
    {
        $user = User::factory()->create();
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht);
        }
        $user->givePermissionTo($rechte);
        foreach ($klassen as $klasse) {
            $user->paed_klassen()->attach($klasse->id);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    protected function klassenplan(Klasse $klasse, array $werte = []): WpPlan
    {
        return WpPlan::factory()->create(array_merge([
            'klasse_id' => $klasse->id,
            'gueltig_von' => now()->startOfWeek(),
            'gueltig_bis' => now()->startOfWeek()->addDays(4),
            'formatvorlage_id' => null,
        ], $werte));
    }

    protected function fachMitAufgaben(WpPlan $plan, array $aufgaben, ?string $fachName = null, int $sort = 0): WpPlanFach
    {
        $fach = WpFach::factory()->create(['name' => $fachName ?? 'Fach '.uniqid()]);
        $planFach = WpPlanFach::create(['wp_plan_id' => $plan->id, 'wp_fach_id' => $fach->id, 'sort_order' => $sort]);
        foreach (array_values($aufgaben) as $i => $aufgabe) {
            $daten = is_array($aufgabe) ? $aufgabe : ['aufgabe' => $aufgabe];
            WpAufgabe::create(array_merge(['wp_plan_fach_id' => $planFach->id, 'sort_order' => $i], $daten));
        }

        return $planFach->load('aufgaben');
    }

    protected function einstellung(string $schluessel, string $wert): void
    {
        Setting::updateOrCreate(['setting' => $schluessel], [
            'module' => 'Wochenplan',
            'setting_name' => $schluessel,
            'type' => 'string',
            'value' => $wert,
        ]);
        Cache::forget('setting_'.$schluessel);
    }

    protected function tagebuchAnbindung(bool $aktiv = true): void
    {
        $this->einstellung('wochenplan_tagebuch_anbindung', $aktiv ? '1' : '0');
    }

    protected function nextcloudFake(bool $aktiv = true): WpNextcloudFake
    {
        $this->nextcloud = new WpNextcloudFake();
        $this->nextcloud->aktiv = $aktiv;
        $this->app->instance(WpNextcloudSpeicher::class, $this->nextcloud);

        return $this->nextcloud;
    }
}
