<?php

namespace App\Http\Requests\Pos;

use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = app(WorkspaceContext::class)->workspaceId();

        return [
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'dining_table_id' => [
                'nullable',
                'integer',
                Rule::exists('dining_tables', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
