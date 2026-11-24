<?php

namespace Tests\Feature\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisBoardZugriff;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gemeinsame Helfer der Ergebnisboard-Tests.
 */
trait ErgebnisboardTestHelfer
{
    protected function nutzer(array $rechte = ['view result boards']): User
    {
        $user = User::factory()->create();
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht);
        }
        $user->givePermissionTo($rechte);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function board(?User $ersteller = null, string $vorlage = 'kanban', array $attribute = []): Ergebnisboard
    {
        $ersteller ??= $this->nutzer(['view result boards', 'create result boards']);

        return Ergebnisboard::factory()->vorlage($vorlage)->create(array_merge(['created_by' => $ersteller->id], $attribute));
    }

    protected function zugriff(Ergebnisboard $board, Model $berechtigter, string $recht): ErgebnisBoardZugriff
    {
        return $board->zugriffe()->create([
            'berechtigter_type' => $berechtigter::class,
            'berechtigter_id' => $berechtigter->getKey(),
            'recht' => $recht,
        ]);
    }

    /** group_user setzen; User::groups() ist 60 s gecacht. */
    protected function inGruppe(User $user, Group $gruppe): void
    {
        $gruppe->users()->syncWithoutDetaching([$user->id]);
        Cache::forget('groups_'.$user->id);
    }
}
