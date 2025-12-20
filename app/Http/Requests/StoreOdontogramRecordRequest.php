<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreOdontogramRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('condition_code')) {
            $this->merge([
                'condition_code' => Str::limit(strip_tags($this->condition_code), 10),
            ]);
        }
        
        if ($this->has('condition_name')) {
            $this->merge([
                'condition_name' => Str::limit(strip_tags($this->condition_name), 50),
            ]);
        }
        
        if ($this->has('diagnosis')) {
            $this->merge([
                'diagnosis' => strip_tags($this->diagnosis),
            ]);
        }
        
        if ($this->has('treatment_notes')) {
            $this->merge([
                'treatment_notes' => strip_tags($this->treatment_notes),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dental_piece_id' => 'required|exists:dental_pieces,id',
            'tooth_surface_id' => 'nullable|exists:tooth_surfaces,id',
            'condition_code' => 'required|string|max:10',
            'condition_name' => 'required|string|max:50',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'appointment_id' => 'nullable|exists:appointments,id',
        ];
    }
}

