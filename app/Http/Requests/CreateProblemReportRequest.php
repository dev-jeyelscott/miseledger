<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateProblemReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'screenshots' => ['nullable', 'array', 'max:5'],
            'screenshots.*' => ['file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->has('title') ? trim($this->string('title')->value()) : null,
            'description' => trim($this->string('description')->value()),
        ]);
    }
}
