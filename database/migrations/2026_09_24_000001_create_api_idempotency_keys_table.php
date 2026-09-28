<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API v1 (Pädagogen-App): Idempotency-Keys für schreibende Aufrufe.
 * Gespeichert werden nur erfolgreiche Antworten (2xx); Bereinigung nach 48 h
 * über "php artisan paed-app:prune-idempotency" (Scheduler).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('method', 10);
            $table->string('path', 255);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            // JSON-Antwort als Text: MySQL-JSON-Spalten würden Schlüssel umsortieren,
            // die Wiederholung soll aber byte-identisch sein.
            $table->longText('response_body')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
