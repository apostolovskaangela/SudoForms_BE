<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $completedIds = SurveyParticipation::where('user_id', $user->id)->pluck('survey_id');

        $open = Survey::query()
            ->with('course.professor')
            ->withCount('questions')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->latest()
            ->get();

        $available = $open
            ->whereNotIn('id', $completedIds)
            ->values()
            ->map(fn (Survey $s) => $this->summary($s));

        $completed = Survey::query()
            ->with('course.professor')
            ->withCount('questions')
            ->whereIn('id', $completedIds)
            ->latest()
            ->get()
            ->map(fn (Survey $s) => $this->summary($s));

        return response()->json([
            'available' => $available,
            'completed' => $completed,
        ]);
    }

    public function show(Request $request, Survey $survey)
    {
        $user = $request->user();

        if (! $survey->isOpen()) {
            return response()->json(['message' => 'This survey is not currently open.'], 404);
        }

        $alreadyDone = SurveyParticipation::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyDone) {
            return response()->json([
                'message' => 'You have already completed this survey.',
                'completed' => true,
            ], 409);
        }

        $survey->load('questions', 'course.professor');

        return response()->json([
            'survey' => array_merge($this->summary($survey), [
                'questions' => $survey->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'text' => $q->text,
                    'type' => $q->type,
                    'options' => $q->options,
                    'required' => $q->required,
                ])->values(),
            ]),
        ]);
    }

    private function summary(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->description,
            'type' => $survey->type,
            'questions_count' => $survey->questions_count,
            'course' => $survey->course ? [
                'name' => $survey->course->name,
                'professor' => $survey->course->professor?->name,
            ] : null,
            'ends_at' => $survey->ends_at?->toIso8601String(),
        ];
    }
}
