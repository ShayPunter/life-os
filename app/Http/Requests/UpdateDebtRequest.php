<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDebtRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'debtor_name' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'type' => ['sometimes', 'required', 'in:owed_to_me,i_owe'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'is_paid' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'debtor_name.required' => 'Please enter the name of the person or entity.',
            'amount.required' => 'Please enter the debt amount.',
            'amount.min' => 'The amount must be greater than zero.',
            'type.required' => 'Please specify whether this debt is owed to you or you owe it.',
            'type.in' => 'Invalid debt type selected.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',
        ];
    }
}
