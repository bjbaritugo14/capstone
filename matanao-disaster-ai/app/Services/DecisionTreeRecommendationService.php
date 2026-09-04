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

        $severityCounts = $this->severityCounts($report);
        $families = max(1, array_sum($severityCounts));
        $membersBySeverity = $this->membersBySeverity($report, $severityCounts);
        $members = array_sum($membersBySeverity);
        $structures = max(0, (int) $report->affected_structures);
        $severity = $this->highestSeverity($severityCounts);
        $disasterType = trim((string) $report->disaster_type) ?: 'Other';
        $barangay = $report->location?->barangay?->barangay_name ?? 'Unassigned';
        $description = $this->descriptionText($report);

        $baseline = $this->combinedRecommendation($severityCounts, $membersBySeverity, $structures, $severity);
        $context = $this->contextAdjustments($disasterType, $description);
        $priority = $this->priority($severity, $families, $structures);
        $severitySummary = $this->severitySummary($severityCounts);
        $signals = $context['signals'] === []
            ? 'no additional contextual escalation'
            : implode('; ', $context['signals']);
        $foodPacks = max(1, (int) ceil($baseline['food_packs'] * $context['food_multiplier']));
        $medicineKits = $context['medical_needs']
            ? max(1, (int) ceil($baseline['medicine_kits'] * $context['medicine_multiplier']))
            : 0;
        $cashAssistance = round(max(0, $baseline['cash_assistance'] * $context['cash_multiplier']), 2);

        $inputs = [
            'damage_severity' => $severity,
            'severity_counts' => $severityCounts,
            'affected_families' => $families,
            'household_members' => $members,
            'affected_structures' => $structures,
            'disaster_type' => $disasterType,
            'barangay' => $barangay,
            'description' => $description,
            'priority' => $priority,
            'context_signals' => $context['signals'],
            'medical_needs' => $context['medical_needs'],
            'output_scope' => 'full_assistance',
        ];

        return [
            'food_packs' => $foodPacks,
            'medicine_kits' => $medicineKits,
            'cash_assistance' => $cashAssistance,
            'basis' => "Decision Tree: {$priority} priority {$disasterType} response for Barangay {$barangay}; {$severity} effective damage from {$severitySummary}, {$families} affected families, {$members} household members, and {$structures} affected structures. Context: {$signals}.",
            'source' => 'decision_tree',
            'inputs' => $inputs,
        ];
    }

    public function rules(): array
    {
        return [
            [
                'condition' => 'IF affected-family severity is severe',
                'logic' => 'Food packs = severe families x '.$this->settings->format('recommendation_food_multiplier_severe', $this->settings->float('recommendation_food_multiplier_severe')).'; medicine kits are calculated only when the report or family description contains medical-need terms; cash assistance uses configured severe family rates. Report-level structure assistance uses the highest family severity present.',
                'output' => 'High priority assistance',
            ],
            [
                'condition' => 'IF affected-family severity is moderate',
                'logic' => 'Food packs = moderate families x '.$this->settings->format('recommendation_food_multiplier_moderate', $this->settings->float('recommendation_food_multiplier_moderate')).'; medicine kits are calculated only when the report or family description contains medical-need terms; cash assistance uses the configured moderate family rates.',
                'output' => 'Medium priority assistance',
            ],
            [
                'condition' => 'IF affected-family severity is minor',
                'logic' => 'Food packs = minor families x '.$this->settings->format('recommendation_food_multiplier_minor', $this->settings->float('recommendation_food_multiplier_minor')).'; medicine kits are zero unless medical-need terms are present; cash assistance uses the lowest configured family rates.',
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
            [
                'condition' => 'IF description contains injured, wound, medical, medicine, hospital, sick, nasamdan, naangol, samad, medikal, pangmedikal, tambal, ospital, masakiton, or nagsakit',
                'logic' => 'The Decision Tree increases medicine-kit planning while food packs and cash assistance are still calculated using the normal severity, family, and structure rules.',
                'output' => 'Medical-needs assistance with medicine kits added while food packs and cash assistance are preserved',
            ],
        ];
    }

    public function defenseExplanation(): array
    {
        return [
            'Decision Tree is the main recommendation algorithm.',
            'Ollama is not trained from scratch; it receives structured disaster data and returns JSON.',
            'If Ollama is unavailable, the Decision Tree still generates food packs, cash assistance, and medicine kits only when medical-need terms are present.',
            'Inputs are affected-family severity counts, effective severity, affected families, household members, affected structures, disaster type, barangay, and description; all are stored with the generated result.',
            'Outputs are food packs, medicine kits, cash assistance, and recommendation basis; medicine kits are zero unless medical-need descriptions are detected.',
            'Recommendation multipliers and thresholds can be managed by administrators in System Settings.',
        ];
    }

    protected function descriptionText(DamageReport $report): string
    {
        return collect([(string) $report->description])
            ->merge($report->affectedFamilyRecords->pluck('description')->all())
            ->filter(fn (mixed $description): bool => trim((string) $description) !== '')
            ->map(fn (mixed $description): string => trim((string) $description))
            ->implode(' ');
    }

    protected function severityCounts(DamageReport $report): array
    {
        $counts = $this->emptySeverityBuckets();
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        if ($report->affectedFamilyRecords->isEmpty()) {
            $counts[$fallbackSeverity] = max(1, (int) $report->affected_families);

            return $counts;
        }

        foreach ($report->affectedFamilyRecords as $family) {
            $counts[$this->normalizedSeverity($family->damage_severity, $fallbackSeverity)]++;
        }

        return $counts;
    }

    protected function membersBySeverity(DamageReport $report, array $severityCounts): array
    {
        $members = $this->emptySeverityBuckets();
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        foreach ($report->affectedFamilyRecords as $family) {
            $severity = $this->normalizedSeverity($family->damage_severity, $fallbackSeverity);
            $members[$severity] += max(0, (int) $family->household_members);
        }

        if (array_sum($members) > 0) {
            return $members;
        }

        $defaultHouseholdSize = $this->settings->integer('recommendation_default_household_size');

        foreach ($severityCounts as $severity => $count) {
            $members[$severity] = $count * $defaultHouseholdSize;
        }

        return $members;
    }

    protected function combinedRecommendation(array $severityCounts, array $membersBySeverity, int $structures, string $effectiveSeverity): array
    {
        $baseline = [
            'food_packs' => 0,
            'medicine_kits' => 0,
            'cash_assistance' => 0.0,
        ];

        foreach (['minor', 'moderate', 'severe'] as $severity) {
            $families = (int) $severityCounts[$severity];

            if ($families <= 0) {
                continue;
            }

            $recommendation = match ($severity) {
                'severe' => $this->severeRecommendation($families, (int) $membersBySeverity[$severity], 0),
                'moderate' => $this->moderateRecommendation($families, (int) $membersBySeverity[$severity], 0),
                default => $this->minorRecommendation($families, (int) $membersBySeverity[$severity], 0),
            };

            $baseline['food_packs'] += $recommendation['food_packs'];
            $baseline['medicine_kits'] += $recommendation['medicine_kits'];
            $baseline['cash_assistance'] += $recommendation['cash_assistance'];
        }

        $baseline['cash_assistance'] += $this->structureCashAssistance($structures, $effectiveSeverity);

        return $baseline;
    }

    protected function structureCashAssistance(int $structures, string $severity): float
    {
        return (float) match ($severity) {
            'severe' => $structures * $this->settings->integer('recommendation_cash_per_structure_severe'),
            'moderate' => $structures * $this->settings->integer('recommendation_cash_per_structure_moderate'),
            default => $structures * $this->settings->integer('recommendation_cash_per_structure_minor'),
        };
    }

    protected function highestSeverity(array $severityCounts): string
    {
        foreach (['severe', 'moderate', 'minor'] as $severity) {
            if (($severityCounts[$severity] ?? 0) > 0) {
                return $severity;
            }
        }

        return 'minor';
    }

    protected function normalizedSeverity(?string $severity, string $fallback = 'minor'): string
    {
        return in_array($severity, ['minor', 'moderate', 'severe'], true) ? $severity : $fallback;
    }

    protected function emptySeverityBuckets(): array
    {
        return [
            'minor' => 0,
            'moderate' => 0,
            'severe' => 0,
        ];
    }

    protected function severitySummary(array $severityCounts): string
    {
        return collect(['minor', 'moderate', 'severe'])
            ->map(fn (string $severity): string => $severityCounts[$severity].' '.$severity)
            ->implode(', ');
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
        $medicalNeeds = false;
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

        if ($this->containsAny($normalizedDescription, $this->medicalDescriptionTerms())) {
            $medicineMultiplier *= 1.25;
            $medicalNeeds = true;
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
            'medical_needs' => $medicalNeeds,
        ];
    }

    protected function medicalDescriptionTerms(): array
    {
        return [
            'injur',
            'wound',
            'medical',
            'medicine',
            'hospital',
            'sick',
            'nasamdan',
            'naangol',
            'samad',
            'medikal',
            'pangmedikal',
            'tambal',
            'ospital',
            'masakiton',
            'nagsakit',
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
