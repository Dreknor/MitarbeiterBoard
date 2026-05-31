<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('procedure_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('procedure_steps', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('done');
            }
            if (!Schema::hasColumn('procedure_steps', 'completed_by')) {
                $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('procedure_steps', 'template_step_id')) {
                $table->unsignedBigInteger('template_step_id')->nullable()->after('procedure_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procedure_steps', function (Blueprint $table) {
            foreach (['completed_at', 'completed_by', 'template_step_id'] as $col) {
                if (Schema::hasColumn('procedure_steps', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

