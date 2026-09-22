<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ical_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('url', 2000);
            $table->string('farbe', 7)->default('#6366f1');
            $table->boolean('aktiv')->default(true);
            $table->timestamp('letzter_abruf')->nullable();
            $table->string('fehler_meldung', 500)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ical_feeds');
    }
};

