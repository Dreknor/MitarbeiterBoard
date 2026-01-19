<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('invitation_sent_at')->nullable();
            $table->unsignedBigInteger('invitation_sent_by')->nullable();
            $table->foreign('invitation_sent_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['invitation_sent_by']);
            $table->dropColumn(['invitation_sent_at', 'invitation_sent_by']);
        });
    }
};

