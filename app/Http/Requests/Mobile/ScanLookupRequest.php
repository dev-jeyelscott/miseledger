<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ScanLookupRequest extends FormRequest
{
    /** Authorization is enforced by the InventoryView gate in the controller. */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }
}
