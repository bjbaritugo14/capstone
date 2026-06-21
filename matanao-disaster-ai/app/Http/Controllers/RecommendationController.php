<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\ResourceRecommendation;
use App\Services\OllamaRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    public function index(): View
    {
        $items = ResourceRecommendation::query()
            ->with(['barangay', 'report'])
            ->latest('generated_at')
            ->get()
            ->map(fn (ResourceRecommendation $recommendation) => [
                'barangay' => $recommendation->barangay?->barangay_name ?? 'Unassigned',
                'report' => 'REP-'.str_pad((string) $recommendation->report_id, 4, '0', STR_PAD_LEFT),
                'families' => $recommendation->report?->affected_families ?? 0,
                'food_packs' => $recommendation->food_packs,
                'medical_kits' => $recommendation->medicine_kits,
                'cash_assistance' => $recommendation->cash_assistance,
                'priority' => $this->priority($recommendation->report?->damage_severity),
                'rule' => $this->rule($recommendation->report?->damage_severity, $recommendation->report?->affected_families ?? 0),
            ])
            ->all();

        $reportsForGeneration = DamageReport::query()
            ->with(['location.barangay', 'affectedFamilyRecords'])
            ->whereDoesntHave('recommendation')
            ->latest('created_at')
            ->get();

        return view('recommendations.index', compact('items', 'reportsForGeneration'));
    }

    public function generate(DamageReport $report, OllamaRecommendationService $ollama): RedirectResponse
    {
        $report->loadMissing(['location.barangay', 'affectedFamilyRecords']);

        $result = $ollama->generate($report);

        ResourceRecommendation::updateOrCreate(
            ['report_id' => $report->report_id],
            [
                'barangay_id' => $report->location->barangay_id,
                'generated_by' => Auth::id(),
                'cash_assistance' => $result['cash_assistance'],
                'food_packs' => $result['food_packs'],
                'medicine_kits' => $result['medicine_kits'],
                'generated_at' => now(),
            ],
        );

        $source = $result['source'] === 'ollama' ? 'Ollama AI' : 'Decision Tree rules';

        return redirect()
            ->route('recommendations.index')
            ->with('status', 'Recommendation generated using '.$source.'. '.$result['basis']);
    }

    protected function priority(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }

    protected function rule(?string $severity, int $families): string
    {
        return match ($severity) {
            'severe' => 'Severe damage report with '.$families.' affected families',
            'moderate' => 'Moderate damage report with '.$families.' affected families',
            default => 'Minor damage report with '.$families.' affected families',
        };
    }
}
