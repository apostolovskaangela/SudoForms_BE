<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Professor;
use Illuminate\Http\Request;

class ProfessorController extends Controller
{
    public function index()
    {
        $professors = Professor::query()
            ->orderBy('name')
            ->get(['id', 'name', 'title', 'department']);

        return response()->json(['professors' => $professors]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $professor = Professor::create($data);

        return response()->json(['professor' => $professor], 201);
    }
}
