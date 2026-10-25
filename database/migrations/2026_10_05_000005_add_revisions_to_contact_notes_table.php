<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * revision counts a note's edits; ai_checked_revision is the edit the AI
     * last checked. The check runs once per version of a note:
     * again only after an edit, not every time someone asks. A counter,
     * not a time: timestamps keep whole seconds, so an edit in the same
     * second as a check would look already checked.
     */
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0)->after('ai_read');
            $table->unsignedInteger('ai_checked_revision')->nullable()->after('revision');
        });
    }

    public function down(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->dropColumn(['revision', 'ai_checked_revision']);
        });
    }
};
