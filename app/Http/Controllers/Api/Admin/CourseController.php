<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::query()
            ->with('professor')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'semester' => $c->semester,
                'professor' => $c->professor ? [
                    'id' => $c->professor->id,
                    'name' => $c->professor->name,
                ] : null,
            ]);

        return response()->json(['courses' => $courses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'professor_id' => ['nullable', 'exists:professors,id'],
            'semester' => ['nullable', 'string', 'max:50'],
        ]);

        $course = Course::create($data);

        return response()->json(['course' => $course->load('professor')], 201);
    }
}
