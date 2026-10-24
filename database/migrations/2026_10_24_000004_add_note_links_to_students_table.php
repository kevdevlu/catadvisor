<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * students and contact_notes point at each other, so these foreign keys
     * can only be added once both tables exist. possible_duplicate_of points
     * at students itself, and Postgres needs the table's primary key in place
     * before that link can be added, so it waits here too.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('possible_duplicate_of')->references('id')->on('students')->nullOnDelete();
            $table->foreign('contact_preference_note_id')->references('id')->on('contact_notes')->nullOnDelete();
            $table->foreign('follow_up_note_id')->references('id')->on('contact_notes')->nullOnDelete();
            $table->foreign('awaiting_reply_note_id')->references('id')->on('contact_notes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['possible_duplicate_of']);
            $table->dropForeign(['contact_preference_note_id']);
            $table->dropForeign(['follow_up_note_id']);
            $table->dropForeign(['awaiting_reply_note_id']);
        });
    }
};
