<?php

namespace App\Http\Controllers;

use App\Models\ResourceRecommendation;
use Illuminate\View\View;

class DswdRecommendationController extends Controller
{
    public function index(): View
    {
        $recommendations = ResourceRecommendation::query()
            ->with([
                'barangay',
                'report.affectedFamilyRecords',
                'report.validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
                'generator',
            ])
            ->whereHas('report', fn ($query) => $query->where('status', 'validated'))
            ->latest('generated_at')
            ->get()
            ->map(function (ResourceRecommendation $recommendation): array {
                $report = $recommendation->report;
                $families = $report?->affectedFamilyRecords->count() ?: ($report?->affected_families ?? 0);
                $members = $report?->affectedFamilyRecords->sum('household_members') ?? 0;
                $latestValidation = $report?->validations->first();
                $severity = $this->priority($report?->damage_severity);

                return [
                    'id' => 'REC-'.str_pad((string) $recommendation->recommendation_id, 4, '0', STR_PAD_LEFT),
                    'barangay' => $recommendation->barangay?->barangay_name ?? 'Unassigned',
                    'report' => 'REP-'.str_pad((string) $recommendation->report_id, 4, '0', STR_PAD_LEFT),
                    'disaster_type' => $report?->disaster_type ?? 'Unknown',
                    'severity' => $severity,
                    'families' => $families,
                    'members' => $members,
                    'food_packs' => $recommendation->food_packs,
                    'medicine_kits' => $recommendation->medicine_kits,
                    'cash_assistance' => $recommendation->cash_assistance,
                    'generated_by' => $recommendation->generator?->full_name ?? 'Unknown user',
                    'generated_at' => $recommendation->generated_at,
                    'validated_by' => $latestValidation?->validator?->full_name ?? 'MDRRMO Validator',
                    'validated_at_label' => $this->formatTimestamp($latestValidation?->validated_at, 'Validation timestamp unavailable'),
                    'basis' => $recommendation->basis
                        ?: $this->basis($report?->damage_severity, $families, $report?->affected_structures ?? 0),
                ];
            })
            ->values();

        $stats = [
            'recommendations' => $recommendations->count(),
            'covered_barangays' => $recommendations->pluck('barangay')->unique()->count(),
            'families' => $recommendations->sum('families'),
            'cash_assistance' => $recommendations->sum('cash_assistance'),
        ];

        return view('dswd.recommendations', [
            'recommendations' => $recommendations->all(),
            'stats' => $stats,
        ]);
    }

    protected function priority(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }

    protected function basis(?string $severity, int $families, int $structures): string
    {
        return match ($severity) {
            'severe' => "Severe damage with {$families} families and {$structures} affected structures.",
            'moderate' => "Moderate damage with {$families} families and {$structures} affected structures.",
            default => "Minor damage with {$families} families and {$structures} affected structures.",
        };
    }

    protected function formatTimestamp(mixed $value, string $fallback): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y h:i A');
        }

        if (blank($value)) {
            return $fallback;
        }

        return date('M d, Y h:i A', strtotime((string) $value));
    }
}
