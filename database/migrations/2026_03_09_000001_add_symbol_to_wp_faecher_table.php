<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_faecher', function (Blueprint $table) {
            $table->enum('symbol_typ', ['emoji', 'svg', 'bild', 'keine'])
                  ->default('keine')
                  ->after('is_default');
            $table->string('symbol_wert', 500)->nullable()->after('symbol_typ');
            $table->string('symbol_farbe', 7)->nullable()->after('symbol_wert');
        });
    }

    public function down(): void
    {
        Schema::table('wp_faecher', function (Blueprint $table) {
            $table->dropColumn(['symbol_typ', 'symbol_wert', 'symbol_farbe']);
        });
    }
};

