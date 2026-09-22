<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // deputy_id zu users-Tabelle (Abwesenheitsvertretung)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('deputy_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('superior_id');
        });

        // Organisations-Positionen (selbstreferenzierend)
        Schema::create('pers_org_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('parent_position_id')->nullable()
                ->constrained('pers_org_positions')->nullOnDelete();
            $table->integer('level')->default(0);
            $table->boolean('is_leadership')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->nullable();  // Hex-Farbcode, z.B. '#3B82F6'
            $table->timestamps();
        });

        // Pivot: Stellenzuordnungen (mit Gültigkeitszeitraum)
        Schema::create('pers_org_position_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pers_org_position_id')
                ->constrained('pers_org_positions')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->boolean('is_deputy')->default(false);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['pers_org_position_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pers_org_position_user');
        Schema::dropIfExists('pers_org_positions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deputy_id']);
            $table->dropColumn('deputy_id');
        });
    }
};

