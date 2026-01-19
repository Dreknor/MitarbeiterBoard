<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'show_column_categories')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('show_column_categories')->default(false)->after('email')->comment('Show column categories in week view of pedagogical diary');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'show_column_categories')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('show_column_categories');
            });
        }
    }
};

