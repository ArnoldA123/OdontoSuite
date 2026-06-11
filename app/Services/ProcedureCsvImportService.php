<?php

namespace App\Services;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sprint 3 fix (IM-6): importador CSV de procedimientos del catalogo.
 *
 * Formato esperado del CSV (header obligatorio):
 *   code,name,description,specialty_code,default_cost,default_duration_minutes,materials_needed,requires_anesthesia,requires_radiographs,contraindications,post_procedure_care,is_active
 *
 * Comportamiento:
 *   - code existe -> actualiza (mantiene specialty_id, solo actualiza campos del CSV)
 *   - code nuevo -> crea
 *   - specialty_code se resuelve a specialty_id (FK). Si no existe, se omite el campo.
 *   - Errores de validacion se reportan por fila con su numero, NO abortan el batch.
 *
 * Devuelve: { inserted: N, updated: M, errors: K, failed_rows: [{row, errors, data}] }
 */
class ProcedureCsvImportService
{
    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo leer el archivo CSV.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV vacío o sin encabezado.');
        }
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $specialtyMap = Specialty::pluck('id', 'code')->toArray();

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $failedRows = [];
        $rowNumber = 1; // header es row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue; // fila vacia
            }

            if (count($row) !== count($header)) {
                $errors++;
                $failedRows[] = [
                    'row' => $rowNumber,
                    'errors' => ['Formato: número de columnas no coincide con el encabezado.'],
                    'data' => $row,
                ];
                continue;
            }

            $data = array_combine($header, $row);
            $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);

            $validator = Validator::make($data, [
                'code' => 'required|string|max:50',
                'name' => 'required|string|max:200',
                'description' => 'nullable|string',
                'specialty_code' => 'nullable|string|max:50',
                'default_cost' => 'nullable|numeric|min:0|max:999999.99',
                'default_duration_minutes' => 'nullable|integer|min:1|max:480',
                'materials_needed' => 'nullable|string',
                'requires_anesthesia' => 'nullable|in:0,1,true,false,si,no',
                'requires_radiographs' => 'nullable|in:0,1,true,false,si,no',
                'contraindications' => 'nullable|string',
                'post_procedure_care' => 'nullable|string',
                'is_active' => 'nullable|in:0,1,true,false,si,no',
            ]);

            if ($validator->fails()) {
                $errors++;
                $failedRows[] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->all(),
                    'data' => $data,
                ];
                continue;
            }

            $validated = $validator->validated();
            $code = $validated['code'];

            $boolFields = ['requires_anesthesia', 'requires_radiographs', 'is_active'];
            foreach ($boolFields as $f) {
                if (isset($validated[$f])) {
                    $validated[$f] = in_array(strtolower($validated[$f]), ['1', 'true', 'si'], true);
                } else {
                    $validated[$f] = false;
                }
            }

            if (!empty($validated['specialty_code']) && isset($specialtyMap[$validated['specialty_code']])) {
                $validated['specialty_id'] = $specialtyMap[$validated['specialty_code']];
            }
            unset($validated['specialty_code']);

            try {
                DB::transaction(function () use ($code, $validated, &$inserted, &$updated) {
                    $existing = ProcedureCatalog::where('code', $code)->first();
                    if ($existing) {
                        $existing->update($validated);
                        $updated++;
                    } else {
                        ProcedureCatalog::create($validated);
                        $inserted++;
                    }
                });
            } catch (\Exception $e) {
                $errors++;
                $failedRows[] = [
                    'row' => $rowNumber,
                    'errors' => [$e->getMessage()],
                    'data' => $data,
                ];
                Log::warning('CSV import row failed', [
                    'row' => $rowNumber,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        fclose($handle);

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'failed_rows' => $failedRows,
        ];
    }
}
