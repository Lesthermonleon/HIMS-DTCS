<?php

namespace App\Http\Requests;

use App\Models\DietPlan;
use App\Models\DietRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDietPlanRequest extends FormRequest
{
    /** Only physicians author nutrition assessments and diet plans in the Doctor-Managed DNMS workflow. */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->hasRole('doctor')) {
            return false;
        }

        if ($this->isMethod('POST')) {
            foreach (['diet_request_id', 'preselected_request_id'] as $field) {
                if ($this->filled($field) && is_numeric($this->input($field))) {
                    $dietRequest = DietRequest::with('patient')->find((int) $this->input($field));
                    if ($dietRequest && ! $user->can('create', [DietPlan::class, $dietRequest])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'plan_details'     => 'required|string|min:20',
            'assessment_notes' => 'nullable|string|max:2000',
            'total_calories'   => 'nullable|integer|min:500|max:5000',
            'protein_grams'    => 'nullable|numeric|min:0',
            'carb_grams'       => 'nullable|numeric|min:0',
            'fat_grams'        => 'nullable|numeric|min:0',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after:start_date',
            'notes'            => 'nullable|string|max:1000',
        ];

        if ($this->isMethod('POST')) {
            $rules['diet_request_id']        = ['required', 'integer', 'exists:diet_requests,id'];
            $rules['preselected_request_id'] = ['nullable', 'integer', 'exists:diet_requests,id'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isMethod('POST')) {
                    return;
                }

                if (
                    $this->filled('preselected_request_id')
                    && $this->filled('diet_request_id')
                    && (int) $this->input('diet_request_id') !== (int) $this->input('preselected_request_id')
                ) {
                    $validator->errors()->add(
                        'diet_request_id',
                        'The preselected diet request cannot be changed.'
                    );
                }
            },
        ];
    }
}
