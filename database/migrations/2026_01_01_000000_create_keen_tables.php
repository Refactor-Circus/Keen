<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keen_entries', function (Blueprint $table): void {
            // Auto-increment rather than ULID: the hash chain needs a strict,
            // gap-free order.
            $table->id();
            // The package the change came from (`showroom`), or `app`.
            $table->string('source', 32);
            $table->string('action');
            // Morph columns rather than a foreign key: history outlives the
            // people and records it is about.
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            // What the entry is scoped to - an organization, a tenant - so
            // the log can be read per scope.
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('surface', 16);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at');

            $table->index(['source', 'id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['scope_type', 'scope_id', 'id']);
            $table->index(['actor_type', 'actor_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // The head of the hash chain: one row every writer locks, so
        // concurrent appends chain one after another on every database
        // (locking the newest entry instead forks the chain on Postgres, and
        // locks nothing while the log is empty).
        Schema::create('keen_chain', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('head_hash', 64)->nullable();
        });

        DB::table('keen_chain')->insert(['id' => 1, 'head_hash' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('keen_chain');
        Schema::dropIfExists('keen_entries');
    }
};
