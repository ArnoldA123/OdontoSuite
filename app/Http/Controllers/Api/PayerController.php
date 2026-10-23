<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Convenios (#293 S3).
 *
 * Quotation roles read the catalog to choose the agreement of an estimate; only
 * the administrator creates or edits one. A convenio is never deleted: the
 * tariff rows and past quotes refer to it, so it is deactivated instead.
 */
class PayerController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $payers = Payer::query()
                ->withCount(['procedurePrices as prices_count'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $payers,
                'meta' => [
                    'message' => 'Convenios cargados exitosamente',
                    'total' => $payers->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo listar los convenios', ['exception' => $e]);

            return response()->json(['message' => 'Error al listar los convenios'], 500);
        }
    }

    public function active(): JsonResponse
    {
        try {
            $payers = Payer::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'code', 'name']);

            return response()->json([
                'data' => $payers,
                'meta' => [
                    'message' => 'Convenios activos cargados exitosamente',
                    'total' => $payers->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo listar los convenios activos', ['exception' => $e]);

            return response()->json(['message' => 'Error al listar los convenios activos'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate($this->rules(), $this->messages());

            $payer = Payer::create($validated);
            $payer->refresh();

            return response()->json([
                'data' => $payer,
                'meta' => ['message' => 'Convenio creado exitosamente'],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo crear el convenio', ['exception' => $e]);

            return response()->json(['message' => 'Error al crear el convenio'], 500);
        }
    }

    public function update(Request $request, Payer $payer): JsonResponse
    {
        try {
            $validated = $request->validate($this->rules($payer), $this->messages());

            $payer->update($validated);
            $payer->refresh();

            return response()->json([
                'data' => $payer,
                'meta' => ['message' => 'Convenio actualizado exitosamente'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No se pudo actualizar el convenio', ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar el convenio'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Payer $payer = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('payers', 'code')->ignore($payer?->id),
            ],
            'name' => 'required|string|max:120',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'El código del convenio es obligatorio.',
            'code.string' => 'El código del convenio debe ser texto.',
            'code.max' => 'El código del convenio no puede exceder 30 caracteres.',
            'code.regex' => 'El código del convenio solo admite minúsculas, números, punto, guion y guion bajo.',
            'code.unique' => 'Ya existe un convenio con ese código.',
            'name.required' => 'El nombre del convenio es obligatorio.',
            'name.string' => 'El nombre del convenio debe ser texto.',
            'name.max' => 'El nombre del convenio no puede exceder 120 caracteres.',
            'is_active.boolean' => 'El estado del convenio debe ser verdadero o falso.',
        ];
    }
}
