<?php

namespace App\Services;

use App\Models\DamageReport;

class DecisionTreeRecommendationService
{
    public function __construct(
        protected SystemSettingService $settings,
    ) {}

    public function generate(DamageReport $report): array
    {
        $report->loadMissing(['affectedFamilyRecords', 'location.barangay']);

        $families = max(1, $report->affectedFamilyRecords->count() ?: $report->affected_families);
        $members = $report->affectedFamilyRecords->sum('household_members');
        $members = $members > 0 ? $members : $families * $this->settings->integer('recommendation_default_household_size');
        $structures = max(0, (int) $report->affected_structures);
        $severity = in_array($report->damage_severity, ['minor', 'moderate', 'severe'], true)
            ? $report->damage_severity
            : 'minor';
        $disasterType = trim((string) $report->disaster_type) ?: 'Other';
        $barangay = $report->location?->barangay?->barangay_name ?? 'Unassigned';
        $description = trim((string) $report->description);

        $baseline = match ($severity) {
            'severe' => $this->severeRecommendation($families, $members, $structures),
            'moderate' => $this->moderateRecommendation($families, $members, $structures),
            default => $this->minorRecommendation($families, $members, $structures),
        };
        $context = $this->contextAdjustments($disasterType, $description);
        $priority = $this->priority($severity, $families, $structures);
        $signals = $context['signals'] === []
            ? 'no additional contextual escalation'
            : implode('; ', $context['signals']);

        $inputs = [
            'damage_severity' => $severity,
            'affected_families' => $families,
            'household_members' => $members,
            'affected_structures' => $structures,
            'disaster_type' => $disasterType,
            'barangay' => $barangay,
            'description' => $description,
            'priority' => $priority,
            'context_signals' => $context['signals'],
        ];

        return [
            'food_packs' => max(1, (int) ceil($baseline['food_packs'] * $context['food_multiplier'])),
            'medicine_kits' => max(1, (int) ceil($baseline['medicine_kits'] * $context['medicine_multiplier'])),
            'cash_assistance' => round(max(0, $baseline['cash_assistance'] * $context['cash_multiplier']), 2),
            'basis' => "Decision Tree: {$priority} priority {$disasterType} response for Barangay {$barangay}; {$severity} damage, {$families} affected families, {$members} household members, and {$structures} affected structures. Context: {$signals}.",
            'source' => 'decision_tree',
            'inputs' => $inputs,
        ];
    }

    public function rules(): array
    {
        return [
            [
                'condition' => 'IF damage severity is severe',
                'logic' => 'Food packs = affected families x '.$this->settings->format('recommendation_food_multiplier_severe', $this->settings->float('recommendation_food_multiplier_severe')).'; medicine kits use household members with a severe multiplier; cash assistance uses configured severe family and structure rates.',
                'output' => 'High priority assistance',
            ],
            [
                'condition' => 'IF damage severity is moderate',
                'logic' => 'Food packs = affected families x '.$this->settings->format('recommendation_food_multiplier_moderate', $this->settings->float('recommendation_food_multiplier_moderate')).'; medicine kits are based on household members; cash assistance uses the configured moderate family and structure rates.',
                'output' => 'Medium priority assistance',
            ],
            [
                'condition' => 'IF damage severity is minor',
                'logic' => 'Food packs = affected families x '.$this->settings->format('recommendation_food_multiplier_minor', $this->settings->float('recommendation_food_multiplier_minor')).'; medicine kits and cash assistance use the lowest configured rates.',
                'output' => 'Low priority assistance',
            ],
            [
                'condition' => 'IF family or structure totals reach configured response thresholds',
                'logic' => 'The Decision Tree raises the recorded priority to focused or immediate response while preserving the severity-based assistance formulas.',
                'output' => 'Scale-based priority escalation',
            ],
            [
                'condition' => 'IF disaster type or description indicates food-access, medical, or major-damage needs',
                'logic' => 'Predefined contextual branches apply modest food, medicine, or cash multipliers and record the exact signals in the recommendation basis.',
                'output' => 'Context-adjusted assistance with an auditable basis',
            ],
        ];
    }

    public function defenseExplanation(): array
    {
        return [
            'Decision Tree is the main recommendation algorithm.',
            'Ollama is not trained from scratch; it receives structured disaster data and returns JSON.',
            'If Ollama is unavailable, the Decision Tree still generates food packs, medicine kits, and cash assistance.',
            'Inputs are severity, affected families, household members, affected structures, disaster type, barangay, and description; all are stored with the generated result.',
            'Outputs are food packs, medicine kits, cash assistance, and recommendation basis.',
            'Recommendation multipliers and thresholds can be managed by administrators in System Settings.',
        ];
    }

    protected function severeRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => (int) ceil($families * $this->settings->float('recommendation_food_multiplier_severe')),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_severe'))) * $this->settings->float('recommendation_medicine_multiplier_severe')),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_severe')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_severe'))),
        ];
    }

    protected function moderateRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => (int) ceil($families * $this->settings->float('recommendation_food_multiplier_moderate')),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_moderate')))),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_moderate')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_moderate'))),
        ];
    }

    protected function minorRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => max(1, (int) ceil($families * $this->settings->float('recommendation_food_multiplier_minor'))),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_minor')))),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_minor')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_minor'))),
        ];
    }

    protected function priority(string $severity, int $families, int $structures): string
    {
        if (
            $severity === 'severe'
            || $families >= $this->settings->integer('impact_immediate_family_threshold')
            || $structures >= $this->settings->integer('impact_immediate_structure_threshold')
        ) {
            return 'high';
        }

        if (
            $severity === 'moderate'
            || $families >= $this->settings->integer('impact_focused_family_threshold')
            || $structures >= $this->settings->integer('impact_focused_structure_threshold')
        ) {
            return 'medium';
        }

        return 'low';
    }

    protected function contextAdjustments(string $disasterType, string $description): array
    {
        $foodMultiplier = 1.0;
        $medicineMultiplier = 1.0;
        $cashMultiplier = 1.0;
        $signals = [];
        $normalizedType = strtolower($disasterType);
        $normalizedDescription = strtolower($description);

        if (in_array($normalizedType, ['flood', 'typhoon'], true)) {
            $foodMultiplier *= 1.10;
            $signals[] = $disasterType.' food-access branch';
        }

        if (in_array($normalizedType, ['earthquake', 'landslide', 'fire'], true)) {
            $medicineMultiplier *= 1.15;
            $signals[] = $disasterType.' medical-readiness branch';
        }

        if ($this->containsAny($normalizedDescription, ['evacuat', 'displaced', 'isolated', 'stranded'])) {
            $foodMultiplier *= 1.10;
            $signals[] = 'description indicates evacuation or disrupted access';
        }

        if ($this->containsAny($normalizedDescription, ['injur', 'wound', 'medical', 'medicine', 'hospital', 'sick'])) {
            $medicineMultiplier *= 1.25;
            $signals[] = 'description indicates medical needs';
        }

        if ($this->containsAny($normalizedDescription, ['destroyed', 'collapsed', 'uninhabitable', 'total damage'])) {
            $cashMultiplier *= 1.10;
            $signals[] = 'description indicates major structural damage';
        }

        return compact('foodMultiplier', 'medicineMultiplier', 'cashMultiplier', 'signals') + [
            'food_multiplier' => $foodMultiplier,
            'medicine_multiplier' => $medicineMultiplier,
            'cash_multiplier' => $cashMultiplier,
        ];
    }

    protected function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
