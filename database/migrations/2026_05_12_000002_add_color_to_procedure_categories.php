<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procedure_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('procedure_categories', 'color')) {
                $table->string('color', 16)->nullable()->after('name');
            }
        });

        Schema::table('procedures', function (Blueprint $table) {
            if (!Schema::hasColumn('procedures', 'ended_reason')) {
                $table->string('ended_reason', 255)->nullable()->after('ended_at');
            }
            if (!Schema::hasColumn('procedures', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procedure_categories', function (Blueprint $table) {
            if (Schema::hasColumn('procedure_categories', 'color')) {
                $table->dropColumn('color');
            }
        });
        Schema::table('procedures', function (Blueprint $table) {
            foreach (['ended_reason', 'template_id'] as $col) {
                if (Schema::hasColumn('procedures', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

