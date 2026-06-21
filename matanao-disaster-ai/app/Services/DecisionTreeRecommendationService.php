<?php

namespace App\Services;

use App\Models\DamageReport;

class DecisionTreeRecommendationService
{
    public function generate(DamageReport $report): array
    {
        $report->loadMissing('affectedFamilyRecords');

        $families = max(1, $report->affectedFamilyRecords->count() ?: $report->affected_families);
        $members = $report->affectedFamilyRecords->sum('household_members');
        $members = $members > 0 ? $members : $families * 4;

        return match ($report->damage_severity) {
            'severe' => $this->severeRecommendation($families, $members, $report->affected_structures),
            'moderate' => $this->moderateRecommendation($families, $members, $report->affected_structures),
            default => $this->minorRecommendation($families, $members, $report->affected_structures),
        };
    }

    public function rules(): array
    {
        return [
            [
                'condition' => 'IF damage severity is severe',
                'logic' => 'Food packs = affected families x 2; medicine kits increase by household members; cash assistance increases by affected families and structures.',
                'output' => 'High priority assistance',
            ],
            [
                'condition' => 'IF damage severity is moderate',
                'logic' => 'Food packs = affected families x 1.5; medicine kits are based on household members; cash assistance uses a moderate multiplier.',
                'output' => 'Medium priority assistance',
            ],
            [
                'condition' => 'IF damage severity is minor',
                'logic' => 'Food packs = at least one per affected family; medicine kits and cash assistance use the lowest multiplier.',
                'output' => 'Low priority assistance',
            ],
        ];
    }

    public function defenseExplanation(): array
    {
        return [
            'Decision Tree is the main recommendation algorithm.',
            'Ollama is not trained from scratch; it receives structured disaster data and returns JSON.',
            'If Ollama is unavailable, the Decision Tree still generates food packs, medicine kits, and cash assistance.',
            'Inputs are severity, affected families, household members, affected structures, disaster type, barangay, and description.',
            'Outputs are food packs, medicine kits, cash assistance, and recommendation basis.',
        ];
    }

    protected function severeRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => $families * 2,
            'medicine_kits' => (int) ceil(max(1, $members / 10) * 1.5),
            'cash_assistance' => (float) (($families * 2000) + ($structures * 1000)),
            'basis' => 'Decision Tree: severe damage requires high priority food, medicine, and cash assistance.',
            'source' => 'decision_tree',
        ];
    }

    protected function moderateRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => (int) ceil($families * 1.5),
            'medicine_kits' => (int) ceil(max(1, $members / 10)),
            'cash_assistance' => (float) (($families * 1000) + ($structures * 500)),
            'basis' => 'Decision Tree: moderate damage requires medium priority food, medicine, and cash assistance.',
            'source' => 'decision_tree',
        ];
    }

    protected function minorRecommendation(int $families, int $members, int $structures): array
    {
        return [
            'food_packs' => $families,
            'medicine_kits' => (int) ceil(max(1, $members / 20)),
            'cash_assistance' => (float) (($families * 500) + ($structures * 250)),
            'basis' => 'Decision Tree: minor damage requires basic assistance and monitoring.',
            'source' => 'decision_tree',
        ];
    }
}
