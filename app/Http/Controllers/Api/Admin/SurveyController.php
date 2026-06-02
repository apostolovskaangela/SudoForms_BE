<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    public function index()
    {
        $surveys = Survey::query()
            ->with('course.professor')
            ->withCount(['questions', 'responses', 'participations'])
            ->latest()
            ->get()
            ->map(fn (Survey $s) => $this->summary($s));

        return response()->json(['surveys' => $surveys]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSurvey($request);

        $survey = DB::transaction(function () use ($data, $request) {
            $survey = Survey::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'course_id' => $data['type'] === 'course' ? ($data['course_id'] ?? null) : null,
                'status' => $data['status'] ?? 'draft',
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->syncQuestions($survey, $data['questions']);

            return $survey;
        });

        return response()->json(['survey' => $this->detail($survey->fresh())], 201);
    }

    public function show(Survey $survey)
    {
        return response()->json(['survey' => $this->detail($survey)]);
    }

    public function update(Request $request, Survey $survey)
    {
        $data = $this->validateSurvey($request);

        DB::transaction(function () use ($survey, $data) {
            $survey->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'course_id' => $data['type'] === 'course' ? ($data['course_id'] ?? null) : null,
                'status' => $data['status'] ?? $survey->status,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            // Replace questions wholesale (answers reference questions, but admins
            // only edit surveys before/while collecting; we rebuild the set here).
            $survey->questions()->delete();
            $this->syncQuestions($survey, $data['questions']);
        });

        return response()->json(['survey' => $this->detail($survey->fresh())]);
    }

    public function destroy(Survey $survey)
    {
        $survey->delete();

        return response()->json(['message' => 'Survey deleted.']);
    }

    public function activate(Survey $survey)
    {
        $survey->update(['status' => 'active']);

        return response()->json(['survey' => $this->detail($survey->fresh())]);
    }

    public function close(Survey $survey)
    {
        $survey->update(['status' => 'closed']);

        return response()->json(['survey' => $this->detail($survey->fresh())]);
    }

    private function validateSurvey(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:course,administration'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'status' => ['nullable', 'in:draft,active,closed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:rating,text,single_choice'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*' => ['string'],
            'questions.*.required' => ['boolean'],
        ]);
    }

    private function syncQuestions(Survey $survey, array $questions): void
    {
        foreach (array_values($questions) as $index => $q) {
            $survey->questions()->create([
                'text' => $q['text'],
                'type' => $q['type'],
                'options' => $q['type'] === 'single_choice' ? array_values($q['options'] ?? []) : null,
                'required' => $q['required'] ?? true,
                'order' => $index,
            ]);
        }
    }

    private function summary(Survey $survey): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->description,
            'type' => $survey->type,
            'status' => $survey->status,
            'course' => $survey->course ? [
                'id' => $survey->course->id,
                'name' => $survey->course->name,
                'professor' => $survey->course->professor?->name,
            ] : null,
            'questions_count' => $survey->questions_count,
            'responses_count' => $survey->responses_count,
            'participations_count' => $survey->participations_count,
            'starts_at' => $survey->starts_at?->toIso8601String(),
            'ends_at' => $survey->ends_at?->toIso8601String(),
            'created_at' => $survey->created_at?->toIso8601String(),
        ];
    }

    private function detail(Survey $survey): array
    {
        $survey->load('questions', 'course.professor')->loadCount(['responses', 'participations']);

        return array_merge($this->summary($survey), [
            'questions' => $survey->questions->map(fn ($q) => [
                'id' => $q->id,
                'text' => $q->text,
                'type' => $q->type,
                'options' => $q->options,
                'required' => $q->required,
                'order' => $q->order,
            ])->values(),
        ]);
    }
}
