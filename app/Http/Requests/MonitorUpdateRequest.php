<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MonitorUpdateRequest extends FormRequest
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
            'type' => ['required', 'in:website,ip'],
            'url' => ['required', 'string', 'url'],
            'method' => ['required', 'in:GET,POST'],
            'headers' => ['nullable', 'array'],
            'headers.*.key' => ['required_with:headers', 'string'],
            'headers.*.value' => ['required_with:headers', 'string'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.key' => ['required_with:parameters', 'string'],
            'parameters.*.value' => ['required_with:parameters', 'string'],
            'enable_content_validation' => ['boolean'],
            'expected_title' => ['nullable', 'required_if:enable_content_validation,true', 'string', 'max:255'],
            'expected_content' => ['nullable', 'required_if:enable_content_validation,true', 'string'],
            'is_active' => ['boolean'],
            'check_interval' => ['required', 'integer', 'min:30', 'max:3600'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_content_validation' => $this->boolean('enable_content_validation'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * Get validated data with normalized headers and parameters.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        if (isset($validated['headers'])) {
            $validated['headers'] = $this->normalizeKeyValuePairs($validated['headers']);
        }

        if (isset($validated['parameters'])) {
            $validated['parameters'] = $this->normalizeKeyValuePairs($validated['parameters']);
        }

        return $validated;
    }

    /**
     * Normalize key-value pairs from frontend format.
     */
    protected function normalizeKeyValuePairs(?array $pairs): array
    {
        if (empty($pairs)) {
            return [];
        }

        $normalized = [];

        foreach ($pairs as $pair) {
            if (isset($pair['key']) && isset($pair['value']) && ! empty($pair['key'])) {
                $normalized[$pair['key']] = $pair['value'];
            }
        }

        return $normalized;
    }
}
