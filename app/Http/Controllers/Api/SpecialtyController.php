<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Specialty::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $specialties = $query->orderBy('name')->get();

        return response()->json([
            'data' => $specialties,
            'meta' => [
                'message' => 'Especialidades cargadas exitosamente',
                'total' => $specialties->count(),
            ],
        ]);
    }

    public function active(): JsonResponse
    {
        $specialties = Specialty::active()->orderBy('name')->get();

        return response()->json([
            'data' => $specialties,
            'meta' => [
                'message' => 'Especialidades activas cargadas exitosamente',
                'total' => $specialties->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $specialty = Specialty::findOrFail($id);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad obtenida exitosamente'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:specialties,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $specialty = Specialty::create($validated);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad creada exitosamente'],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $specialty = Specialty::findOrFail($id);

        $validated = $request->validate([
            'code' => "sometimes|string|max:50|unique:specialties,code,{$id}",
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $specialty->update($validated);

        return response()->json([
            'data' => $specialty,
            'meta' => ['message' => 'Especialidad actualizada exitosamente'],
        ]);
    }
}
