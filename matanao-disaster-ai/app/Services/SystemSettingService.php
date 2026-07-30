<?php

namespace App\Services;

use App\Models\SystemSetting;

class SystemSettingService
{
    public const DEFINITIONS = [
        'recommendation_use_ollama_assistance' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Allow Ollama-assisted refinement',
        ],
        'recommendation_default_household_size' => [
            'type' => 'integer',
            'default' => 4,
            'label' => 'Default household size',
        ],
        'recommendation_food_multiplier_minor' => [
            'type' => 'float',
            'default' => 1.0,
            'label' => 'Minor food multiplier',
        ],
        'recommendation_food_multiplier_moderate' => [
            'type' => 'float',
            'default' => 1.5,
            'label' => 'Moderate food multiplier',
        ],
        'recommendation_food_multiplier_severe' => [
            'type' => 'float',
            'default' => 2.0,
            'label' => 'Severe food multiplier',
        ],
        'recommendation_medicine_divisor_minor' => [
            'type' => 'integer',
            'default' => 20,
            'label' => 'Minor medicine divisor',
        ],
        'recommendation_medicine_divisor_moderate' => [
            'type' => 'integer',
            'default' => 10,
            'label' => 'Moderate medicine divisor',
        ],
        'recommendation_medicine_divisor_severe' => [
            'type' => 'integer',
            'default' => 10,
            'label' => 'Severe medicine divisor',
        ],
        'recommendation_medicine_multiplier_severe' => [
            'type' => 'float',
            'default' => 1.5,
            'label' => 'Severe medicine multiplier',
        ],
        'recommendation_cash_per_family_minor' => [
            'type' => 'integer',
            'default' => 500,
            'label' => 'Minor cash per family',
        ],
        'recommendation_cash_per_family_moderate' => [
            'type' => 'integer',
            'default' => 1000,
            'label' => 'Moderate cash per family',
        ],
        'recommendation_cash_per_family_severe' => [
            'type' => 'integer',
            'default' => 2000,
            'label' => 'Severe cash per family',
        ],
        'recommendation_cash_per_structure_minor' => [
            'type' => 'integer',
            'default' => 250,
            'label' => 'Minor cash per structure',
        ],
        'recommendation_cash_per_structure_moderate' => [
            'type' => 'integer',
            'default' => 500,
            'label' => 'Moderate cash per structure',
        ],
        'recommendation_cash_per_structure_severe' => [
            'type' => 'integer',
            'default' => 1000,
            'label' => 'Severe cash per structure',
        ],
        'impact_focused_family_threshold' => [
            'type' => 'integer',
            'default' => 30,
            'label' => 'Focused validation family threshold',
        ],
        'impact_focused_structure_threshold' => [
            'type' => 'integer',
            'default' => 15,
            'label' => 'Focused validation structure threshold',
        ],
        'impact_immediate_family_threshold' => [
            'type' => 'integer',
            'default' => 100,
            'label' => 'Immediate response family threshold',
        ],
        'impact_immediate_structure_threshold' => [
            'type' => 'integer',
            'default' => 50,
            'label' => 'Immediate response structure threshold',
        ],
    ];

    protected ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = SystemSetting::query()
            ->get()
            ->keyBy('key');

        $this->resolved = collect(self::DEFINITIONS)
            ->mapWithKeys(function (array $definition, string $key) use ($stored): array {
                $value = $stored->get($key)?->value ?? $definition['default'];

                return [$key => $this->cast($definition['type'], $value)];
            })
            ->all();

        return $this->resolved;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFINITIONS[$key]['default'] ?? null;
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function boolean(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function subset(array $keys): array
    {
        $values = $this->all();

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $values[$key]])
            ->all();
    }

    public function label(string $key): string
    {
        return self::DEFINITIONS[$key]['label'] ?? $key;
    }

    public function format(string $key, mixed $value): string
    {
        $type = self::DEFINITIONS[$key]['type'] ?? 'string';

        return match ($type) {
            'boolean' => (bool) $value ? 'Enabled' : 'Disabled',
            'float' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    public function updateMany(array $values): void
    {
        $timestamp = now();
        $payload = [];

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }

            $payload[] = [
                'key' => $key,
                'value' => $this->normalize(self::DEFINITIONS[$key]['type'], $value),
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ];
        }

        if ($payload !== []) {
            SystemSetting::query()->upsert($payload, ['key'], ['value', 'updated_at']);
        }

        $this->resolved = null;
    }

    protected function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    protected function normalize(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => (bool) $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'float' => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.'),
            default => (string) $value,
        };
    }
}
