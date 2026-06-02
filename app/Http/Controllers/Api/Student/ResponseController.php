<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Response as SurveyResponse;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponseController extends Controller
{
    /**
     * Submit an anonymous response.
     *
     * Anonymity is enforced here: we write the participation row (WHO, in
     * survey_participations, keyed to the student) and the response + answers
     * (WHAT, with no student key) as two independent records. They share only
     * the survey id, so no individual answer can be traced to the student.
     */
    public function store(Request $request, Survey $survey)
    {
        $user = $request->user();

        if (! $survey->isOpen()) {
            return response()->json(['message' => 'This survey is not open for responses.'], 422);
        }

        $alreadyDone = SurveyParticipation::where('survey_id', $survey->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyDone) {
            return response()->json(['message' => 'You have already submitted this survey.'], 409);
        }

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'answers.*.value' => ['nullable', 'string', 'max:5000'],
        ]);

        $survey->load('questions');
        $questions = $survey->questions->keyBy('id');

        // Index submitted answers by question id (ignoring any not in this survey).
        $submitted = collect($data['answers'])
            ->filter(fn ($a) => $questions->has($a['question_id']))
            ->keyBy('question_id');

        // Enforce that required questions were actually answered.
        foreach ($survey->questions as $question) {
            if (! $question->required) {
                continue;
            }

            $answer = $submitted->get($question->id);
            $hasValue = $question->type === 'rating'
                ? ($answer && ! is_null($answer['rating'] ?? null))
                : ($answer && filled($answer['value'] ?? null));

            if (! $hasValue) {
                throw ValidationException::withMessages([
                    'answers' => ["Please answer the required question: \"{$question->text}\"."],
                ]);
            }
        }

        DB::transaction(function () use ($survey, $user, $submitted, $questions) {
            // WHO: log participation (prevents double-submit, measures adoption).
            SurveyParticipation::create([
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'submitted_at' => now(),
            ]);

            // WHAT: anonymous response container — no student reference.
            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'submitted_at' => now(),
            ]);

            foreach ($submitted as $questionId => $answer) {
                $question = $questions->get($questionId);

                Answer::create([
                    'response_id' => $response->id,
                    'question_id' => $question->id,
                    'rating' => $question->type === 'rating' ? ($answer['rating'] ?? null) : null,
                    'value' => $question->type === 'rating' ? null : ($answer['value'] ?? null),
                ]);
            }
        });

        return response()->json([
            'message' => 'Thank you! Your anonymous feedback has been recorded.',
        ], 201);
    }
}
