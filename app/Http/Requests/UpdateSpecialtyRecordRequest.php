<?php

namespace App\Http\Requests;

/**
 * Update contract for a specialty record (issue #212).
 *
 * The create request owns the per-specialty field list, limits and enums.
 * The update contract reuses them verbatim and only relaxes what cannot be
 * required again: every clinical field becomes optional, and `patient_id`
 * leaves the payload because an existing record stays with its patient.
 *
 * Before this request existed, `SpecialtyRecordController@update` validated a
 * fixed subset of columns, so editing an implantology status, an orthodontic
 * phase or an oral-surgery note answered 200 while silently discarding the
 * value.
 */
class UpdateSpecialtyRecordRequest extends StoreSpecialtyRecordRequest
{
    /**
     * @return array<string, string|array<int, string>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['patient_id']);

        foreach ($rules as $field => $rule) {
            if ($field === 'specialty') {
                continue;
            }

            $rules[$field] = $this->optional($rule);
        }

        return $rules;
    }

    /**
     * @param  string|array<int, string> $rule
     * @return string|array<int, string>
     */
    private function optional(string|array $rule): string|array
    {
        if (is_array($rule)) {
            return array_values(array_unique([...$rule, 'sometimes', 'nullable']));
        }

        $segments = array_values(array_filter(
            explode('|', $rule),
            static fn (string $segment): bool => $segment !== 'required'
        ));

        return implode('|', array_values(array_unique([...$segments, 'sometimes', 'nullable'])));
    }
}
