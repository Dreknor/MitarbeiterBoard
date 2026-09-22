<?php

namespace Tests\Feature\Personal;

use App\Models\personal\OrgPosition;
use App\Models\User;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    /** @test */
    public function orgchart_page_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.orgchart.index'))->assertStatus(403);
    }

    /** @test */
    public function orgchart_page_loads_for_authorized_user(): void
    {
        $this->actingAsWithPermission('view orgchart');

        $response = $this->get(route('personal.orgchart.index'));
        $response->assertStatus(200);
        $response->assertViewHas('treeData');
    }

    /** @test */
    public function orgchart_returns_null_tree_when_no_positions(): void
    {
        $this->actingAsWithPermission('view orgchart');

        $response = $this->get(route('personal.orgchart.index'));
        $this->assertNull($response->viewData('treeData'));
    }

    /** @test */
    public function tree_data_contains_positions_and_users(): void
    {
        $this->actingAsWithPermission('view orgchart', 'view personal_data:all');

        $root  = OrgPosition::factory()->create(['parent_position_id' => null, 'name' => 'Schulleitung']);
        $child = OrgPosition::factory()->create(['parent_position_id' => $root->id, 'name' => 'Hortleitung']);
        $user  = User::factory()->create(['name' => 'Max Mustermann']);
        $root->users()->attach($user->id, ['valid_from' => now(), 'is_deputy' => false]);

        $response = $this->get(route('personal.orgchart.index'));
        $treeData = $response->viewData('treeData');

        $this->assertEquals('Schulleitung', $treeData['name']);
        $this->assertCount(1, $treeData['users']);
        $this->assertCount(1, $treeData['children']);
    }

    /** @test */
    public function position_can_have_deputy(): void
    {
        $position = OrgPosition::factory()->create();
        $deputy   = User::factory()->create();
        $position->users()->attach($deputy->id, [
            'valid_from' => now(),
            'is_deputy'  => true,
        ]);

        $this->assertCount(1, $position->currentDeputy);
    }

    /** @test */
    public function manage_orgchart_requires_permission(): void
    {
        $this->actingAsWithPermission('view orgchart');

        $this->get(route('personal.orgchart.positions.index'))->assertStatus(403);
    }
}

