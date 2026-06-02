<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Survey;
use App\Models\User;

class ResultController extends Controller
{
    /**
     * Aggregated, anonymous results for a single survey. We aggregate over
     * responses/answers only — never joined to any student identity.
     */
    public function show(Survey $survey)
    {
        $survey->load('questions', 'course.professor');

        $responsesCount = $survey->responses()->count();
        $participationCount = $survey->participations()->count();
        $activeStudents = User::query()->students()->active()->count();

        $answersByQuestion = Answer::query()
            ->whereIn('question_id', $survey->questions->pluck('id'))
            ->get()
            ->groupBy('question_id');

        $allRatings = collect();

        $questions = $survey->questions->map(function ($question) use ($answersByQuestion, $allRatings) {
            $answers = $answersByQuestion->get($question->id, collect());

            $payload = [
                'id' => $question->id,
                'text' => $question->text,
                'type' => $question->type,
                'answers_count' => $answers->count(),
            ];

            if ($question->type === 'rating') {
                $ratings = $answers->pluck('rating')->filter(fn ($r) => ! is_null($r));
                $allRatings->push(...$ratings);

                $distribution = [];
                for ($i = 1; $i <= 5; $i++) {
                    $distribution[$i] = $ratings->filter(fn ($r) => (int) $r === $i)->count();
                }

                $payload['average'] = $ratings->count() ? round($ratings->avg(), 2) : null;
                $payload['distribution'] = $distribution;
            } elseif ($question->type === 'single_choice') {
                $counts = [];
                foreach (($question->options ?? []) as $option) {
                    $counts[$option] = 0;
                }
                foreach ($answers as $answer) {
                    if (! is_null($answer->value)) {
                        $counts[$answer->value] = ($counts[$answer->value] ?? 0) + 1;
                    }
                }
                $payload['option_counts'] = $counts;
            } else { // text
                $payload['comments'] = $answers
                    ->pluck('value')
                    ->filter(fn ($v) => filled($v))
                    ->values();
            }

            return $payload;
        });

        return response()->json([
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'type' => $survey->type,
                'status' => $survey->status,
                'course' => $survey->course ? [
                    'name' => $survey->course->name,
                    'professor' => $survey->course->professor?->name,
                ] : null,
            ],
            'stats' => [
                'responses' => $responsesCount,
                'participation' => $participationCount,
                'active_students' => $activeStudents,
                'adoption_rate' => $activeStudents > 0
                    ? round($participationCount / $activeStudents * 100, 1)
                    : 0,
                'overall_average' => $allRatings->count() ? round($allRatings->avg(), 2) : null,
            ],
            'questions' => $questions,
        ]);
    }
}
