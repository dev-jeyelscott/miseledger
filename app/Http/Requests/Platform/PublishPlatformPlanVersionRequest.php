<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Requires an explicit operator acknowledgement that publishing never
 * reprices or moves existing subscribers, who remain pinned to their
 * current plan version.
 */
class PublishPlatformPlanVersionRequest extends FormRequest
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
            'confirm' => ['required', 'accepted'],
        ];
    }
}
