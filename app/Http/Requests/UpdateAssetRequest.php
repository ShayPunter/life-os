<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost' => ['required_without_all:original_cost,original_currency', 'nullable', 'numeric', 'min:0.01'],
            'original_cost' => ['nullable', 'numeric', 'min:0.01', 'required_with:original_currency'],
            'original_currency' => ['nullable', 'string', 'size:3', 'in:GBP,EUR,CZK,USD', 'required_with:original_cost'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'tracking_type' => ['required', 'string', 'in:uses,hours'],
            'purchased_at' => ['required', 'date'],
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
            'name.required' => 'Please enter the asset name.',
            'name.max' => 'The asset name must not exceed 255 characters.',
            'cost.required' => 'Please enter the cost.',
            'cost.min' => 'The cost must be greater than zero.',
            'original_currency.in' => 'The currency must be one of: GBP, EUR, CZK, USD.',
            'purchased_at.required' => 'Please select the purchase date.',
        ];
    }
}
