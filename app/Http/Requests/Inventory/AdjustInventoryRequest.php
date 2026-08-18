<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'type' => ['required', 'in:add,remove,reserve,release,adjustment,return'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference_type' => ['nullable', 'string', 'max:64'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
