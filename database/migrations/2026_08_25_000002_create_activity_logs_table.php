<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * activity_logs is the audit trail for record changes — who created,
     * updated or deleted which master record or document.
     *
     * It is NOT a second stock ledger. stock_movements already records every
     * quantity change and explains current stock; duplicating that here would
     * double the write volume and create two sources of truth. Only the
     * documents and master records are logged.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete, not cascade: deleting a user must never erase the
            // record of what they did. The row survives with a null actor.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // created | updated | deleted
            $table->string('action', 20);

            // The record acted upon, as a polymorphic reference. subject_type
            // stores the readable morph alias ('product') rather than a PHP
            // class path, so the table stays legible and a namespace change
            // does not invalidate history.
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');

            // A snapshot of how the subject identified itself at the time. Kept
            // denormalised on purpose: after a delete the subject row is gone,
            // and a log saying 'deleted product #48' is far less useful than
            // one naming it.
            $table->string('subject_label', 255)->nullable();

            // Changed attributes on an update, with sensitive values redacted.
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            // The log is browsed newest-first and filtered by actor, by action
            // and by subject, so those are the paths worth indexing.
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
