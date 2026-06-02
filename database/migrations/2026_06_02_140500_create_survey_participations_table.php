<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Participation log: records THAT a student completed a survey.
     * Used to prevent double submission and to measure adoption.
     * Deliberately stores NO answer data, so it can never reveal WHAT was answered.
     */
    public function up(): void
    {
        Schema::create('survey_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['survey_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_participations');
    }
};
