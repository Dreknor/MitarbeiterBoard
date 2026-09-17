<?php

use App\Models\GradingDocumentationSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grading_documentation_sessions', function (Blueprint $table) {
            $table->string('answer_order_mode')
                ->default(GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT)
                ->after('type');
        });

        DB::table('grading_documentation_sessions')
            ->whereNull('answer_order_mode')
            ->update([
                'answer_order_mode' => GradingDocumentationSession::ANSWER_ORDER_BY_STUDENT,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grading_documentation_sessions', function (Blueprint $table) {
            $table->dropColumn('answer_order_mode');
        });
    }
};

