<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitspaket 3.2: Kennzeichnung von Monatsabschlüssen, die aufgrund
 * rückwirkender Vertragsänderungen erneut geprüft werden müssen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->boolean('requires_review')->default(false)->after('locked_by');
            $table->string('review_reason')->nullable()->after('requires_review');
            $table->timestamp('reviewed_at')->nullable()->after('review_reason');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index('requires_review', 'timesheets_requires_review_idx');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex('timesheets_requires_review_idx');
            $table->dropColumn(['requires_review', 'review_reason', 'reviewed_at', 'reviewed_by']);
        });
    }
};

