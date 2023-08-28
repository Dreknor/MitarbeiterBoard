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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('setting');
            $table->string('setting_name');
            $table->string('type');
            $table->text('value')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();

        });

        Spatie\Permission\Models\Permission::create([
            'name' => 'edit settings',
            'guard_name' => 'web'
        ]);

        exec('php artisan cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');

    }
};
