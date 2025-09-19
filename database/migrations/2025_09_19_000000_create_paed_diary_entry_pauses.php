<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasTable('paed_diary_entry_pauses')){
            Schema::create('paed_diary_entry_pauses', function(Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('paed_diary_entry_id');
                $table->unsignedBigInteger('schueler_id');
                $table->date('date');
                $table->timestamps();
                $table->unique(['paed_diary_entry_id','schueler_id','date'],'entry_schueler_date_unique');
                $table->foreign('paed_diary_entry_id')->references('id')->on('paed_diary_entries')->onDelete('cascade');
                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('paed_diary_entry_pauses');
    }
};

