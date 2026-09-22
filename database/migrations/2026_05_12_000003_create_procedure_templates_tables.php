<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / §8.1 – Trennung Vorlage ↔ Instanz.
 *
 * Legt die neuen Tabellen `procedure_templates` und `procedure_template_steps`
 * an und kopiert alle bestehenden Vorlagen (`procedures.started_at IS NULL`)
 * dorthin. Die alten Vorlagen bleiben vorerst in `procedures` erhalten – das
 * bestehende UI/die alten Controller funktionieren unverändert weiter. Phase 4
 * entfernt sie endgültig.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('procedure_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('name', 120);
            $table->mediumText('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->unsignedBigInteger('legacy_procedure_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('procedure_categories');
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('procedure_template_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('position_id');
            $table->string('name', 120);
            $table->mediumText('description')->nullable();
            $table->unsignedInteger('durationDays')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('legacy_step_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('procedure_templates')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('procedure_template_steps')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions');
        });

        // ── Datenmigration: vorhandene Vorlagen kopieren ──────────────────────
        $vorlagen = DB::table('procedures')
            ->whereNull('started_at')
            ->whereNull('deleted_at')
            ->get();

        $stepMap = []; // legacy_step_id => new_template_step_id

        foreach ($vorlagen as $v) {
            $newTemplateId = DB::table('procedure_templates')->insertGetId([
                'category_id'         => $v->category_id,
                'author_id'           => $v->author_id,
                'name'                => $v->name,
                'description'         => $v->description ?? null,
                'color'               => null,
                'legacy_procedure_id' => $v->id,
                'created_at'          => $v->created_at,
                'updated_at'          => $v->updated_at,
            ]);

            // Verknüpfung in legacy procedures setzen (template_id)
            DB::table('procedures')->where('id', $v->id)->update(['template_id' => $newTemplateId]);

            $steps = DB::table('procedure_steps')
                ->where('procedure_id', $v->id)
                ->orderBy('id')
                ->get();

            // Zwei-Pass-Strategie, weil parent_id auf bereits eingefügte Steps zeigen muss.
            foreach ($steps as $idx => $s) {
                $newStepId = DB::table('procedure_template_steps')->insertGetId([
                    'template_id'    => $newTemplateId,
                    'parent_id'      => null,
                    'position_id'    => $s->position_id,
                    'name'           => $s->name,
                    'description'    => $s->description ?? null,
                    'durationDays'   => $s->durationDays,
                    'sort_order'     => $idx,
                    'legacy_step_id' => $s->id,
                    'created_at'     => $s->created_at,
                    'updated_at'     => $s->updated_at,
                ]);
                $stepMap[$s->id] = $newStepId;
            }

            // Parent-IDs nachtragen
            foreach ($steps as $s) {
                if ($s->parent && isset($stepMap[$s->parent], $stepMap[$s->id])) {
                    DB::table('procedure_template_steps')
                        ->where('id', $stepMap[$s->id])
                        ->update(['parent_id' => $stepMap[$s->parent]]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_template_steps');
        Schema::dropIfExists('procedure_templates');
    }
};

