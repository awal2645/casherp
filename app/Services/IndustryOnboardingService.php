<?php

namespace App\Services;

use App\Industry;
use Illuminate\Support\Collection;

class IndustryOnboardingService
{
    public function selectableIndustries(): Collection
    {
        return Industry::query()
            ->where('is_active', true)
            ->with(['enabledFeatures' => fn ($query) => $query->orderBy('features.name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function profilePayload(Collection $industries): array
    {
        return $industries->mapWithKeys(function (Industry $industry) {
            $configuration = config('industry_onboarding.profiles.'.$industry->code, []);

            return [(string) $industry->id => [
                'id' => $industry->id,
                'code' => $industry->code,
                'name' => $industry->name,
                'audience' => $industry->description ?: ($configuration['audience'] ?? ''),
                'workspace' => $configuration['workspace'] ?? '',
                'features' => $industry->enabledFeatures->pluck('name')->values()->all(),
                'questions' => array_values($configuration['questions'] ?? []),
            ]];
        })->all();
    }

    public function sanitizeAnswers(Industry $industry, array $answers): array
    {
        $questions = config('industry_onboarding.profiles.'.$industry->code.'.questions', []);
        $sanitized = [];

        foreach ($questions as $question) {
            $key = $question['key'];
            $type = $question['type'] ?? 'boolean';
            $value = $answers[$key] ?? ($question['default'] ?? null);

            if ($type === 'boolean') {
                $sanitized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $allowedValues = collect($question['options'] ?? [])
                ->pluck('value')
                ->map(fn ($option) => (string) $option)
                ->all();

            if ($type === 'multiselect') {
                $values = is_array($value) ? $value : [$value];
                $dependsOn = $question['depends_on'] ?? null;
                if ($dependsOn) {
                    $parentValue = (string) ($sanitized[$dependsOn]
                        ?? $answers[$dependsOn]
                        ?? '');
                    $showAllFor = array_map('strval', $question['show_all_for'] ?? []);
                    $allowedValues = collect($question['options'] ?? [])
                        ->filter(function ($option) use ($parentValue, $showAllFor) {
                            return in_array($parentValue, $showAllFor, true)
                                || (string) ($option['parent'] ?? '') === $parentValue;
                        })
                        ->pluck('value')
                        ->map(fn ($option) => (string) $option)
                        ->all();
                }
                $sanitized[$key] = collect($values)
                    ->filter(fn ($option) => is_scalar($option))
                    ->map(fn ($option) => (string) $option)
                    ->filter(fn ($option) => in_array($option, $allowedValues, true))
                    ->unique()
                    ->values()
                    ->all();
                continue;
            }

            $value = is_scalar($value) ? (string) $value : '';
            $sanitized[$key] = in_array($value, $allowedValues, true)
                ? $value
                : (string) ($question['default'] ?? '');
        }

        return $sanitized;
    }

    public function questions(Industry $industry): array
    {
        return array_values(config(
            'industry_onboarding.profiles.'.$industry->code.'.questions',
            []
        ));
    }

    public function allowedQuestionKeys(Industry $industry): array
    {
        return collect(config('industry_onboarding.profiles.'.$industry->code.'.questions', []))
            ->pluck('key')
            ->all();
    }
}
