<?php

namespace App\Http\Requests\Platform;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Models\User;
use App\Support\Billing\FeatureCode;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a draft commercial version against the application-owned
 * feature code and usage limit registries. Arbitrary feature-code or
 * limit-key text is never accepted.
 */
class SavePlatformPlanVersionRequest extends FormRequest
{
    /**
     * Defense in depth alongside the route-level platform.admin middleware.
     */
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
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'tier' => ['required', 'integer', 'min:1', 'max:1000'],
            'feature_codes' => ['array'],
            'feature_codes.*' => [Rule::in(FeatureCode::all())],
            'limits' => ['required', 'array'],
            'prices' => ['array'],
            'prices.*.provider' => ['required', Rule::enum(BillingProvider::class)],
            'prices.*.collection_method' => ['required', Rule::enum(BillingCollectionMethod::class)],
            'prices.*.interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'prices.*.currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'prices.*.amount_minor' => ['required', 'integer', 'min:1'],
        ];

        foreach (UsageLimitKey::all() as $key) {
            $rules["limits.{$key}"] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * Require every known usage limit to be declared exactly once, since an
     * omitted limit fails closed at runtime rather than meaning unlimited.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $limits = (array) $this->input('limits', []);
            $expectedKeys = UsageLimitKey::all();
            sort($expectedKeys);

            $actualKeys = array_keys($limits);
            sort($actualKeys);

            if ($actualKeys !== $expectedKeys) {
                $validator->errors()->add(
                    'limits',
                    'Every usage limit must be set exactly once.',
                );
            }

            $featureCodes = array_values(
                array_filter(
                    (array) $this->input('feature_codes', []),
                    'is_string',
                ),
            );

            if (count($featureCodes) !== count(array_unique($featureCodes))) {
                $validator->errors()->add(
                    'feature_codes',
                    'Feature codes must not repeat.',
                );
            }

            $prices = (array) $this->input('prices', []);
            $tuples = [];

            foreach ($prices as $price) {
                if (! is_array($price)) {
                    continue;
                }

                $tuple = implode('|', [
                    $price['provider'] ?? '',
                    $price['collection_method'] ?? '',
                    $price['interval'] ?? '',
                    $price['currency'] ?? '',
                ]);

                if (in_array($tuple, $tuples, true)) {
                    $validator->errors()->add(
                        'prices',
                        'Each provider, collection method, interval, and currency combination may appear only once.',
                    );

                    break;
                }

                $tuples[] = $tuple;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name) ? Str::squish($name) : $name,
            'feature_codes' => array_values(
                array_filter(
                    (array) $this->input('feature_codes', []),
                    'is_string',
                ),
            ),
        ]);
    }
}
