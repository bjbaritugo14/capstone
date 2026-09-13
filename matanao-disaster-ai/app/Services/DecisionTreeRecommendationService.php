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

        $medicalSeverityCounts = $this->medicalSeverityCounts($report, $severityCounts);
        $medicalNeeds = array_sum($medicalSeverityCounts) > 0;
        $baseline = $this->combinedRecommendation($severityCounts, $membersBySeverity, $structures, $severity);
        $baseline['food_packs'] = $this->foodRecommendationTotal($report, $severityCounts, $membersBySeverity);
        $baseline['medicine_kits'] = $this->medicineRecommendationTotal(
            $medicalSeverityCounts,
            $this->medicalMembersBySeverity($report, $medicalSeverityCounts),
        );
        $context = $this->contextAdjustments($disasterType, $description, $medicalNeeds);
        $priority = $this->priority($severity, $families, $structures);
        $severitySummary = $this->severitySummary($severityCounts);
        $signals = $context['signals'] === []
            ? 'no additional contextual escalation'
            : implode('; ', $context['signals']);
        $foodPacks = max(1, (int) $baseline['food_packs']);
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
            'medical_needs' => $medicalNeeds,
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
                'logic' => 'Food packs = 3 per severe family; medicine kits are calculated only when the report or family description contains medical-need terms; cash assistance uses configured severe family and structure rates.',
                'output' => 'High priority assistance',
            ],
            [
                'condition' => 'IF affected-family severity is moderate',
                'logic' => 'Food packs follow household size: more than 8 members = 3 packs, more than 4 members = 2 packs, otherwise 1 pack; medicine kits are calculated only when medical-need terms are present; cash assistance uses the configured moderate family rates.',
                'output' => 'Medium priority assistance',
            ],
            [
                'condition' => 'IF affected-family severity is minor',
                'logic' => 'Food packs follow household size: more than 8 members = 3 packs, more than 4 members = 2 packs, otherwise 1 pack; medicine kits are zero unless medical-need terms are present; cash assistance uses the lowest configured family rates.',
                'output' => 'Low priority assistance',
            ],
            [
                'condition' => 'IF family or structure totals reach configured response thresholds',
                'logic' => 'The Decision Tree raises the recorded priority to focused or immediate response while preserving the severity-based assistance formulas.',
                'output' => 'Scale-based priority escalation',
            ],
            [
                'condition' => 'IF disaster type or description indicates food-access, medical, or major-damage needs',
                'logic' => 'Predefined contextual branches apply medicine or cash multipliers and record the exact signals in the recommendation basis. Food packs stay fixed to the household-size rule.',
                'output' => 'Context-adjusted assistance with an auditable basis',
            ],
            [
                'condition' => 'IF description contains injured, wound, medical, medicine, hospital, sick, nasamdan, naangol, samad, medikal, pangmedikal, tambal, ospital, masakiton, or nagsakit',
                'logic' => 'The Decision Tree increases medicine-kit planning while food packs stay fixed to the household-size rule and cash assistance uses the normal severity, family, and structure rules.',
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
            'Outputs are food packs, medicine kits, cash assistance, and recommendation basis; food packs use the fixed household-size rule and medicine kits are zero unless medical-need descriptions are detected.',
            'Medicine, cash, and priority thresholds can be managed by administrators in System Settings.',
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

    protected function medicalSeverityCounts(DamageReport $report, array $severityCounts): array
    {
        $counts = $this->emptySeverityBuckets();
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        if ($report->affectedFamilyRecords->isEmpty()) {
            if ($this->containsAny(strtolower((string) $report->description), $this->medicalDescriptionTerms())) {
                return $severityCounts;
            }

            return $counts;
        }

        $hasFamilyDescriptions = $report->affectedFamilyRecords
            ->contains(fn ($family): bool => trim((string) $family->description) !== '');

        foreach ($report->affectedFamilyRecords as $family) {
            $description = $hasFamilyDescriptions
                ? (string) $family->description
                : (string) $report->description;

            if (! $this->containsAny(strtolower($description), $this->medicalDescriptionTerms())) {
                continue;
            }

            $counts[$this->normalizedSeverity($family->damage_severity, $fallbackSeverity)]++;
        }

        return $counts;
    }

    protected function medicalMembersBySeverity(DamageReport $report, array $medicalSeverityCounts): array
    {
        $members = $this->emptySeverityBuckets();
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        if ($report->affectedFamilyRecords->isEmpty()) {
            $defaultHouseholdSize = $this->settings->integer('recommendation_default_household_size');

            foreach ($medicalSeverityCounts as $severity => $count) {
                $members[$severity] = $count * $defaultHouseholdSize;
            }

            return $members;
        }

        $hasFamilyDescriptions = $report->affectedFamilyRecords
            ->contains(fn ($family): bool => trim((string) $family->description) !== '');

        foreach ($report->affectedFamilyRecords as $family) {
            $description = $hasFamilyDescriptions
                ? (string) $family->description
                : (string) $report->description;

            if (! $this->containsAny(strtolower($description), $this->medicalDescriptionTerms())) {
                continue;
            }

            $severity = $this->normalizedSeverity($family->damage_severity, $fallbackSeverity);
            $members[$severity] += max(0, (int) $family->household_members);
        }

        return $members;
    }

    protected function medicineRecommendationTotal(array $severityCounts, array $membersBySeverity): int
    {
        $total = 0;

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

            $total += $recommendation['medicine_kits'];
        }

        return $total;
    }

    protected function foodRecommendationTotal(DamageReport $report, array $severityCounts, array $membersBySeverity): int
    {
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        if ($report->affectedFamilyRecords->isNotEmpty()) {
            return max(1, $report->affectedFamilyRecords->sum(function ($family) use ($fallbackSeverity): int {
                $severity = $this->normalizedSeverity($family->damage_severity, $fallbackSeverity);

                return $this->familyFoodPacks($severity, (int) $family->household_members);
            }));
        }

        $total = 0;

        foreach (['minor', 'moderate', 'severe'] as $severity) {
            $families = (int) $severityCounts[$severity];

            if ($families <= 0) {
                continue;
            }

            $averageMembers = (int) ceil(max(0, (int) $membersBySeverity[$severity]) / $families);

            $total += $families * $this->familyFoodPacks($severity, $averageMembers);
        }

        return max(1, $total);
    }

    protected function combinedRecommendation(array $severityCounts, array $membersBySeverity, int $structures, string $effectiveSeverity): array
    {
        $baseline = [
            'food_packs' => 0,
            'medicine_kits' => 0,
            'cash_assistance' => 0.0,
        ];
        $structureCounts = $this->structureCountsBySeverity($severityCounts, $structures, $effectiveSeverity);

        foreach (['minor', 'moderate', 'severe'] as $severity) {
            $families = (int) $severityCounts[$severity];

            if ($families <= 0 && ($structureCounts[$severity] ?? 0) <= 0) {
                continue;
            }

            $recommendation = match ($severity) {
                'severe' => $this->severeRecommendation($families, (int) $membersBySeverity[$severity], (int) $structureCounts[$severity]),
                'moderate' => $this->moderateRecommendation($families, (int) $membersBySeverity[$severity], (int) $structureCounts[$severity]),
                default => $this->minorRecommendation($families, (int) $membersBySeverity[$severity], (int) $structureCounts[$severity]),
            };

            $baseline['food_packs'] += $recommendation['food_packs'];
            $baseline['medicine_kits'] += $recommendation['medicine_kits'];
            $baseline['cash_assistance'] += $recommendation['cash_assistance'];
        }

        return $baseline;
    }

    protected function structureCountsBySeverity(array $severityCounts, int $structures, string $effectiveSeverity): array
    {
        $counts = $this->emptySeverityBuckets();
        $structures = max(0, $structures);

        if ($structures === 0) {
            return $counts;
        }

        $familyTotal = array_sum($severityCounts);

        if ($familyTotal <= 0) {
            $counts[$effectiveSeverity] = $structures;

            return $counts;
        }

        $fractions = [];
        $allocated = 0;

        foreach (['minor', 'moderate', 'severe'] as $severity) {
            $rawShare = ($structures * (int) $severityCounts[$severity]) / $familyTotal;
            $counts[$severity] = (int) floor($rawShare);
            $fractions[$severity] = $rawShare - $counts[$severity];
            $allocated += $counts[$severity];
        }

        $remaining = $structures - $allocated;
        $order = ['severe', 'moderate', 'minor'];

        usort($order, function (string $left, string $right) use ($fractions, $severityCounts): int {
            return $fractions[$right] <=> $fractions[$left]
                ?: $severityCounts[$right] <=> $severityCounts[$left]
                ?: array_search($left, ['severe', 'moderate', 'minor'], true) <=> array_search($right, ['severe', 'moderate', 'minor'], true);
        });

        for ($step = 0; $step < $remaining; $step++) {
            $counts[$order[$step % count($order)]]++;
        }

        return $counts;
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
            'food_packs' => $this->foodPacksForSeverityBucket('severe', $families, $members),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_severe'))) * $this->settings->float('recommendation_medicine_multiplier_severe')),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_severe')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_severe'))),
        ];
    }

    protected function moderateRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => $this->foodPacksForSeverityBucket('moderate', $families, $members),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_moderate')))),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_moderate')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_moderate'))),
        ];
    }

    protected function minorRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => $this->foodPacksForSeverityBucket('minor', $families, $members),
            'medicine_kits' => (int) ceil(max(1, $members / max(1, $this->settings->integer('recommendation_medicine_divisor_minor')))),
            'cash_assistance' => (float) (($families * $this->settings->integer('recommendation_cash_per_family_minor')) + ($structures * $this->settings->integer('recommendation_cash_per_structure_minor'))),
        ];
    }

    protected function foodPacksForSeverityBucket(string $severity, int $families, int $members): int
    {
        if ($families <= 0) {
            return 0;
        }

        $averageMembers = (int) ceil(max(0, $members) / $families);

        return $families * $this->familyFoodPacks($severity, $averageMembers);
    }

    protected function familyFoodPacks(string $severity, int $members): int
    {
        if ($severity === 'severe' || $members > 8) {
            return 3;
        }

        if ($members > 4) {
            return 2;
        }

        return 1;
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

    protected function contextAdjustments(string $disasterType, string $description, bool $medicalNeeds): array
    {
        $medicineMultiplier = 1.0;
        $cashMultiplier = 1.0;
        $signals = [];
        $normalizedType = strtolower($disasterType);
        $normalizedDescription = strtolower($description);

        if (in_array($normalizedType, ['flood', 'typhoon'], true)) {
            $signals[] = $disasterType.' food-access branch';
        }

        if (in_array($normalizedType, ['earthquake', 'landslide', 'fire'], true)) {
            $medicineMultiplier *= 1.15;
            $signals[] = $disasterType.' medical-readiness branch';
        }

        if ($this->containsAny($normalizedDescription, ['evacuat', 'displaced', 'isolated', 'stranded'])) {
            $signals[] = 'description indicates evacuation or disrupted access';
        }

        if ($medicalNeeds) {
            $medicineMultiplier *= 1.25;
            $signals[] = 'description indicates medical needs';
        }

        if ($this->containsAny($normalizedDescription, ['destroyed', 'collapsed', 'uninhabitable', 'total damage'])) {
            $cashMultiplier *= 1.10;
            $signals[] = 'description indicates major structural damage';
        }

        return compact('medicineMultiplier', 'cashMultiplier', 'signals') + [
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
