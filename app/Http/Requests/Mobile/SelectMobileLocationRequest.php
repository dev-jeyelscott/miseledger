<?php

namespace App\Http\Requests\Mobile;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SelectMobileLocationRequest extends FormRequest
{
    /** Any authenticated org member may select an accessible location. */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Validate the chosen location belongs to the active organization and
     * is active, and that `next` is a safe same-origin `/mobile` path.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organization = $this->attributes->get('activeOrganization');
        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : 0;

        return [
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('active', true),
            ],
            'next' => [
                'nullable',
                'string',
                'starts_with:/mobile',
            ],
        ];
    }

    /** Resolve the validated redirect target, defaulting to `mobile.home`. */
    public function safeNext(): string
    {
        $next = $this->validated('next');

        return is_string($next) && $next !== '' ? $next : route('mobile.home', absolute: false);
    }
}
