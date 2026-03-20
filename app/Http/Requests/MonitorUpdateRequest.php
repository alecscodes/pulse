<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMonitorRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MonitorUpdateRequest extends FormRequest
{
    use ValidatesMonitorRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return $this->monitorRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withMonitorValidator($validator);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareMonitorForValidation();
    }

    public function validated($key = null, $default = null): array
    {
        return $this->normalizeMonitorValidated(parent::validated($key, $default));
    }
}
