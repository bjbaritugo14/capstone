<?php

namespace App\Http\Controllers;

use App\Models\ResourceRecommendation;
use App\Services\DecisionTreeRecommendationService;
use Illuminate\View\View;

class AdminRecommendationHistoryController extends Controller
{
    public function index(DecisionTreeRecommendationService $decisionTree): View
    {
        $recommendations = ResourceRecommendation::query()
            ->with(['barangay', 'report.location.barangay', 'report.affectedFamilyRecords', 'generator'])
            ->latest('generated_at')
            ->get()
            ->map(function (ResourceRecommendation $recommendation) {
                $report = $recommendation->report;
                $families = $report?->affectedFamilyRecords->count() ?: ($report?->affected_families ?? 0);
                $members = $report?->affectedFamilyRecords->sum('household_members') ?: ($families * 4);
                $severity = $report?->damage_severity ?? 'minor';

                return [
                    'id' => 'REC-'.str_pad((string) $recommendation->recommendation_id, 4, '0', STR_PAD_LEFT),
                    'report' => 'REP-'.str_pad((string) $recommendation->report_id, 4, '0', STR_PAD_LEFT),
                    'barangay' => $recommendation->barangay?->barangay_name ?? 'Unassigned',
                    'disaster_type' => $report?->disaster_type ?? 'Unknown',
                    'severity' => $this->priority($severity),
                    'status' => ucfirst($report?->status ?? 'pending'),
                    'families' => $families,
                    'members' => $members,
                    'structures' => $report?->affected_structures ?? 0,
                    'food_packs' => $recommendation->food_packs,
                    'medicine_kits' => $recommendation->medicine_kits,
                    'cash_assistance' => $recommendation->cash_assistance,
                    'generated_by' => $recommendation->generator?->full_name ?? 'Unknown user',
                    'generated_at' => $recommendation->generated_at,
                    'rule_trigger' => $this->ruleTrigger($severity, $families, $members, $report?->affected_structures ?? 0),
                    'output_summary' => $this->outputSummary($recommendation->food_packs, $recommendation->medicine_kits, $recommendation->cash_assistance),
                    'basis' => $this->basis($severity, $families, $report?->affected_structures ?? 0),
                ];
            })
            ->values()
            ->all();

        $stats = [
            'total' => count($recommendations),
            'high_priority' => count(array_filter($recommendations, fn (array $item) => $item['severity'] === 'High')),
            'food_packs' => array_sum(array_column($recommendations, 'food_packs')),
            'cash_assistance' => array_sum(array_column($recommendations, 'cash_assistance')),
        ];

        $rules = collect($decisionTree->rules())
            ->map(fn (array $rule, int $index) => [
                'id' => $index + 1,
                'condition' => $rule['condition'],
                'logic' => $rule['logic'],
                'output' => $rule['output'],
            ])
            ->all();

        return view('admin.recommendation-history', compact('recommendations', 'stats', 'rules'));
    }

    protected function priority(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }

    protected function ruleTrigger(?string $severity, int $families, int $members, int $structures): string
    {
        return match ($severity) {
            'severe' => "Triggered severe rule: {$families} families, {$members} household members, {$structures} structures affected.",
            'moderate' => "Triggered moderate rule: {$families} families, {$members} household members, {$structures} structures affected.",
            default => "Triggered minor rule: {$families} families, {$members} household members, {$structures} structures affected.",
        };
    }

    protected function outputSummary(int $foodPacks, int $medicineKits, float $cashAssistance): string
    {
        return "{$foodPacks} food packs, {$medicineKits} medicine kits, Php ".number_format($cashAssistance, 2).' cash assistance';
    }

    protected function basis(?string $severity, int $families, int $structures): string
    {
        return match ($severity) {
            'severe' => "High-priority output based on severe damage, {$families} families, and {$structures} affected structures.",
            'moderate' => "Medium-priority output based on moderate damage, {$families} families, and {$structures} affected structures.",
            default => "Low-priority output based on minor damage, {$families} families, and {$structures} affected structures.",
        };
    }
}
