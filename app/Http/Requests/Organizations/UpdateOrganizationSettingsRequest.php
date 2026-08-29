<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use App\Models\User;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    private const CURRENCIES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BOV',
        'BRL', 'BSD', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHE', 'CHF',
        'CHW', 'CLF', 'CLP', 'CNY', 'COP', 'COU', 'CRC', 'CUC', 'CUP', 'CVE',
        'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD',
        'FKP', 'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD',
        'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK',
        'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD',
        'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD', 'MAD', 'MDL',
        'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN',
        'MXV', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR',
        'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD',
        'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLE',
        'SOS', 'SRD', 'SSP', 'STN', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT',
        'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'USN',
        'UYI', 'UYU', 'UYW', 'UZS', 'VED', 'VES', 'VND', 'VUV', 'WST', 'XAF',
        'XCD', 'XCG', 'XDR', 'XOF', 'XPF', 'XSU', 'XUA', 'YER', 'ZAR', 'ZMW',
        'ZWG',
    ];

    /**
     * Return the same ISO currency whitelist used by settings validation.
     *
     * @return list<string>
     */
    public static function currencyOptions(): array
    {
        return self::CURRENCIES;
    }

    /**
     * Return the IANA timezone identifiers accepted by the settings form.
     *
     * @return list<string>
     */
    public static function timezoneOptions(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');

        return $user instanceof User
            && $organization instanceof Organization
            && $user->can('update', $organization);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'alpha_dash:ascii',
                Rule::unique('organizations', 'slug')->ignore(
                    $this->route('organization'),
                ),
            ],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(self::currencyOptions()),
            ],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * Normalize organization settings before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $slug = $this->input('slug');
        $timezone = $this->input('timezone');
        $currency = $this->input('currency');

        $normalized = [];

        if (is_string($name)) {
            $normalized['name'] = trim($name);
        }

        if (is_string($slug)) {
            $normalized['slug'] = Str::lower(trim($slug));
        }

        if (is_string($timezone)) {
            $normalized['timezone'] = trim($timezone);
        }

        if (is_string($currency)) {
            $normalized['currency'] = Str::upper(trim($currency));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
