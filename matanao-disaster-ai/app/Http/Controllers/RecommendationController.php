<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\ResourceRecommendation;
use App\Services\AuditTrailService;
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
            ->whereHas('report', fn ($query) => $query->where('status', 'validated'))
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
                'rule' => $recommendation->basis
                    ?: $this->rule($recommendation->report?->damage_severity, $recommendation->report?->affected_families ?? 0),
            ])
            ->all();

        $reportsForGeneration = DamageReport::query()
            ->with(['location.barangay', 'affectedFamilyRecords'])
            ->where('status', 'validated')
            ->whereDoesntHave('recommendation')
            ->latest('created_at')
            ->get();

        return view('recommendations.index', compact('items', 'reportsForGeneration'));
    }

    public function generate(DamageReport $report, OllamaRecommendationService $ollama, AuditTrailService $auditTrail, \Illuminate\Http\Request $request): RedirectResponse
    {
        abort_if($report->status !== 'validated', 403, 'Only validated reports can be used for recommendation generation.');

        $report->loadMissing(['location.barangay', 'affectedFamilyRecords']);

        $result = $ollama->generate($report);

        $recommendation = ResourceRecommendation::updateOrCreate(
            ['report_id' => $report->report_id],
            [
                'barangay_id' => $report->location->barangay_id,
                'generated_by' => Auth::id(),
                'cash_assistance' => $result['cash_assistance'],
                'food_packs' => $result['food_packs'],
                'medicine_kits' => $result['medicine_kits'],
                'basis' => $result['basis'],
                'source' => $result['source'],
                'input_snapshot' => $result['inputs'],
                'generated_at' => now(),
            ],
        );
        $wasRecentlyCreated = $recommendation->wasRecentlyCreated;
        $recommendation->refresh();

        $source = $result['source'] === 'ollama'
            ? 'Decision Tree with Ollama-assisted refinement'
            : 'Decision Tree rules only';

        $auditTrail->log(
            $request,
            'Recommendation',
            $wasRecentlyCreated ? 'recommendation_generated' : 'recommendation_updated',
            ($wasRecentlyCreated ? 'Generated' : 'Updated').' recommendation '.$this->recommendationCode($recommendation).' for '.$this->reportCode($report).'.',
            $recommendation,
            [
                'report_code' => $this->reportCode($report),
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'source' => $source,
                'food_packs' => $recommendation->food_packs,
                'medicine_kits' => $recommendation->medicine_kits,
                'cash_assistance' => $recommendation->cash_assistance,
                'basis' => $result['basis'],
            ],
            $this->recommendationCode($recommendation),
        );

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

    protected function reportCode(DamageReport $report): string
    {
        return 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT);
    }

    protected function recommendationCode(ResourceRecommendation $recommendation): string
    {
        return 'REC-'.str_pad((string) $recommendation->recommendation_id, 4, '0', STR_PAD_LEFT);
    }
}
