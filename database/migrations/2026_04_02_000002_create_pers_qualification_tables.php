<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pers_qualification_types')) {
            Schema::create('pers_qualification_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category');
                $table->integer('validity_months')->nullable();
                $table->integer('reminder_days')->default(90);
                $table->json('applies_to')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('pers_employee_qualifications')) {
            Schema::create('pers_employee_qualifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employe_id');
                $table->unsignedBigInteger('qualification_type_id');
                $table->date('acquired_date');
                $table->date('expiry_date')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('status')->default('gueltig');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->datetime('verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->foreign('employe_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('qualification_type_id')->references('id')->on('pers_qualification_types')->cascadeOnDelete();
                $table->foreign('document_id')->references('id')->on('media')->nullOnDelete();
                $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
                // Kurzname noetig: MySQL-Limit 64 Zeichen
                // (langer Name waere: pers_employee_qualifications_employe_id_qualification_type_id_unique = 69 Zeichen)
                $table->unique(['employe_id', 'qualification_type_id'], 'pers_emp_qual_emp_qualtype_unique');
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('pers_employee_qualifications');
        Schema::dropIfExists('pers_qualification_types');
    }
};
