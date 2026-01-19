<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!Schema::hasColumn('rooms', 'bookable')) {
                    $table->boolean('bookable')->default(true)->after('feed_expires_at');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (Schema::hasColumn('rooms', 'bookable')) {
                    $table->dropColumn('bookable');
                }
            });
        }
    }
};
