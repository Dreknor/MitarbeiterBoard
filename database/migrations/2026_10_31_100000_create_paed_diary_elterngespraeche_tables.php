<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elterngespräche in der Schülerakte des Pädagogischen Tagebuchs: Planung, Vorbereitung,
 * Protokoll und Vereinbarungen. Inhalte werden verschlüsselt gespeichert (Model-Casts),
 * daher text-Spalten. Dazu Einstellungen für den Briefkopf des Protokoll-PDFs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paed_diary_elterngespraeche', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schueler_id');
            $table->unsignedBigInteger('klasse_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('paed_diary_appointment_id')->nullable();
            $table->date('datum');
            $table->time('beginn')->nullable();
            $table->time('ende')->nullable();
            $table->string('ort')->nullable();
            $table->string('form', 20)->default('vor_ort');
            $table->string('status', 20)->default('geplant');
            $table->boolean('vertraulich')->default(false);
            $table->boolean('schueler_anwesend')->default(false);
            $table->text('eltern_teilnehmende')->nullable();
            $table->text('weitere_teilnehmende')->nullable();
            $table->text('anlass')->nullable();
            $table->longText('gespraechspunkte')->nullable();
            $table->text('vorbereitung_notizen')->nullable();
            $table->boolean('vorbereitung_im_protokoll')->default(false);
            $table->longText('verlauf')->nullable();
            $table->date('naechster_termin')->nullable();
            $table->timestamp('abgeschlossen_at')->nullable();
            $table->unsignedBigInteger('abgeschlossen_von')->nullable();
            $table->timestamp('abgesagt_at')->nullable();
            $table->unsignedBigInteger('aktualisiert_von')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
            $table->foreign('klasse_id')->references('id')->on('klassen')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('paed_diary_appointment_id', 'pd_eg_appointment_fk')->references('id')->on('paed_diary_appointments')->nullOnDelete();
            $table->foreign('abgeschlossen_von')->references('id')->on('users')->nullOnDelete();
            $table->foreign('aktualisiert_von')->references('id')->on('users')->nullOnDelete();
            $table->index(['schueler_id', 'datum'], 'pd_eg_schueler_datum_idx');
        });

        Schema::create('paed_diary_elterngespraech_vereinbarungen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('elterngespraech_id');
            $table->text('text');
            $table->string('verantwortlich', 20)->nullable();
            $table->date('bis')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('paed_diary_task_id')->nullable();
            $table->unsignedBigInteger('paed_diary_goal_id')->nullable();
            $table->timestamps();

            $table->foreign('elterngespraech_id', 'pd_egv_gespraech_fk')->references('id')->on('paed_diary_elterngespraeche')->onDelete('cascade');
            $table->foreign('paed_diary_task_id', 'pd_egv_task_fk')->references('id')->on('paed_diary_tasks')->nullOnDelete();
            $table->foreign('paed_diary_goal_id', 'pd_egv_goal_fk')->references('id')->on('paed_diary_goals')->nullOnDelete();
        });

        Schema::create('paed_diary_elterngespraech_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('elterngespraech_id');
            $table->unsignedBigInteger('user_id');

            $table->foreign('elterngespraech_id', 'pd_egu_gespraech_fk')->references('id')->on('paed_diary_elterngespraeche')->onDelete('cascade');
            $table->foreign('user_id', 'pd_egu_user_fk')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['elterngespraech_id', 'user_id'], 'pd_egu_unique');
        });

        $settings = [
            [
                'setting' => 'school_address',
                'setting_name' => 'Anschrift der Schule (Briefkopf)',
                'description' => 'Erscheint im Briefkopf der Protokolle von Elterngesprächen. Zeilen mit „|“ trennen, z. B. „Musterstraße 1 | 01445 Radebeul“.',
            ],
            [
                'setting' => 'school_contact',
                'setting_name' => 'Kontaktzeile der Schule (Briefkopf)',
                'description' => 'Telefon, E-Mail und Web für den Briefkopf, z. B. „Tel. 0351 123456 · info@schule.de · www.schule.de“.',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['setting' => $setting['setting']],
                [
                    'module' => 'paed_diary',
                    'setting_name' => $setting['setting_name'],
                    'type' => 'text',
                    'value' => '',
                    'description' => $setting['description'],
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_elterngespraech_user');
        Schema::dropIfExists('paed_diary_elterngespraech_vereinbarungen');
        Schema::dropIfExists('paed_diary_elterngespraeche');
        Setting::whereIn('setting', ['school_address', 'school_contact'])->delete();
    }
};
