<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
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
            'amount.required' => 'Please enter the expense amount.',
            'amount.min' => 'The amount must be greater than zero.',
            'date.required' => 'Please select the expense date.',
            'receipt.image' => 'The receipt must be an image file.',
            'receipt.max' => 'The receipt image must not exceed 5MB.',
        ];
    }
}
