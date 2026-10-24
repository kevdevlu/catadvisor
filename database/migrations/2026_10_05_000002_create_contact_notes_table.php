<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per note. external_id keeps the CSV note ID
     * (N001...), and deleted notes stay in the table with deleted_at set.
     */
    public function up(): void
    {
        Schema::create('contact_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->nullable()->unique();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('advisors');
            $table->date('contact_date');
            $table->string('contact_type');
            $table->text('note_text');
            $table->boolean('is_private')->default(false);
            $table->boolean('ai_read')->default(false);
            $table->timestamps();
            $table->foreignUuid('updated_by')->nullable()->constrained('advisors')->nullOnDelete();
            $table->softDeletes();
            $table->foreignUuid('deleted_by')->nullable()->constrained('advisors')->nullOnDelete();

            $table->index(['student_id', 'contact_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_notes');
    }
};
