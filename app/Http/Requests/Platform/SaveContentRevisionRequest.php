<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates draft Markdown edits. Server validation is authoritative;
 * published revisions are never reachable through this request because the
 * controller rejects non-draft revisions before validation runs.
 */
class SaveContentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body_markdown' => ['required', 'string', 'max:200000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');

        $this->merge([
            'title' => is_string($title) ? Str::squish($title) : $title,
        ]);
    }
}
