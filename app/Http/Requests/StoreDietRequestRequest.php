<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDietRequestRequest extends FormRequest
{
    /** Only physicians may originate or modify therapeutic diet orders (ownership enforced by policy). */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('doctor');
    }

    public function rules(): array
    {
        return [
            'patient_id'       => 'required|exists:patients,id',
            'diet_type'        => 'required|string|max:100',
            'allergies'        => 'nullable|string|max:500',
            'food_restrictions'=> 'nullable|string|max:500',
            'clinical_notes'   => 'nullable|string|max:1000',
        ];
    }
}
