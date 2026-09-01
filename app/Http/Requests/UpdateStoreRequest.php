<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\OpeningHours;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manager role is enforced by middleware.
    }

    /**
     * The slug is deliberately absent: it is printed into every QR code a
     * cafe has put out, and changing it would silently break them all.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            // Exactly six hex digits. Anything looser and the value ends
            // up inside a CSS rule, which is not a place to be relaxed.
            'accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['sometimes', 'image', 'mimes:png,webp,jpeg,jpg', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'online_ordering_enabled' => ['required', 'boolean'],

            // Absent means "no hours set", which is a shop that never closes.
            // A day present but null is a closing day. Both are meaningful,
            // so neither is filtered out before it gets here.
            'hours' => ['nullable', 'array'],
            'hours.*' => ['nullable', 'array:open,close'],
            'hours.*.open' => ['required_with:hours.*.close', 'date_format:H:i'],
            'hours.*.close' => ['required_with:hours.*.open', 'date_format:H:i'],
        ];
    }

    /**
     * Reduce the submitted week to the seven real days, before rules run.
     *
     * Whitelisted rather than validated with a rule so that a stray key
     * cannot reach the JSON column at all — the value is read back indexed by
     * day name, and a shop with an "8th" day would simply be invisible to
     * every lookup that matters.
     *
     * This is `prepareForValidation` rather than `passedValidation` because
     * the controller saves `validated()`, which reads from the validator and
     * would never see a merge made after the fact.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('hours')) {
            return;
        }

        $submitted = $this->input('hours');

        if (! is_array($submitted)) {
            $this->merge(['hours' => null]);

            return;
        }

        $week = [];

        foreach (OpeningHours::DAYS as $day) {
            $entry = $submitted[$day] ?? null;

            $week[$day] = is_array($entry)
                && ($entry['open'] ?? null) !== null
                && ($entry['close'] ?? null) !== null
                ? ['open' => $entry['open'], 'close' => $entry['close']]
                : null;
        }

        // Every day closed is far more likely a mis-click than an intention,
        // and to a customer it is indistinguishable from a shop that simply
        // never opens. Stored as no hours at all, which at least behaves the
        // way it did before anyone touched this form.
        $this->merge([
            'hours' => array_filter($week) === [] ? null : $week,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent.regex' => 'Pick a colour in the form #1A70F5.',
            'hours.*.open.date_format' => 'Use a time like 07:00.',
            'hours.*.close.date_format' => 'Use a time like 18:00.',
        ];
    }
}
