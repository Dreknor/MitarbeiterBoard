<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roster_events', function (Blueprint $table) {
            if (! $this->indexExists('roster_events', 'roster_events_comp')) {
                $table->index(['roster_id','date','employe_id','start'], 'roster_events_comp');
            }
            if (! $this->indexExists('roster_events', 'roster_events_roster_date')) {
                $table->index(['roster_id','date'], 'roster_events_roster_date');
            }
        });

        Schema::table('working_times', function (Blueprint $table) {
            if (! $this->indexExists('working_times', 'working_times_comp')) {
                $table->index(['roster_id','date','employe_id'], 'working_times_comp');
            }
            if (! $this->indexExists('working_times', 'working_times_roster_date')) {
                $table->index(['roster_id','date'], 'working_times_roster_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roster_events', function (Blueprint $table) {
            $table->dropIndex('roster_events_comp');
            $table->dropIndex('roster_events_roster_date');
        });
        Schema::table('working_times', function (Blueprint $table) {
            $table->dropIndex('working_times_comp');
            $table->dropIndex('working_times_roster_date');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $schemaManager = $connection->getDoctrineSchemaManager();
        $indexes = $schemaManager->listTableIndexes($connection->getTablePrefix().$table);
        return array_key_exists($index, $indexes);
    }
};

