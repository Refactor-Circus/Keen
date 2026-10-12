<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Keen\Support\Tables;

return new class extends Migration
{
    public function up(): void
    {
        $table = Tables::name('entries');

        // A record's history and a person's own entries are read newest
        // first: with the id last, the index serves the order and the cursor
        // too, on every database. They supersede the two-column indexes.
        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->dropIndex(['subject_type', 'subject_id']);
            $blueprint->dropIndex(['actor_type', 'actor_id']);

            $blueprint->index(['subject_type', 'subject_id', 'id'], $table.'_subject_index');
            $blueprint->index(['actor_type', 'actor_id', 'id'], $table.'_actor_index');
        });
    }

    public function down(): void
    {
        $table = Tables::name('entries');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->dropIndex($table.'_subject_index');
            $blueprint->dropIndex($table.'_actor_index');

            $blueprint->index(['subject_type', 'subject_id']);
            $blueprint->index(['actor_type', 'actor_id']);
        });
    }
};
