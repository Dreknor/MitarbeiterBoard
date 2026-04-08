<?php

namespace Tests\Unit\Enums;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\TerminationReason;
use App\Enums\DocumentStatus;
use App\Enums\SyncStatus;
use App\Enums\QualificationStatus;
use App\Enums\QualificationCategory;
use App\Enums\TrainingStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ReviewStatus;
use App\Enums\BemStatus;
use App\Enums\ChangeRequestStatus;
use App\Enums\RetentionStatus;
use App\Enums\ProcedureLinkType;
use App\Enums\ProcedureLinkStatus;
use App\Enums\TeacherQualificationLevel;
use Tests\TestCase;

class AllEnumsTest extends TestCase
{
    // --- EmploymentStatus ---

    /** @test */
    public function employment_status_has_all_cases(): void
    {
        $values = array_column(EmploymentStatus::cases(), 'value');
        $this->assertEquals(['aktiv', 'ruhend', 'beendet'], $values);
    }

    /** @test */
    public function employment_status_labels(): void
    {
        $this->assertEquals('Aktiv', EmploymentStatus::Aktiv->label());
        $this->assertEquals('Ruhend', EmploymentStatus::Ruhend->label());
        $this->assertEquals('Beendet', EmploymentStatus::Beendet->label());
    }

    /** @test */
    public function employment_status_is_active(): void
    {
        $this->assertTrue(EmploymentStatus::Aktiv->isActive());
        $this->assertFalse(EmploymentStatus::Ruhend->isActive());
        $this->assertFalse(EmploymentStatus::Beendet->isActive());
    }

    /** @test */
    public function employment_status_from_value(): void
    {
        $this->assertEquals(EmploymentStatus::Aktiv, EmploymentStatus::from('aktiv'));
    }

    /** @test */
    public function employment_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        EmploymentStatus::from('ungueltig');
    }

    // --- EmploymentStatusReason ---

    /** @test */
    public function employment_status_reason_has_all_cases(): void
    {
        $values = array_column(EmploymentStatusReason::cases(), 'value');
        $this->assertEquals(['elternzeit', 'mutterschutz', 'sabbatical', 'langzeitkrank', 'sonstig'], $values);
    }

    /** @test */
    public function employment_status_reason_labels(): void
    {
        $this->assertEquals('Elternzeit', EmploymentStatusReason::Elternzeit->label());
        $this->assertEquals('Mutterschutz', EmploymentStatusReason::Mutterschutz->label());
    }

    /** @test */
    public function employment_status_reason_from_value(): void
    {
        $this->assertEquals(EmploymentStatusReason::Elternzeit, EmploymentStatusReason::from('elternzeit'));
    }

    /** @test */
    public function employment_status_reason_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        EmploymentStatusReason::from('ungueltig');
    }

    // --- TerminationReason ---

    /** @test */
    public function termination_reason_has_all_cases(): void
    {
        $values = array_column(TerminationReason::cases(), 'value');
        $this->assertCount(6, $values);
        $this->assertContains('kuendigung_an', $values);
        $this->assertContains('kuendigung_ag', $values);
        $this->assertContains('aufhebung', $values);
        $this->assertContains('befristungsablauf', $values);
        $this->assertContains('verrentung', $values);
        $this->assertContains('sonstig', $values);
    }

    /** @test */
    public function termination_reason_labels(): void
    {
        $this->assertEquals('Kündigung (Arbeitnehmer)', TerminationReason::KuendigungAN->label());
        $this->assertEquals('Kündigung (Arbeitgeber)', TerminationReason::KuendigungAG->label());
    }

    /** @test */
    public function termination_reason_from_value(): void
    {
        $this->assertEquals(TerminationReason::Aufhebung, TerminationReason::from('aufhebung'));
    }

    /** @test */
    public function termination_reason_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        TerminationReason::from('ungueltig');
    }

    // --- DocumentStatus ---

    /** @test */
    public function document_status_has_all_cases(): void
    {
        $values = array_column(DocumentStatus::cases(), 'value');
        $this->assertEquals(['aktuell', 'abgelaufen', 'archiviert'], $values);
    }

    /** @test */
    public function document_status_labels(): void
    {
        $this->assertEquals('Aktuell', DocumentStatus::Aktuell->label());
    }

    /** @test */
    public function document_status_badge_colors(): void
    {
        $this->assertEquals('green', DocumentStatus::Aktuell->badgeColor());
        $this->assertEquals('red', DocumentStatus::Abgelaufen->badgeColor());
        $this->assertEquals('gray', DocumentStatus::Archiviert->badgeColor());
    }

    /** @test */
    public function document_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        DocumentStatus::from('ungueltig');
    }

    // --- SyncStatus ---

    /** @test */
    public function sync_status_has_all_cases(): void
    {
        $values = array_column(SyncStatus::cases(), 'value');
        $this->assertEquals(['synced', 'uploading', 'sync_fehler', 'pending'], $values);
    }

    /** @test */
    public function sync_status_labels(): void
    {
        $this->assertEquals('Synchronisiert', SyncStatus::Synced->label());
        $this->assertEquals('Sync-Fehler', SyncStatus::SyncFehler->label());
    }

    /** @test */
    public function sync_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        SyncStatus::from('ungueltig');
    }

    // --- QualificationStatus ---

    /** @test */
    public function qualification_status_has_all_cases(): void
    {
        $values = array_column(QualificationStatus::cases(), 'value');
        $this->assertEquals(['gueltig', 'ablaufend', 'abgelaufen', 'fehlend'], $values);
    }

    /** @test */
    public function qualification_status_badge_colors(): void
    {
        $this->assertEquals('green', QualificationStatus::Gueltig->badgeColor());
        $this->assertEquals('yellow', QualificationStatus::Ablaufend->badgeColor());
        $this->assertEquals('red', QualificationStatus::Abgelaufen->badgeColor());
        $this->assertEquals('gray', QualificationStatus::Fehlend->badgeColor());
    }

    /** @test */
    public function qualification_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        QualificationStatus::from('ungueltig');
    }

    // --- QualificationCategory ---

    /** @test */
    public function qualification_category_has_all_cases(): void
    {
        $values = array_column(QualificationCategory::cases(), 'value');
        $this->assertEquals(['pflicht', 'empfohlen', 'freiwillig'], $values);
    }

    /** @test */
    public function qualification_category_labels(): void
    {
        $this->assertEquals('Pflicht', QualificationCategory::Pflicht->label());
    }

    /** @test */
    public function qualification_category_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        QualificationCategory::from('ungueltig');
    }

    // --- TrainingStatus ---

    /** @test */
    public function training_status_has_all_cases(): void
    {
        $values = array_column(TrainingStatus::cases(), 'value');
        $this->assertEquals(['geplant', 'bestaetigt', 'durchgefuehrt', 'abgesagt'], $values);
    }

    /** @test */
    public function training_status_labels(): void
    {
        $this->assertEquals('Geplant', TrainingStatus::Geplant->label());
        $this->assertEquals('Bestätigt', TrainingStatus::Bestaetigt->label());
        $this->assertEquals('Durchgeführt', TrainingStatus::Durchgefuehrt->label());
    }

    /** @test */
    public function training_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        TrainingStatus::from('ungueltig');
    }

    // --- ParticipantStatus ---

    /** @test */
    public function participant_status_has_all_cases(): void
    {
        $values = array_column(ParticipantStatus::cases(), 'value');
        $this->assertCount(5, $values);
        $this->assertContains('nicht_erschienen', $values);
    }

    /** @test */
    public function participant_status_labels(): void
    {
        $this->assertEquals('Nicht erschienen', ParticipantStatus::NichtErschienen->label());
    }

    /** @test */
    public function participant_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        ParticipantStatus::from('ungueltig');
    }

    // --- ReviewStatus ---

    /** @test */
    public function review_status_has_all_cases(): void
    {
        $values = array_column(ReviewStatus::cases(), 'value');
        $this->assertEquals(['geplant', 'durchgefuehrt', 'verschoben', 'abgesagt'], $values);
    }

    /** @test */
    public function review_status_labels(): void
    {
        $this->assertEquals('Verschoben', ReviewStatus::Verschoben->label());
    }

    /** @test */
    public function review_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        ReviewStatus::from('ungueltig');
    }

    // --- BemStatus ---

    /** @test */
    public function bem_status_has_all_cases(): void
    {
        $values = array_column(BemStatus::cases(), 'value');
        $this->assertCount(7, $values);
        $this->assertContains('erkannt', $values);
        $this->assertContains('abgelehnt_durch_ma', $values);
    }

    /** @test */
    public function bem_status_labels(): void
    {
        $this->assertEquals('Erkannt', BemStatus::Erkannt->label());
        $this->assertEquals('Abgelehnt durch Mitarbeiter', BemStatus::AbgelehntDurchMa->label());
    }

    /** @test */
    public function bem_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        BemStatus::from('ungueltig');
    }

    // --- ChangeRequestStatus ---

    /** @test */
    public function change_request_status_has_all_cases(): void
    {
        $values = array_column(ChangeRequestStatus::cases(), 'value');
        $this->assertEquals(['beantragt', 'genehmigt', 'abgelehnt'], $values);
    }

    /** @test */
    public function change_request_status_labels(): void
    {
        $this->assertEquals('Beantragt', ChangeRequestStatus::Beantragt->label());
    }

    /** @test */
    public function change_request_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        ChangeRequestStatus::from('ungueltig');
    }

    // --- RetentionStatus ---

    /** @test */
    public function retention_status_has_all_cases(): void
    {
        $values = array_column(RetentionStatus::cases(), 'value');
        $this->assertEquals(['ausstehend', 'erinnert', 'geprueft', 'behalten', 'geloescht'], $values);
    }

    /** @test */
    public function retention_status_labels(): void
    {
        $this->assertEquals('Geprüft', RetentionStatus::Geprueft->label());
    }

    /** @test */
    public function retention_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        RetentionStatus::from('ungueltig');
    }

    // --- ProcedureLinkType ---

    /** @test */
    public function procedure_link_type_has_all_cases(): void
    {
        $values = array_column(ProcedureLinkType::cases(), 'value');
        $this->assertEquals(['onboarding', 'offboarding', 'versetzung'], $values);
    }

    /** @test */
    public function procedure_link_type_labels(): void
    {
        $this->assertEquals('Onboarding', ProcedureLinkType::Onboarding->label());
    }

    /** @test */
    public function procedure_link_type_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        ProcedureLinkType::from('ungueltig');
    }

    // --- ProcedureLinkStatus ---

    /** @test */
    public function procedure_link_status_has_all_cases(): void
    {
        $values = array_column(ProcedureLinkStatus::cases(), 'value');
        $this->assertEquals(['aktiv', 'abgeschlossen', 'abgebrochen'], $values);
    }

    /** @test */
    public function procedure_link_status_labels(): void
    {
        $this->assertEquals('Abgeschlossen', ProcedureLinkStatus::Abgeschlossen->label());
    }

    /** @test */
    public function procedure_link_status_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        ProcedureLinkStatus::from('ungueltig');
    }

    // --- TeacherQualificationLevel ---

    /** @test */
    public function teacher_qualification_level_has_all_cases(): void
    {
        $values = array_column(TeacherQualificationLevel::cases(), 'value');
        $this->assertEquals(['fakultas', 'fachfremd_qualifiziert', 'seiteneinsteiger', 'keine'], $values);
    }

    /** @test */
    public function teacher_qualification_level_labels(): void
    {
        $this->assertEquals('Volle Lehrbefähigung (Fakultas)', TeacherQualificationLevel::Fakultas->label());
        $this->assertEquals('Fachfremd (qualifiziert)', TeacherQualificationLevel::FachfremdQualifiziert->label());
        $this->assertEquals('Seiteneinsteiger', TeacherQualificationLevel::Seiteneinsteiger->label());
        $this->assertEquals('Keine Qualifizierung', TeacherQualificationLevel::Keine->label());
    }

    /** @test */
    public function teacher_qualification_level_throws_on_invalid(): void
    {
        $this->expectException(\ValueError::class);
        TeacherQualificationLevel::from('ungueltig');
    }
}

