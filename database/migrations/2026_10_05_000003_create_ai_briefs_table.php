<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One cached prep brief per student. It is deleted whenever
     * that student's notes change; notes_version records which notes it used.
     */
    public function up(): void
    {
        Schema::create('ai_briefs', function (Blueprint $table) {
            $table->foreignUuid('student_id')->primary()->constrained('students')->cascadeOnDelete();
            $table->json('content');
            $table->string('notes_version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_briefs');
    }
};
