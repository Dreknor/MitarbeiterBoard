<?php

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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('users');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        \App\Models\Setting::insert([
            'module' => 'Urlaubsplaner',
            'setting' => 'absence_auto_create',
            'setting_name' => 'Abwesenheiten erstellen',
            'type'  => 'boolean',
            'value' => '1',
            'description' => 'Automatisch Abwesenheiten erstellen, wenn Urlaub genehmigt wurde',
        ]);

        \App\Models\Setting::insert([
            'module' => 'Urlaubsplaner',
            'setting' => 'show_holidays',
            'setting_name' => 'Urlaub Anderer anzeigen',
            'type'  => 'boolean',
            'value' => '0',
            'description' => 'Dürfen Benutzer den Urlaub anderer Benutzer sehen?',
        ]);


        \App\Models\Setting::insert([
            'module' => 'Urlaubsplaner',
            'setting' => 'ferien_state',
            'setting_name' => 'Bundeslandkürzel für Ferien',
            'type'  => 'text',
            'value' => 'SN',
            'description' => 'Bundesland-Kürzel für die Ferien',
        ]);

        Spatie\Permission\Models\Permission::insert([
           'name' => 'approve holidays',
              'guard_name' => 'web',
        ]);

        \Spatie\Permission\Models\Permission::insert([
           'name' => 'has holidays',
              'guard_name' => 'web',
        ]);

        exec('php artisan cache:clear');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
