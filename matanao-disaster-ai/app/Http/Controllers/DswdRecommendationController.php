<?php

namespace App\Http\Controllers;

use App\Models\ResourceRecommendation;
use Illuminate\View\View;

class DswdRecommendationController extends Controller
{
    public function index(): View
    {
        $recommendations = ResourceRecommendation::query()
            ->with(['barangay', 'report.affectedFamilyRecords', 'generator'])
            ->latest('generated_at')
            ->get()
            ->map(fn (ResourceRecommendation $recommendation) => [
                'id' => 'REC-'.str_pad((string) $recommendation->recommendation_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $recommendation->barangay?->barangay_name ?? 'Unassigned',
                'report' => 'REP-'.str_pad((string) $recommendation->report_id, 4, '0', STR_PAD_LEFT),
                'families' => $recommendation->report?->affectedFamilyRecords->count() ?: ($recommendation->report?->affected_families ?? 0),
                'members' => $recommendation->report?->affectedFamilyRecords->sum('household_members') ?? 0,
                'food_packs' => $recommendation->food_packs,
                'medicine_kits' => $recommendation->medicine_kits,
                'cash_assistance' => $recommendation->cash_assistance,
                'generated_by' => $recommendation->generator?->full_name ?? 'Unknown user',
                'generated_at' => $recommendation->generated_at,
            ])
            ->all();

        return view('dswd.recommendations', compact('recommendations'));
    }
}
