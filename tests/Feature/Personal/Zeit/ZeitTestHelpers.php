<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Gemeinsame Helfer für die Zeitwirtschafts-Tests.
 */
trait ZeitTestHelpers
{
    /**
     * Die Zeit-Services sind "scoped" (Zwischenspeicher pro Request). Im Test läuft
     * die Anwendung über mehrere Requests weiter – daher vor jedem Request leeren.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Neues Arbeitszeitmodell für alle Monate aktivieren (Stichtag weit in der Vergangenheit).
     */
    protected function neuesModell(): void
    {
        $this->settingSetzen('zeitwirtschaft_stichtag', '2020-01-01');
    }

    protected function mitarbeiter(array $rechte = ['has holidays', 'has timesheet'], array $vertrag = []): User
    {
        $user = User::factory()->create();
        $this->rechte($user, ...$rechte);

        Employment::factory()->create(array_merge([
            'employe_id' => $user->id,
            'start' => '2025-01-01',
            'end' => null,
            'hours' => 40,
        ], $vertrag));

        return $user->fresh();
    }

    protected function rechte(User $user, string ...$rechte): User
    {
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht, 'web');
        }
        $user->givePermissionTo($rechte);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    protected function abteilung(): Group
    {
        return Group::factory()->asDepartment()->create();
    }

    protected function settingSetzen(string $key, string $wert): void
    {
        \App\Models\Setting::updateOrCreate(['setting' => $key], ['value' => $wert, 'module' => 'Test', 'setting_name' => $key, 'type' => 'string']);
        \Illuminate\Support\Facades\Cache::forget('setting_'.$key);
    }
}
