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
            ->latest('generated_at')
            ->get()
            ->map(fn (ResourceRecommendation $recommendation) => [
                'id' => $recommendation->recommendation_id,
                'barangay' => $recommendation->barangay?->barangay_name,
                'report_id' => $recommendation->report_id,
                'food_packs' => $recommendation->food_packs,
                'medical_kits' => $recommendation->medicine_kits,
                'cash_assistance' => $recommendation->cash_assistance,
                'priority_level' => match ($recommendation->report?->damage_severity) {
                    'severe' => 'High',
                    'moderate' => 'Medium',
                    default => 'Low',
                },
                'generated_at' => $recommendation->generated_at,
            ]);

        return response()->json($recommendations);
    }
}
