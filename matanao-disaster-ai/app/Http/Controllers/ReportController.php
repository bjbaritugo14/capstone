<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\IncidentLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected function reports(): array
    {
        return DamageReport::query()
            ->with(['location.barangay', 'user', 'recommendation', 'affectedFamilyRecords'])
            ->latest('created_at')
            ->get()
            ->map(fn (DamageReport $report) => [
                'id' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'event' => $report->disaster_type.' Incident',
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'road_segment' => $report->location?->road_segment ?? 'Unspecified',
                'sitio_purok' => $report->location?->sitio_purok ?? 'Not specified',
                'type' => $report->disaster_type,
                'severity' => $this->severityLabel($report->damage_severity),
                'families' => $report->affected_families,
                'household_members' => $report->affectedFamilyRecords->sum('household_members'),
                'houses' => $report->affected_structures,
                'status' => ucfirst($report->status),
                'submitted_by' => $report->user?->full_name ?? 'Unknown user',
                'coordinates' => $this->coordinates($report->location?->latitude, $report->location?->longitude),
                'latitude' => $report->location?->latitude === null ? null : (float) $report->location->latitude,
                'longitude' => $report->location?->longitude === null ? null : (float) $report->location->longitude,
                'date' => optional($report->incident_datetime)->format('Y-m-d') ?? '',
                'time' => optional($report->incident_datetime)->format('h:i A') ?? '',
                'needs' => $this->needs($report),
                'impact_score' => $this->impactScore($report),
            ])
            ->all();
    }

    public function index(): View
    {
        $reports = $this->reports();
        $barangays = Barangay::orderBy('barangay_name')->get();

        $stats = [
            'total' => count($reports),
            'high_severity' => count(array_filter($reports, fn (array $report) => $report['severity'] === 'High')),
            'affected_families' => array_sum(array_column($reports, 'families')),
            'validated' => count(array_filter($reports, fn (array $report) => $report['status'] === 'Validated')),
        ];

        $impactAreas = collect($reports)
            ->groupBy('barangay')
            ->map(function ($items, $barangay) {
                $peakSeverity = $this->highestSeverityLabel($items->pluck('severity')->all());

                return [
                    'location' => $barangay,
                    'reports' => $items->count(),
                    'families' => $items->sum('families'),
                    'structures' => $items->sum('houses'),
                    'severity' => $peakSeverity,
                    'priority' => $this->impactPriority($items->sum('families'), $items->sum('houses'), $peakSeverity),
                ];
            })
            ->sortByDesc(fn (array $area) => [$this->severityWeight($area['severity']), $area['families'], $area['reports']])
            ->values()
            ->all();

        $disasterTypes = collect($reports)
            ->groupBy('type')
            ->map(function ($items, $type) {
                return [
                    'type' => $type,
                    'reports' => $items->count(),
                    'families' => $items->sum('families'),
                    'structures' => $items->sum('houses'),
                    'severity' => $this->highestSeverityLabel($items->pluck('severity')->all()),
                ];
            })
            ->sortByDesc('reports')
            ->values()
            ->all();

        $severityAnalytics = collect(['High', 'Medium', 'Low'])
            ->map(function (string $severity) use ($reports) {
                $items = collect($reports)->where('severity', $severity);

                return [
                    'label' => $severity,
                    'reports' => $items->count(),
                    'families' => $items->sum('families'),
                    'structures' => $items->sum('houses'),
                ];
            })
            ->all();

        $mapCenter = ['lat' => 6.688099, 'lng' => 125.166607];

        $mapPoints = collect($reports)
            ->filter(fn (array $report) => $report['latitude'] !== null && $report['longitude'] !== null)
            ->map(fn (array $report) => [
                'id' => $report['id'],
                'barangay' => $report['barangay'],
                'type' => $report['type'],
                'severity' => $report['severity'],
                'status' => $report['status'],
                'road_segment' => $report['road_segment'],
                'families' => $report['families'],
                'structures' => $report['houses'],
                'lat' => $report['latitude'],
                'lng' => $report['longitude'],
            ])
            ->values()
            ->all();

        return view('reports.index', compact(
            'reports',
            'barangays',
            'stats',
            'impactAreas',
            'disasterTypes',
            'severityAnalytics',
            'mapCenter',
            'mapPoints',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,barangay_id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'road_segment' => ['nullable', 'string', 'max:150'],
            'sitio_purok' => ['nullable', 'string', 'max:150'],
            'disaster_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'damage_severity' => ['required', 'in:minor,moderate,severe'],
            'affected_families' => ['nullable', 'integer', 'min:0'],
            'affected_structures' => ['nullable', 'integer', 'min:0'],
            'incident_datetime' => ['required', 'date'],
            'families' => ['nullable', 'array'],
            'families.*.family_head_name' => ['nullable', 'string', 'max:150'],
            'families.*.household_members' => ['nullable', 'integer', 'min:1'],
            'families.*.contact_number' => ['nullable', 'string', 'max:30'],
            'families.*.evacuation_status' => ['nullable', 'string', 'max:50'],
        ]);

        $familyRows = collect($validated['families'] ?? [])
            ->filter(fn (array $family) => filled($family['family_head_name'] ?? null))
            ->values();

        $location = IncidentLocation::create([
            'barangay_id' => $validated['barangay_id'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'road_segment' => $validated['road_segment'] ?? null,
            'sitio_purok' => $validated['sitio_purok'] ?? null,
        ]);

        $report = DamageReport::create([
            'user_id' => Auth::id(),
            'location_id' => $location->location_id,
            'disaster_type' => $validated['disaster_type'],
            'description' => $validated['description'] ?? null,
            'damage_severity' => $validated['damage_severity'],
            'affected_families' => $familyRows->isNotEmpty() ? $familyRows->count() : ($validated['affected_families'] ?? 0),
            'affected_structures' => $validated['affected_structures'] ?? 0,
            'incident_datetime' => $validated['incident_datetime'],
            'status' => 'pending',
        ]);

        $familyRows->each(function (array $family) use ($report): void {
            AffectedFamily::create([
                'report_id' => $report->report_id,
                'family_head_name' => $family['family_head_name'],
                'household_members' => $family['household_members'] ?? 1,
                'contact_number' => $family['contact_number'] ?? null,
                'evacuation_status' => $family['evacuation_status'] ?? null,
            ]);
        });

        return redirect()->route('reports.index')->with('status', 'Disaster report saved.');
    }

    public function print(): View
    {
        $reports = $this->reports();
        $totals = [
            'reports' => count($reports),
            'families' => array_sum(array_column($reports, 'families')),
            'houses' => array_sum(array_column($reports, 'houses')),
        ];

        return view('reports.print', compact('reports', 'totals'));
    }

    protected function severityLabel(string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }

    protected function coordinates(mixed $latitude, mixed $longitude): string
    {
        if ($latitude === null || $longitude === null) {
            return 'No coordinates';
        }

        return $latitude.', '.$longitude;
    }

    protected function needs(DamageReport $report): string
    {
        $recommendation = $report->recommendation->first();

        if ($recommendation === null) {
            return 'No recommendation generated';
        }

        return collect([
            $recommendation->cash_assistance > 0 ? 'Cash assistance' : null,
            $recommendation->food_packs > 0 ? 'Food packs' : null,
            $recommendation->medicine_kits > 0 ? 'Medicine kits' : null,
        ])->filter()->implode(', ');
    }

    protected function impactScore(DamageReport $report): int
    {
        $severityScore = match ($report->damage_severity) {
            'severe' => 3,
            'moderate' => 2,
            default => 1,
        };

        return $severityScore + (int) $report->affected_families + (int) $report->affected_structures;
    }

    protected function highestSeverityLabel(array $severities): string
    {
        if (in_array('High', $severities, true)) {
            return 'High';
        }

        if (in_array('Medium', $severities, true)) {
            return 'Medium';
        }

        return 'Low';
    }

    protected function impactPriority(int $families, int $structures, string $severity): string
    {
        if ($severity === 'High' || $families >= 100 || $structures >= 50) {
            return 'Immediate response';
        }

        if ($severity === 'Medium' || $families >= 30 || $structures >= 15) {
            return 'Focused validation';
        }

        return 'Routine monitoring';
    }

    protected function severityWeight(string $severity): int
    {
        return match ($severity) {
            'High' => 3,
            'Medium' => 2,
            default => 1,
        };
    }
}
