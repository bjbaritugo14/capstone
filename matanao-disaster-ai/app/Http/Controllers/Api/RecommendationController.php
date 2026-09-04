<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourceRecommendation;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function index(): JsonResponse
    {
        $recommendations = ResourceRecommendation::query()
            ->with(['barangay', 'report'])
            ->whereHas('report', fn ($query) => $query->where('status', 'validated'))
            ->latest('generated_at')
            ->get()
            ->map(function (ResourceRecommendation $recommendation): array {
                $inputs = $recommendation->input_snapshot ?? [];

                return [
                    'id' => $recommendation->recommendation_id,
                    'barangay' => $recommendation->barangay?->barangay_name,
                    'report_id' => $recommendation->report_id,
                    'food_packs' => $recommendation->food_packs,
                    'medical_kits' => $recommendation->medicine_kits,
                    'cash_assistance' => $recommendation->cash_assistance,
                    'basis' => $recommendation->basis,
                    'source' => $recommendation->source,
                    'inputs' => $recommendation->input_snapshot,
                    'priority_level' => $this->priorityLabel($inputs['priority'] ?? null, $inputs['damage_severity'] ?? $recommendation->report?->damage_severity),
                    'generated_at' => $recommendation->generated_at,
                ];
            });

        return response()->json($recommendations);
    }

    protected function priorityLabel(?string $priority, ?string $severity): string
    {
        return match ($priority) {
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => match ($severity) {
                'severe' => 'High',
                'moderate' => 'Medium',
                default => 'Low',
            },
        };
    }
}
