<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Response;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $activeStudents = User::query()->students()->active()->count();

        $surveys = Survey::query()
            ->with('course.professor')
            ->withCount(['participations', 'responses'])
            ->latest()
            ->get();

        // Average rating per survey (rating column is not encrypted, so SQL AVG works).
        $avgBySurvey = Answer::query()
            ->join('questions', 'answers.question_id', '=', 'questions.id')
            ->whereNotNull('answers.rating')
            ->selectRaw('questions.survey_id as survey_id, AVG(answers.rating) as avg_rating')
            ->groupBy('questions.survey_id')
            ->pluck('avg_rating', 'survey_id');

        $participatingStudents = SurveyParticipation::query()
            ->distinct('user_id')
            ->count('user_id');

        $perSurvey = $surveys->map(fn (Survey $s) => [
            'id' => $s->id,
            'title' => $s->title,
            'type' => $s->type,
            'status' => $s->status,
            'course' => $s->course?->name,
            'professor' => $s->course?->professor?->name,
            'participations' => $s->participations_count,
            'responses' => $s->responses_count,
            'adoption_rate' => $activeStudents > 0
                ? round($s->participations_count / $activeStudents * 100, 1)
                : 0,
            'average_rating' => isset($avgBySurvey[$s->id])
                ? round((float) $avgBySurvey[$s->id], 2)
                : null,
        ]);

        return response()->json([
            'stats' => [
                'total_surveys' => $surveys->count(),
                'active_surveys' => $surveys->where('status', 'active')->count(),
                'closed_surveys' => $surveys->where('status', 'closed')->count(),
                'draft_surveys' => $surveys->where('status', 'draft')->count(),
                'total_responses' => Response::count(),
                'active_students' => $activeStudents,
                'participating_students' => $participatingStudents,
                'overall_adoption_rate' => $activeStudents > 0
                    ? round($participatingStudents / $activeStudents * 100, 1)
                    : 0,
            ],
            'surveys' => $perSurvey,
        ]);
    }
}
