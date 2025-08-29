<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Falls ein vorheriger fehlgeschlagener Lauf Tabellen teilweise angelegt hat, nur fehlende erstellen

        if (!Schema::hasTable('paed_diary_entries')) {
            Schema::create('paed_diary_entries', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('klasse_id');
                $table->unsignedBigInteger('user_id');
                $table->date('datum');
                $table->text('content');
                $table->timestamps();
                $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['klasse_id','datum'],'pd_entries_klasse_datum_idx');
            });
        }

        if (!Schema::hasTable('paed_diary_entry_schueler')) {
            Schema::create('paed_diary_entry_schueler', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('paed_diary_entry_id');
                $table->unsignedBigInteger('schueler_id');
                $table->foreign('paed_diary_entry_id')->references('id')->on('paed_diary_entries')->onDelete('cascade');
                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
                $table->unique(['paed_diary_entry_id','schueler_id'],'pd_entry_schl_uq');
            });
        }

        if (!Schema::hasTable('paed_diary_columns')) {
            Schema::create('paed_diary_columns', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('klasse_id');
                $table->string('name');
                $table->string('slug');
                $table->string('type')->default('text');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
                $table->unique(['klasse_id','slug'],'pd_col_klasse_slug_uq');
            });
        }

        if (!Schema::hasTable('paed_diary_column_values')) {
            Schema::create('paed_diary_column_values', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('paed_diary_column_id');
                $table->unsignedBigInteger('schueler_id');
                $table->date('datum');
                $table->string('value',255)->nullable();
                $table->timestamps();
                $table->foreign('paed_diary_column_id')->references('id')->on('paed_diary_columns')->onDelete('cascade');
                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
                $table->unique(['paed_diary_column_id','schueler_id','datum'],'pd_col_val_uq');
                $table->index(['paed_diary_column_id','datum'],'pd_col_val_col_datum_idx');
            });
        }

        if (!Schema::hasTable('paed_diary_tasks')) {
            Schema::create('paed_diary_tasks', function (Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('klasse_id');
                $table->unsignedBigInteger('schueler_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('open');
                $table->boolean('highlighted')->default(true);
                $table->unsignedBigInteger('created_by');
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->foreign('klasse_id')->references('id')->on('klassen')->onDelete('cascade');
                $table->foreign('schueler_id')->references('id')->on('schueler')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->index(['klasse_id','status'],'pd_tasks_klasse_status_idx');
            });
        }

        if (Schema::hasTable('permissions')) {
            try {
                if (!\DB::table('permissions')->where('name','view paed diary')->exists()) {
                    \DB::table('permissions')->insert([
                        'name' => 'view paed diary',
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_tasks');
        Schema::dropIfExists('paed_diary_column_values');
        Schema::dropIfExists('paed_diary_columns');
        Schema::dropIfExists('paed_diary_entry_schueler');
        Schema::dropIfExists('paed_diary_entries');
    }
};
