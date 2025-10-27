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
                if (!Schema::hasColumn('rooms', 'feed_token')) {
                    $table->string('feed_token', 64)->nullable()->unique()->after('room_number');
                }
                if (!Schema::hasColumn('rooms', 'feed_expires_at')) {
                    $table->timestamp('feed_expires_at')->nullable()->after('feed_token');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (Schema::hasColumn('rooms', 'feed_expires_at')) {
                    $table->dropColumn('feed_expires_at');
                }
                if (Schema::hasColumn('rooms', 'feed_token')) {
                    $table->dropColumn('feed_token');
                }
            });
        }
    }
};

