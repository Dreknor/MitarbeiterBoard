<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ox_calendars
        Schema::create('ox_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('ox_calendar_id');
            $table->string('name');
            $table->string('farbe', 7)->default('#3B82F6');
            $table->text('beschreibung')->nullable();
            $table->boolean('sichtbar')->default(true);
            $table->boolean('schreibbar')->default(false);
            $table->text('sync_token')->nullable();
            $table->timestamp('letzte_synchronisation')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. ox_termine
        Schema::create('ox_termine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ox_calendar_id')->constrained('ox_calendars')->cascadeOnDelete();
            $table->string('ox_uid');
            $table->string('ox_etag')->nullable();
            $table->string('ox_href')->nullable();
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->string('ort')->nullable();
            $table->dateTime('beginn');
            $table->dateTime('ende');
            $table->string('timezone')->nullable()->default('Europe/Berlin');
            $table->boolean('ganztaegig')->default(false);
            $table->text('rrule')->nullable();
            $table->json('exdates')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->text('raw_ical')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['beginn', 'ende']);
            $table->index(['ox_calendar_id', 'beginn']);
            $table->unique(['ox_calendar_id', 'ox_uid']);
        });

        // 3. ox_termin_teilnehmer
        Schema::create('ox_termin_teilnehmer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ox_termin_id')->constrained('ox_termine')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('NEEDS-ACTION');
            $table->timestamps();

            $table->index('ox_termin_id');
        });

        // 4. ox_calendar_group (Pivot)
        Schema::create('ox_calendar_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ox_calendar_id')->constrained('ox_calendars')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->boolean('schreibbar')->default(false);
            $table->timestamps();

            $table->unique(['ox_calendar_id', 'group_id']);
        });

        // 5. ox_sync_log (Audit – kein SoftDeletes!)
        Schema::create('ox_sync_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ox_calendar_id')->nullable()->constrained('ox_calendars')->nullOnDelete();
            $table->string('aktion');
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_adresse')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ox_sync_log');
        Schema::dropIfExists('ox_calendar_group');
        Schema::dropIfExists('ox_termin_teilnehmer');
        Schema::dropIfExists('ox_termine');
        Schema::dropIfExists('ox_calendars');
    }
};

