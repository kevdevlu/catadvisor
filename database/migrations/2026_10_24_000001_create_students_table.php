<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * There is no "last contact date" column:
     * it is always worked out from the notes.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sis_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('major');
            $table->string('class_year');
            $table->unsignedSmallInteger('credits_earned');
            $table->decimal('gpa', 3, 2);
            $table->string('risk_flag');
            $table->foreignUuid('advisor_id')->constrained('advisors');
            $table->uuid('possible_duplicate_of')->nullable();

            // Contact preference. The links to notes, and the
            // possible_duplicate_of link above, are added in a later migration
            // (add_note_links_to_students_table).
            $table->text('contact_preference')->nullable();
            $table->uuid('contact_preference_note_id')->nullable();
            $table->foreignUuid('contact_preference_set_by')->nullable()->constrained('advisors')->nullOnDelete();
            $table->timestamp('contact_preference_set_at')->nullable();

            // Next follow-up. A null set_by means the AI set it.
            $table->date('follow_up_on')->nullable();
            $table->string('follow_up_reason')->nullable();
            $table->uuid('follow_up_note_id')->nullable();
            $table->foreignUuid('follow_up_set_by')->nullable()->constrained('advisors')->nullOnDelete();
            $table->timestamp('follow_up_set_at')->nullable();

            // Waiting on a reply. A null date means not waiting.
            $table->date('awaiting_reply_since')->nullable();
            $table->uuid('awaiting_reply_note_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
