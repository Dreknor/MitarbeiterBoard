<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('paed_diary_columns') && !Schema::hasColumn('paed_diary_columns','deactivated_from')) {
            Schema::table('paed_diary_columns', function(Blueprint $table){
                $table->date('deactivated_from')->nullable()->after('active')->index();
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('paed_diary_columns') && Schema::hasColumn('paed_diary_columns','deactivated_from')) {
            Schema::table('paed_diary_columns', function(Blueprint $table){
                $table->dropColumn('deactivated_from');
            });
        }
    }
};

