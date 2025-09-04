<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletedAtToPaedDiaryEntries extends Migration
{
    public function up()
    {
        Schema::table('paed_diary_entries', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('content');
        });
    }

    public function down()
    {
        Schema::table('paed_diary_entries', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
}

