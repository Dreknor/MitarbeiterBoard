<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_procedures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('procedure_id');
            $table->smallInteger('month', false, true)->nullable();
            $table->ENUM('faelligkeit_typ', ['datum', 'vor_ferien', 'nach_ferien']);
            $table->integer('wochen')->nullable();
            $table->enum('ferien', ['Sommerferien', 'Herbstferien', 'Weihnachtsferien', 'Winterferien', 'Osterferien'])->nullable();
            $table->timestamps();

            $table->foreign('procedure_id')->references('id')->on('procedures');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_procedures');
    }
};
