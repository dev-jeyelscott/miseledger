<?php

namespace App\Http\Requests\Platform;

use App\Enums\ContentKind;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates the creation of a new marketing/legal content page and its
 * initial draft revision. Markdown is the only accepted authoring format.
 */
class CreateContentPageRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(ContentKind::class)],
            'key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z][a-z0-9_.]*$/',
                Rule::unique('content_pages', 'key'),
            ],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('content_pages', 'slug'),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body_markdown' => ['required', 'string', 'max:200000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = $this->input('key');
        $slug = $this->input('slug');
        $title = $this->input('title');

        $this->merge([
            'key' => is_string($key) ? Str::lower(Str::squish($key)) : $key,
            'slug' => is_string($slug) ? Str::slug($slug) : $slug,
            'title' => is_string($title) ? Str::squish($title) : $title,
        ]);
    }
}
