<?php

namespace Database\Factories\personal;

use App\Enums\DocumentStatus;
use App\Enums\SyncStatus;
use App\Models\personal\DocumentType;
use App\Models\personal\PersonalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalDocumentFactory extends Factory
{
    protected $model = PersonalDocument::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'employe_id'       => User::factory(),
            'document_type_id' => DocumentType::factory(),
            'title'            => $this->faker->sentence(3),
            'nextcloud_path'   => '/Personal/Allgemein/Angestellt/Test_Nutzer_1/' . $this->faker->slug . '.pdf',
            'nextcloud_file_id'=> null,
            'issue_date'       => $this->faker->dateTimeBetween('-2 years', 'now'),
            'expiry_date'      => $this->faker->optional()->dateTimeBetween('+1 month', '+5 years'),
            'reminder_days'    => 30,
            'reminder_sent_at' => null,
            'status'           => DocumentStatus::Aktuell,
            'sync_status'      => SyncStatus::Synced,
            'notes'            => null,
            'uploaded_by'      => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['sync_status' => SyncStatus::Pending]);
    }

    public function syncFehler(): static
    {
        return $this->state(['sync_status' => SyncStatus::SyncFehler]);
    }

    public function expiringSoon(): static
    {
        return $this->state(['expiry_date' => now()->addDays(20)]);
    }
}

