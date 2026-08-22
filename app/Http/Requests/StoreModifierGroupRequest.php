<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\StoreContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manager role is enforced by middleware
    }

    /**
     * min_select is optional in the payload, but max_select is compared
     * against it — so it has to exist before the rules run.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'min_select' => (int) ($this->input('min_select') ?? 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('modifier_groups', 'name')->where('store_id', app(StoreContext::class)->id()),
            ],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'],
            'modifiers' => ['required', 'array', 'min:1', 'max:30'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['required', 'string', 'max:60'],
            'modifiers.*.price_delta' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have an add-on group with this name.',
            'max_select.gte' => 'The most a customer may choose cannot be fewer than the least they must choose.',
            'modifiers.required' => 'Add at least one option to this group.',
        ];
    }
}
