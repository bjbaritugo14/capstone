<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\IncidentLocation;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected SystemSettingService $settings,
    ) {}

    protected function reports(?int $barangayId = null): array
    {
        return DamageReport::query()
            ->with(['location.barangay', 'user', 'recommendation', 'affectedFamilyRecords'])
            ->when($barangayId, fn ($query) => $query->whereHas(
                'location',
                fn ($locationQuery) => $locationQuery->where('barangay_id', $barangayId)
            ))
            ->latest('created_at')
            ->get()
            ->map(function (DamageReport $report) {
                $recommendation = $report->recommendation->first();
                $medicineKits = $this->hasMedicalNeedDescription($report)
                    ? (int) ($recommendation?->medicine_kits ?? 0)
                    : 0;

                return [
                    'id' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                    'event' => $report->disaster_type.' Incident',
                    'barangay_id' => $report->location?->barangay?->barangay_id,
                    'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                    'road_segment' => $report->location?->road_segment ?? 'Unspecified',
                    'sitio_purok' => $report->location?->sitio_purok ?? 'Not specified',
                    'type' => $report->disaster_type,
                    'severity' => $this->severityLabel($report->damage_severity),
                    'families' => $report->affected_families,
                    'named_families' => $report->affectedFamilyRecords->count(),
                    'household_members' => $report->affectedFamilyRecords->sum('household_members'),
                    'houses' => $report->affected_structures,
                    'status' => ucfirst($report->status),
                    'submitted_by' => $report->user?->full_name ?? 'Unknown user',
                    'coordinates' => $this->coordinates($report->location?->latitude, $report->location?->longitude),
                    'latitude' => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude) ? (float) $report->location->latitude : null,
                    'longitude' => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude) ? (float) $report->location->longitude : null,
                    'date' => optional($report->incident_datetime)->format('Y-m-d') ?? '',
                    'time' => optional($report->incident_datetime)->format('h:i A') ?? '',
                    'needs' => $this->needs($report),
                    'food_packs' => (int) ($recommendation?->food_packs ?? 0),
                    'cash_assistance' => (float) ($recommendation?->cash_assistance ?? 0),
                    'medicine_kits' => $medicineKits,
                    'has_recommendation' => $recommendation !== null,
                    'family_assistance' => $this->familyAssistanceRows($report, $recommendation),
                    'impact_score' => $this->impactScore($report),
                ];
            })
            ->all();
    }

    public function index(): View
    {
        $reports = $this->reports();
        $barangays = Barangay::query()->active()->orderBy('barangay_name')->get();

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
            'barangay_id' => ['required', Rule::exists('barangays', 'barangay_id')->where('status', 'active')],
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

    public function print(Request $request): View
    {
        $filters = $request->validate([
            'barangay_id' => ['nullable', 'integer', Rule::exists('barangays', 'barangay_id')],
        ]);

        $selectedBarangayId = filled($filters['barangay_id'] ?? null)
            ? (int) $filters['barangay_id']
            : null;

        $reports = $this->reports($selectedBarangayId);
        $barangays = Barangay::query()->active()->orderBy('barangay_name')->get();
        $selectedBarangay = $selectedBarangayId
            ? $barangays->firstWhere('barangay_id', $selectedBarangayId)
            : null;

        $barangayGroups = collect($reports)
            ->groupBy('barangay')
            ->map(fn ($items, string $barangay) => [
                'barangay' => $barangay,
                'reports' => $items->values(),
                'totals' => [
                    'reports' => $items->count(),
                    'families' => $items->sum('families'),
                    'named_families' => $items->sum('named_families'),
                    'household_members' => $items->sum('household_members'),
                    'houses' => $items->sum('houses'),
                    'food_packs' => $items->sum('food_packs'),
                    'medicine_kits' => $items->sum('medicine_kits'),
                    'cash_assistance' => $items->sum('cash_assistance'),
                ],
            ])
            ->sortBy('barangay')
            ->values();

        $totals = [
            'reports' => count($reports),
            'families' => array_sum(array_column($reports, 'families')),
            'houses' => array_sum(array_column($reports, 'houses')),
            'food_packs' => array_sum(array_column($reports, 'food_packs')),
            'medicine_kits' => array_sum(array_column($reports, 'medicine_kits')),
            'cash_assistance' => array_sum(array_column($reports, 'cash_assistance')),
        ];

        return view('reports.print', compact(
            'barangayGroups',
            'barangays',
            'reports',
            'selectedBarangay',
            'selectedBarangayId',
            'totals',
        ));
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
        if (! $this->hasCoordinates($latitude, $longitude)) {
            return 'No coordinates';
        }

        return $latitude.', '.$longitude;
    }

    protected function hasCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
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
            $this->hasMedicalNeedDescription($report) && $recommendation->medicine_kits > 0 ? 'Medicine kits' : null,
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
        if (
            $severity === 'High'
            || $families >= $this->settings->integer('impact_immediate_family_threshold')
            || $structures >= $this->settings->integer('impact_immediate_structure_threshold')
        ) {
            return 'Immediate response';
        }

        if (
            $severity === 'Medium'
            || $families >= $this->settings->integer('impact_focused_family_threshold')
            || $structures >= $this->settings->integer('impact_focused_structure_threshold')
        ) {
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

    protected function familyAssistanceRows(DamageReport $report, mixed $recommendation): array
    {
        $families = $report->affectedFamilyRecords->values();

        if ($families->isEmpty()) {
            return [];
        }

        $foodPacks = (int) ($recommendation?->food_packs ?? 0);
        $medicineKits = $this->hasMedicalNeedDescription($report)
            ? (int) ($recommendation?->medicine_kits ?? 0)
            : 0;
        $cashAssistance = (float) ($recommendation?->cash_assistance ?? 0);
        $fallbackSeverity = $this->normalizedSeverity($report->damage_severity);

        $rows = $families
            ->map(function (AffectedFamily $family) use ($fallbackSeverity): array {
                $severity = $this->normalizedSeverity($family->damage_severity, $fallbackSeverity);

                return [
                    'severity_key' => $severity,
                    'family' => $family,
                    'name' => $family->family_head_name,
                    'household_members' => $family->household_members,
                    'contact_number' => $family->contact_number ?: 'N/A',
                    'evacuation_status' => $family->evacuation_status ?: 'Not specified',
                    'severity' => $this->severityLabel($severity),
                ];
            })
            ->values()
            ->all();

        $foodAllocations = $this->allocateWeightedWholeNumber(
            $foodPacks,
            array_map(fn (array $row): float => $this->foodWeight($row['severity_key']), $rows),
        );
        $medicineAllocations = $this->allocateWeightedWholeNumber(
            $medicineKits,
            array_map(fn (array $row): float => $this->medicineWeight($row['family'], $row['severity_key']), $rows),
        );
        $cashAllocations = $this->allocateWeightedMoney(
            $cashAssistance,
            array_map(fn (array $row): float => $this->cashWeight($row['severity_key']), $rows),
        );

        foreach ($rows as $index => $row) {
            $rows[$index] = [
                'name' => $row['name'],
                'household_members' => $row['household_members'],
                'contact_number' => $row['contact_number'],
                'evacuation_status' => $row['evacuation_status'],
                'severity' => $row['severity'],
                'food_packs' => $foodAllocations[$index] ?? 0,
                'medicine_kits' => $medicineAllocations[$index] ?? 0,
                'cash_assistance' => $cashAllocations[$index] ?? 0.0,
            ];
        }

        return $rows;
    }

    protected function foodWeight(string $severity): float
    {
        return max(0.01, $this->settings->float('recommendation_food_multiplier_'.$severity));
    }

    protected function medicineWeight(AffectedFamily $family, string $severity): float
    {
        $members = max(1, (int) $family->household_members);
        $divisor = max(1, $this->settings->integer('recommendation_medicine_divisor_'.$severity));
        $multiplier = $severity === 'severe'
            ? max(0.01, $this->settings->float('recommendation_medicine_multiplier_severe'))
            : 1.0;

        return max(0.01, ($members / $divisor) * $multiplier);
    }

    protected function cashWeight(string $severity): float
    {
        return max(0.01, $this->settings->integer('recommendation_cash_per_family_'.$severity));
    }

    protected function allocateWeightedWholeNumber(int $total, array $weights): array
    {
        $count = count($weights);

        if ($count === 0) {
            return [];
        }

        $total = max(0, $total);
        $weights = array_map(
            fn (mixed $weight): float => is_numeric($weight) && (float) $weight > 0 ? (float) $weight : 1.0,
            array_values($weights),
        );
        $weightTotal = array_sum($weights);
        $allocations = array_fill(0, $count, 0);
        $fractions = array_fill(0, $count, 0.0);

        foreach ($weights as $index => $weight) {
            $rawShare = $weightTotal > 0 ? ($total * $weight) / $weightTotal : $total / $count;
            $allocations[$index] = (int) floor($rawShare);
            $fractions[$index] = $rawShare - $allocations[$index];
        }

        $remaining = $total - array_sum($allocations);
        $order = array_keys($weights);

        usort($order, function (int $left, int $right) use ($fractions, $weights): int {
            return $fractions[$right] <=> $fractions[$left]
                ?: $weights[$right] <=> $weights[$left]
                ?: $left <=> $right;
        });

        for ($step = 0; $step < $remaining; $step++) {
            $allocations[$order[$step % $count]]++;
        }

        return $allocations;
    }

    protected function allocateWeightedMoney(float $total, array $weights): array
    {
        $centavos = (int) round(max(0, $total) * 100);

        return array_map(
            fn (int $amount): float => $amount / 100,
            $this->allocateWeightedWholeNumber($centavos, $weights),
        );
    }

    protected function hasMedicalNeedDescription(DamageReport $report): bool
    {
        return $this->containsAny(
            strtolower($this->descriptionText($report)),
            $this->medicalDescriptionTerms(),
        );
    }

    protected function descriptionText(DamageReport $report): string
    {
        return collect([(string) $report->description])
            ->merge($report->affectedFamilyRecords->pluck('description')->all())
            ->filter(fn (mixed $description): bool => trim((string) $description) !== '')
            ->map(fn (mixed $description): string => trim((string) $description))
            ->implode(' ');
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

    protected function normalizedSeverity(?string $severity, string $fallback = 'minor'): string
    {
        return match (strtolower((string) $severity)) {
            'severe', 'high' => 'severe',
            'moderate', 'medium' => 'moderate',
            'minor', 'low' => 'minor',
            default => $fallback,
        };
    }
}
