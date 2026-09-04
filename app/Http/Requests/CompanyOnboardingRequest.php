<?php

namespace App\Http\Requests;

use App\Industry;
use App\Services\IndustryOnboardingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CompanyOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];
        foreach ([
            'name', 'country', 'state', 'city', 'zip_code', 'landmark',
            'website', 'mobile', 'alternate_number', 'surname', 'first_name',
            'last_name', 'username', 'email',
        ] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }

        if (! empty($trimmed['email'])) {
            $trimmed['email'] = mb_strtolower($trimmed['email']);
        }

        if (! empty($trimmed['website'])
            && ! preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $trimmed['website'])) {
            $trimmed['website'] = 'https://'.$trimmed['website'];
        }

        $this->merge($trimmed);
    }

    protected function companyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry_id' => [
                'required',
                'integer',
                Rule::exists('industries', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'start_date' => ['nullable', 'date_format:'.config('constants.default_date_format')],
            'country' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'landmark' => ['required', 'string', 'max:255'],
            'time_zone' => ['required', 'timezone'],
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
            'accounting_method' => ['required', Rule::in(['fifo', 'lifo'])],
            'website' => ['nullable', 'url', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'alternate_number' => ['nullable', 'string', 'max:30'],
            'onboarding' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('industry_id')) {
                return;
            }

            $industry = Industry::whereKey($this->integer('industry_id'))
                ->where('is_active', true)
                ->first();
            if (empty($industry)) {
                return;
            }

            $answers = (array) $this->input('onboarding', []);
            $onboarding = app(IndustryOnboardingService::class);
            $questions = $onboarding->questions($industry);
            $allowedKeys = $onboarding->allowedQuestionKeys($industry);
            $unknownKeys = array_diff(array_keys($answers), $allowedKeys);
            if (! empty($unknownKeys)) {
                $validator->errors()->add('onboarding', 'One or more setup answers are not valid for the selected industry.');
            }

            foreach ($questions as $question) {
                $key = $question['key'];
                $type = $question['type'] ?? 'boolean';
                $value = $answers[$key] ?? null;
                $field = 'onboarding.'.$key;

                if ($type === 'boolean') {
                    if (! is_null($value)
                        && ! in_array((string) $value, ['0', '1'], true)
                        && ! is_bool($value)) {
                        $validator->errors()->add($field, 'Choose yes or no for '.$question['label'].'.');
                    }
                    continue;
                }

                $allowedValues = collect($question['options'] ?? [])
                    ->pluck('value')
                    ->map(fn ($option) => (string) $option)
                    ->all();

                if ($type === 'multiselect') {
                    $values = is_array($value) ? $value : [];
                    if (! empty($question['required'])
                        && count($values) < (int) ($question['min'] ?? 1)) {
                        $validator->errors()->add($field, 'Select at least one option for '.$question['label'].'.');
                    }
                    if (count($values) > 20
                        || collect($values)->contains(fn ($option) => ! is_scalar($option) || ! in_array((string) $option, $allowedValues, true))) {
                        $validator->errors()->add($field, 'One or more selected options are not valid.');
                    }

                    $dependsOn = $question['depends_on'] ?? null;
                    if ($dependsOn) {
                        $parentValue = (string) ($answers[$dependsOn] ?? '');
                        $showAllFor = array_map('strval', $question['show_all_for'] ?? []);
                        $allowedForParent = collect($question['options'] ?? [])
                            ->filter(function ($option) use ($parentValue, $showAllFor) {
                                return in_array($parentValue, $showAllFor, true)
                                    || (string) ($option['parent'] ?? '') === $parentValue;
                            })
                            ->pluck('value')
                            ->map(fn ($option) => (string) $option)
                            ->all();

                        if (collect($values)->contains(
                            fn ($option) => is_scalar($option)
                                && ! in_array((string) $option, $allowedForParent, true)
                        )) {
                            $validator->errors()->add(
                                $field,
                                'Select options that match the chosen business category.'
                            );
                        }
                    }
                    continue;
                }

                if (! empty($question['required']) && (! is_scalar($value) || trim((string) $value) === '')) {
                    $validator->errors()->add($field, 'Choose an option for '.$question['label'].'.');
                } elseif (! is_null($value)
                    && (! is_scalar($value) || ! in_array((string) $value, $allowedValues, true))) {
                    $validator->errors()->add($field, 'The selected option is not valid.');
                }
            }

            if (in_array($industry->code, ['restaurant_food_service', 'hotel_with_restaurant'], true)) {
                if (in_array('service_channels', $allowedKeys, true)) {
                    // The generic typed-question validator above already
                    // checks the required multiselect and its allowed values.
                    return;
                }

                $serviceKeys = array_intersect(['dine_in', 'takeaway', 'delivery', 'bar_or_catering', 'room_service'], $allowedKeys);
                $hasLegacyServiceMode = collect($serviceKeys)->contains(
                    fn ($key) => filter_var($answers[$key] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
                if (! in_array('service_channels', $allowedKeys, true) && ! $hasLegacyServiceMode) {
                    $validator->errors()->add('onboarding', 'Select at least one restaurant service mode.');
                }
            }
        });
    }
}
