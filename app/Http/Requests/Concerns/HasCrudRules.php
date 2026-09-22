<?php

namespace App\Http\Requests\Concerns;

/**
 * HasCrudRules — shared store/update rule composition for the simple admin
 * CRUD controllers.
 *
 * The create rule set stays the single source of truth: updateRules() derives
 * the update variant from it, making every field optional ("sometimes") while
 * the caller can drop immutable fields or override the ones that must stay
 * strict (a required-on-update field, or a unique rule that ignores the record
 * being updated).
 */
trait HasCrudRules
{
    /**
     * Derive the update rules from the create rules.
     *
     * @param  array<string, mixed>  $createRules  rules used by the store action
     * @param  array<int, string>  $drop  fields that cannot change on update
     * @param  array<string, mixed>  $overrides  fields that replace the derived rule
     * @return array<string, mixed>
     */
    protected function updateRules(array $createRules, array $drop = [], array $overrides = []): array
    {
        $rules = [];

        foreach ($createRules as $field => $rule) {
            if (in_array($field, $drop, true)) {
                continue;
            }

            $rules[$field] = $this->makeOptional($rule);
        }

        return array_merge($rules, $overrides);
    }

    /**
     * Make a single field rule optional without dropping its other constraints.
     *
     * @param  string|array<int, string>  $rule
     * @return string|array<int, string>
     */
    private function makeOptional(string|array $rule): string|array
    {
        if (is_array($rule)) {
            $rule = array_values(array_diff($rule, ['required']));

            if (!in_array('sometimes', $rule, true)) {
                array_unshift($rule, 'sometimes');
            }

            return $rule;
        }

        $parts = array_values(array_diff(explode('|', $rule), ['required']));

        if (!in_array('sometimes', $parts, true)) {
            array_unshift($parts, 'sometimes');
        }

        return implode('|', $parts);
    }
}
