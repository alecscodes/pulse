<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesMonitorRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    protected function monitorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:website,ip'],
            'url' => [
                'required',
                'string',
                Rule::when(
                    $this->input('type') === 'ip',
                    'ip',
                    'url',
                ),
            ],
            'method' => ['required', 'in:GET,POST'],
            'headers' => ['nullable', 'array'],
            'headers.*.key' => ['required_with:headers', 'string'],
            'headers.*.value' => ['required_with:headers', 'string'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.key' => ['required_with:parameters', 'string'],
            'parameters.*.value' => ['required_with:parameters', 'string'],
            'enable_content_validation' => ['boolean'],
            'expected_title' => ['nullable', 'string', 'max:255'],
            'expected_content' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'check_interval' => ['required', 'integer', 'min:30', 'max:3600'],
        ];
    }

    protected function prepareMonitorForValidation(): void
    {
        $this->merge([
            'enable_content_validation' => $this->boolean('enable_content_validation'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeMonitorValidated(array $validated): array
    {
        if (isset($validated['headers'])) {
            $validated['headers'] = $this->normalizeKeyValuePairs($validated['headers']);
        }

        if (isset($validated['parameters'])) {
            $validated['parameters'] = $this->normalizeKeyValuePairs($validated['parameters']);
        }

        return $validated;
    }

    protected function withMonitorValidator(Validator $validator): void
    {
        $validator->sometimes(
            'expected_title',
            'required_without:expected_content',
            fn ($input) => ! empty($input->enable_content_validation),
        );
        $validator->sometimes(
            'expected_content',
            'required_without:expected_title',
            fn ($input) => ! empty($input->enable_content_validation),
        );
    }

    /**
     * @param  array<int, array{key?: string, value?: string}>|null  $pairs
     * @return array<string, string>
     */
    protected function normalizeKeyValuePairs(?array $pairs): array
    {
        if (empty($pairs)) {
            return [];
        }

        $normalized = [];

        foreach ($pairs as $pair) {
            if (isset($pair['key'], $pair['value']) && $pair['key'] !== '') {
                $normalized[$pair['key']] = $pair['value'];
            }
        }

        return $normalized;
    }
}
