<?php

namespace Tests\Unit\Factories;

use App\Models\Klasse;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\PaedDiaryTask;
use App\Models\User;
use Tests\TestCase;

class PaedDiaryFactoriesTest extends TestCase
{
    /** @test */
    public function PaedDiaryCategoryFactory_erstellt_globale_kategorie_per_default(): void
    {
        $cat = PaedDiaryCategory::factory()->create();
        $this->assertNull($cat->user_id);
        $this->assertNotEmpty($cat->name);
    }

    /** @test */
    public function PaedDiaryCategoryFactory_ownedBy_erstellt_persoenliche_kategorie(): void
    {
        $user = User::factory()->create();
        $cat  = PaedDiaryCategory::factory()->ownedBy($user)->create();
        $this->assertEquals($user->id, $cat->user_id);
    }

    /** @test */
    public function PaedDiaryEntryFactory_erstellt_entry_mit_beziehungen(): void
    {
        $entry = PaedDiaryEntry::factory()->create();
        $this->assertNotNull($entry->klasse);
        $this->assertNotNull($entry->user);
        $this->assertFalse($entry->dossier_only);
        $this->assertNull($entry->completed_at);
    }

    /** @test */
    public function PaedDiaryEntryFactory_completed_state(): void
    {
        $entry = PaedDiaryEntry::factory()->completed()->create();
        $this->assertNotNull($entry->completed_at);
    }

    /** @test */
    public function PaedDiaryEntryFactory_dossierOnly_state(): void
    {
        $entry = PaedDiaryEntry::factory()->dossierOnly()->create();
        $this->assertTrue($entry->dossier_only);
    }

    /** @test */
    public function PaedDiaryEntryFactory_withCategory_state(): void
    {
        $cat   = PaedDiaryCategory::factory()->create();
        $entry = PaedDiaryEntry::factory()->withCategory($cat)->create();
        $this->assertEquals($cat->id, $entry->category_id);
    }

    /** @test */
    public function PaedDiaryColumnFactory_erstellt_spalte(): void
    {
        $col = PaedDiaryColumn::factory()->create();
        $this->assertNotNull($col->klasse_id);
        $this->assertNotEmpty($col->name);
        $this->assertNotEmpty($col->slug);
    }

    /** @test */
    public function PaedDiaryTaskFactory_erstellt_aufgabe(): void
    {
        $task = PaedDiaryTask::factory()->create();
        $this->assertNotNull($task->klasse_id);
        $this->assertEquals('open', $task->status);
    }

    /** @test */
    public function PaedDiaryTaskFactory_closed_state(): void
    {
        $task = PaedDiaryTask::factory()->closed()->create();
        $this->assertEquals('closed', $task->status);
        $this->assertNotNull($task->closed_at);
    }

    /** @test */
    public function PaedDiaryAppointmentFactory_erstellt_termin(): void
    {
        $termin = PaedDiaryAppointment::factory()->create();
        $this->assertNotNull($termin->user_id);
        $this->assertFalse($termin->is_recurring);
    }

    /** @test */
    public function PaedDiaryAppointmentFactory_recurring_state(): void
    {
        $termin = PaedDiaryAppointment::factory()->recurring()->create();
        $this->assertTrue($termin->is_recurring);
        $this->assertEquals('weekly', $termin->recurring_type);
    }

    /** @test */
    public function PaedDiarySchuelerAbsenceFactory_erstellt_abwesenheit(): void
    {
        $absence = PaedDiarySchuelerAbsence::factory()->create();
        $this->assertNotNull($absence->schueler);
        $this->assertNotNull($absence->klasse);
        $this->assertNotNull($absence->markedByUser);
    }
}

