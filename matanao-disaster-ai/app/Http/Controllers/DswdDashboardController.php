<?php

namespace App\Http\Controllers;

use App\Models\AffectedFamily;
use App\Models\DamageReport;
use App\Models\ResourceRecommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DswdDashboardController extends Controller
{
    public function index(): View
    {
        $reports = $this->validatedReportsQuery()
            ->latest('incident_datetime')
            ->get();

        $stats = [
            'disaster_reports' => $reports->count(),
            'affected_families' => $reports->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->count() ?: $report->affected_families),
            'household_members' => $reports->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->sum('household_members')),
            'recommendations' => ResourceRecommendation::query()
                ->whereHas('report', fn ($query) => $query->where('status', 'validated'))
                ->count(),
        ];

        $barangayImpacts = $reports
            ->groupBy(fn (DamageReport $report) => $report->location?->barangay?->barangay_name ?? 'Unassigned')
            ->map(fn ($items, $barangay) => [
                'barangay' => $barangay,
                'reports' => $items->count(),
                'families' => $items->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->count() ?: $report->affected_families),
                'members' => $items->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->sum('household_members')),
                'severity' => $this->highestSeverity($items->pluck('damage_severity')->all()),
            ])
            ->values()
            ->all();

        $families = AffectedFamily::query()
            ->with(['report.location.barangay'])
            ->whereHas('report', fn ($query) => $query->where('status', 'validated'))
            ->latest('created_at')
            ->limit(20)
            ->get();

        $validatedAreaRecords = $this->validatedAreaRecords($reports)
            ->sortByDesc('validated_at_sort')
            ->values();

        $validatedAreaStats = [
            'areas' => $validatedAreaRecords->count(),
            'barangays' => $validatedAreaRecords
                ->pluck('barangay')
                ->filter(fn (string $barangay) => $barangay !== 'Unassigned')
                ->unique()
                ->count(),
            'household_members' => $validatedAreaRecords->sum('household_members'),
            'family_pins' => $validatedAreaRecords->sum('pinpointed_families'),
        ];

        $validatedAreaPreview = $validatedAreaRecords
            ->take(4)
            ->values()
            ->all();
        $validatedAreaMapCenter = $this->mapCenter();
        $validatedAreaMapPoints = $this->validatedAreaMapPoints($validatedAreaRecords);

        return view('dswd.dashboard', compact(
            'stats',
            'barangayImpacts',
            'families',
            'validatedAreaStats',
            'validatedAreaPreview',
            'validatedAreaMapCenter',
            'validatedAreaMapPoints',
        ));
    }

    public function validatedAreas(): View
    {
        $validatedReports = $this->validatedReportsQuery()
            ->latest('incident_datetime')
            ->get();

        $areas = $this->validatedAreaRecords($validatedReports)
            ->sortByDesc('validated_at_sort')
            ->values();

        $stats = [
            'validated_areas' => $areas->count(),
            'covered_barangays' => $areas
                ->pluck('barangay')
                ->filter(fn (string $barangay) => $barangay !== 'Unassigned')
                ->unique()
                ->count(),
            'affected_families' => $areas->sum('affected_families'),
            'family_pins' => $areas->sum('pinpointed_families'),
        ];

        $barangayCoverage = $areas
            ->groupBy('barangay')
            ->map(function (Collection $items, string $barangay): array {
                $latestArea = $items->sortByDesc('validated_at_sort')->first();

                return [
                    'barangay' => $barangay,
                    'areas' => $items->count(),
                    'families' => $items->sum('affected_families'),
                    'members' => $items->sum('household_members'),
                    'family_pins' => $items->sum('pinpointed_families'),
                    'severity' => $this->highestSeverityLabel($items->pluck('severity')->all()),
                    'validated_at_label' => $latestArea['validated_at_label'] ?? 'Validation timestamp unavailable',
                    'severity_weight' => $items->max('severity_weight') ?? 1,
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['severity_weight'], $row['families'], $row['areas']])
            ->values()
            ->all();

        $mapCenter = $this->mapCenter();
        $mapPoints = $this->validatedAreaMapPoints($areas);

        return view('dswd.validated-areas', compact('areas', 'stats', 'barangayCoverage', 'mapCenter', 'mapPoints'));
    }

    protected function highestSeverity(array $severities): string
    {
        if (in_array('severe', $severities, true)) {
            return 'High';
        }

        if (in_array('moderate', $severities, true)) {
            return 'Medium';
        }

        return 'Low';
    }

    protected function validatedReportsQuery(): Builder
    {
        return DamageReport::query()
            ->with([
                'location.barangay',
                'affectedFamilyRecords',
                'recommendation',
                'validations' => fn ($query) => $query
                    ->with('validator')
                    ->latest('validated_at'),
            ])
            ->where('status', 'validated');
    }

    protected function validatedAreaRecords(Collection $reports): Collection
    {
        return $reports->map(function (DamageReport $report): array {
            $latestValidation = $report->validations->first();
            $reportCoordinates = $this->coordinatePair($report->location?->latitude, $report->location?->longitude);
            $pinpointedFamilies = $report->affectedFamilyRecords
                ->filter(fn (AffectedFamily $family) => $this->hasMappableCoordinates($family->latitude, $family->longitude));
            $severity = $this->severityLabel($report->damage_severity);

            return [
                'id' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'sitio_purok' => $report->location?->sitio_purok ?: 'Not specified',
                'road_segment' => $report->location?->road_segment ?: 'Unspecified',
                'disaster_type' => $report->disaster_type,
                'severity' => $severity,
                'severity_weight' => $this->severityWeight($severity),
                'affected_families' => $report->affectedFamilyRecords->count() ?: (int) $report->affected_families,
                'household_members' => (int) $report->affectedFamilyRecords->sum('household_members'),
                'pinpointed_families' => $pinpointedFamilies->count(),
                'validated_by' => $latestValidation?->validator?->full_name ?? 'MDRRMO Validator',
                'validated_at_label' => $this->formatTimestamp($latestValidation?->validated_at, 'Validation timestamp unavailable'),
                'validated_at_sort' => $this->timestampValue($latestValidation?->validated_at),
                'incident_at_label' => optional($report->incident_datetime)->format('M d, Y h:i A') ?? 'No incident date',
                'description' => filled($report->description) ? $report->description : 'No description provided.',
                'status' => ucfirst($report->status),
                'coordinates' => $this->coordinates($report->location?->latitude, $report->location?->longitude),
                'lat' => $reportCoordinates['lat'] ?? null,
                'lng' => $reportCoordinates['lng'] ?? null,
                'family_points' => $pinpointedFamilies
                    ->map(fn (AffectedFamily $family): array => [
                        'family_head_name' => $family->family_head_name,
                        'household_members' => (int) $family->household_members,
                        'evacuation_status' => $family->evacuation_status ?: 'Not specified',
                        'lat' => (float) $family->latitude,
                        'lng' => (float) $family->longitude,
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }

    protected function validatedAreaMapPoints(Collection $areas): array
    {
        return $areas
            ->flatMap(function (array $area): array {
                $points = [];

                if ($area['lat'] !== null && $area['lng'] !== null) {
                    $points[] = [
                        'kind' => 'validated_area',
                        'title' => $area['id'],
                        'subtitle' => $area['disaster_type'].' | '.$area['barangay'],
                        'note' => $area['affected_families'].' families | '.$area['household_members'].' household members | '.$area['severity'],
                        'coordinates' => $area['coordinates'],
                        'severity' => $area['severity'],
                        'lat' => $area['lat'],
                        'lng' => $area['lng'],
                    ];
                }

                foreach ($area['family_points'] as $familyPoint) {
                    $points[] = [
                        'kind' => 'family_pin',
                        'title' => $familyPoint['family_head_name'],
                        'subtitle' => $area['id'].' | '.$area['barangay'],
                        'note' => $familyPoint['household_members'].' household members | '.$familyPoint['evacuation_status'],
                        'coordinates' => $familyPoint['lat'].', '.$familyPoint['lng'],
                        'severity' => $area['severity'],
                        'lat' => $familyPoint['lat'],
                        'lng' => $familyPoint['lng'],
                    ];
                }

                return $points;
            })
            ->values()
            ->all();
    }

    protected function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
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

    protected function severityWeight(string $severity): int
    {
        return match ($severity) {
            'High' => 3,
            'Medium' => 2,
            default => 1,
        };
    }

    protected function mapCenter(): array
    {
        return ['lat' => 6.688099, 'lng' => 125.166607];
    }

    protected function coordinates(mixed $latitude, mixed $longitude): string
    {
        if (! $this->hasMappableCoordinates($latitude, $longitude)) {
            return 'No coordinates';
        }

        return $latitude.', '.$longitude;
    }

    protected function coordinatePair(mixed $latitude, mixed $longitude): ?array
    {
        if (! $this->hasMappableCoordinates($latitude, $longitude)) {
            return null;
        }

        return [
            'lat' => (float) $latitude,
            'lng' => (float) $longitude,
        ];
    }

    protected function hasMappableCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
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

    protected function timestampValue(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (blank($value)) {
            return 0;
        }

        return strtotime((string) $value) ?: 0;
    }
}
