<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'atom_feed_url')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('atom_feed_url', 500)
                    ->nullable()
                    ->default(null)
                    ->after('email')
                    ->comment('Persönliche ATOM-Feed-URL für das Dashboard-Widget');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'atom_feed_url')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('atom_feed_url');
            });
        }
    }
};

