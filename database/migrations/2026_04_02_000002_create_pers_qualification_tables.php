<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('pers_employee_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('qualification_type_id')->constrained('pers_qualification_types');
            $table->date('acquired_date');
            $table->date('expiry_date')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('pers_documents')->nullOnDelete();
            $table->string('status')->default('gueltig');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employe_id', 'qualification_type_id']);
        });

        // Seed: 11 Standard-Pflichtqualifikationen (Anhang C.1)
        $qualifications = [
            ['name' => 'Erweitertes Führungszeugnis', 'category' => 'pflicht', 'validity_months' => 60, 'reminder_days' => 90, 'applies_to' => null, 'description' => 'Gemäß § 30a BZRG'],
            ['name' => 'Masernschutz-Nachweis', 'category' => 'pflicht', 'validity_months' => null, 'reminder_days' => 0, 'applies_to' => null, 'description' => 'Gemäß § 20 Abs. 9 IfSG'],
            ['name' => 'Erste-Hilfe-Kurs', 'category' => 'pflicht', 'validity_months' => 24, 'reminder_days' => 90, 'applies_to' => null, 'description' => null],
            ['name' => 'Erste-Hilfe am Kind', 'category' => 'empfohlen', 'validity_months' => 24, 'reminder_days' => 90, 'applies_to' => json_encode(['regulaer', 'lehrer']), 'description' => null],
            ['name' => 'Belehrung Infektionsschutzgesetz', 'category' => 'pflicht', 'validity_months' => 24, 'reminder_days' => 60, 'applies_to' => null, 'description' => null],
            ['name' => 'Datenschutzschulung', 'category' => 'pflicht', 'validity_months' => 12, 'reminder_days' => 30, 'applies_to' => null, 'description' => null],
            ['name' => 'Brandschutzunterweisung', 'category' => 'pflicht', 'validity_months' => 12, 'reminder_days' => 30, 'applies_to' => null, 'description' => null],
            ['name' => 'Arbeitsschutzunterweisung', 'category' => 'pflicht', 'validity_months' => 12, 'reminder_days' => 30, 'applies_to' => null, 'description' => null],
            ['name' => 'Schwimmfähigkeitsnachweis', 'category' => 'empfohlen', 'validity_months' => null, 'reminder_days' => 0, 'applies_to' => json_encode(['lehrer']), 'description' => null],
            ['name' => 'Religionspädagogische Qualifikation', 'category' => 'empfohlen', 'validity_months' => null, 'reminder_days' => 0, 'applies_to' => json_encode(['lehrer', 'regulaer']), 'description' => null],
            ['name' => 'Fachkraft nach §21 SächsKitaG', 'category' => 'pflicht', 'validity_months' => null, 'reminder_days' => 0, 'applies_to' => json_encode(['regulaer']), 'description' => null],
        ];

        foreach ($qualifications as $q) {
            DB::table('pers_qualification_types')->insertOrIgnore($q + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Permissions
        Permission::findOrCreate('view qualifications', 'web');
        Permission::findOrCreate('manage qualifications', 'web');
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_employee_qualifications');
        Schema::dropIfExists('pers_qualification_types');
    }
};

