<?php

namespace Tests\Feature;

use App\Models\Klasse;
use Tests\TestCase;

class KlasseControllerTest extends TestCase
{
    private function buildUpdatePayload(Klasse $klasse, array $overrides = []): array
    {
        return array_merge([
            'name'    => $klasse->name,
            'kuerzel' => $klasse->kuerzel,
            'color'   => $klasse->color, // NOT NULL in DB
        ], $overrides);
    }

    // ─── Test: User ohne Permission kann show_vertretungen nicht ändern ───────

    public function test_user_without_permission_cannot_change_show_vertretungen(): void
    {
        $klasse = Klasse::factory()->create(['show_vertretungen' => true]);
        $this->actingAsWithPermission('edit klassen');

        $payload = $this->buildUpdatePayload($klasse, ['show_vertretungen' => '0']);
        $this->put(route('klassen.update', $klasse), $payload);

        $klasse->refresh();
        $this->assertTrue((bool) $klasse->show_vertretungen, 'User ohne Permission darf show_vertretungen nicht ändern');
    }

    // ─── Test: User mit Permission kann show_vertretungen ändern ─────────────

    public function test_user_with_permission_can_change_show_vertretungen(): void
    {
        $klasse = Klasse::factory()->create(['show_vertretungen' => true]);
        $this->actingAsWithPermission('edit klassen', 'edit klassen vertretungen');

        $payload = $this->buildUpdatePayload($klasse, ['show_vertretungen' => '0']);
        $response = $this->put(route('klassen.update', $klasse), $payload);

        $response->assertRedirect();
        $rawValue = \Illuminate\Support\Facades\DB::table('klassen')->where('id', $klasse->id)->value('show_vertretungen');
        $this->assertEquals(0, (int) $rawValue, 'User mit Permission: show_vertretungen muss auf 0 gesetzt worden sein');
    }
}


