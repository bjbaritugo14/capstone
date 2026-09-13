<?php

namespace App\Http\Controllers;

use App\Models\AffectedFamily;
use App\Models\DamageReport;
use App\Models\ResourceRecommendation;
use App\Models\VehicularAccident;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $reports = DamageReport::query()->with(['location.barangay', 'affectedFamilyRecords'])->get();
        $accidents = VehicularAccident::query()->with(['location.barangay'])->get();

        $stats = [
            'web_submissions' => $reports->count(),
            'validated_reports' => $reports->where('status', 'validated')->count(),
            'affected_households' => $reports->sum('affected_families'),
            'accident_logs' => $accidents->count(),
        ];

        $impactByBarangay = $reports
            ->groupBy(fn (DamageReport $report) => $report->location?->barangay?->barangay_name ?? 'Unassigned')
            ->map(fn ($items, $barangay) => [
                'name' => $barangay,
                'families' => $items->sum('affected_families'),
                'severity' => $this->highestSeverity($items->pluck('damage_severity')->all()),
                'recommendation' => $this->responseEmphasis($items->sum('affected_families'), $this->highestSeverity($items->pluck('damage_severity')->all())),
            ])
            ->values()
            ->all();

        $recommendations = ResourceRecommendation::query()
            ->with(['barangay', 'report'])
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(fn (ResourceRecommendation $recommendation) => [
                'barangay' => $recommendation->barangay?->barangay_name ?? 'Unassigned',
                'food_packs' => $recommendation->food_packs,
                'medical_kits' => $recommendation->medicine_kits,
                'cash_assistance' => $recommendation->cash_assistance,
                'priority' => $this->severityLabel($recommendation->report?->damage_severity),
                'basis' => ($recommendation->report?->affected_families ?? 0).' affected families, '.($recommendation->report?->affected_structures ?? 0).' affected structures',
            ])
            ->all();

        $recentDisasters = $reports
            ->sortByDesc('incident_datetime')
            ->take(5)
            ->map(fn (DamageReport $report) => [
                'code' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'severity' => $this->severityLabel($report->damage_severity),
                'status' => ucfirst($report->status),
                'coordinates' => $this->coordinates($report->location?->latitude, $report->location?->longitude),
                'lat' => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude) ? (float) $report->location->latitude : null,
                'lng' => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude) ? (float) $report->location->longitude : null,
                'incident_type' => 'Disaster Report',
                'kind' => 'disaster',
                'family_points' => $report->affectedFamilyRecords
                    ->filter(fn (AffectedFamily $family): bool => $this->hasCoordinates($family->latitude, $family->longitude))
                    ->map(fn (AffectedFamily $family): array => [
                        'name' => $family->family_head_name,
                        'household_members' => (int) $family->household_members,
                        'evacuation_status' => $family->evacuation_status ?: 'Not specified',
                        'severity' => $this->severityLabel($family->damage_severity ?: $report->damage_severity),
                        'lat' => (float) $family->latitude,
                        'lng' => (float) $family->longitude,
                    ])
                    ->values()
                    ->all(),
                'sort_date' => $report->incident_datetime,
            ]);

        $recentAccidents = $accidents
            ->sortByDesc('incident_datetime')
            ->take(5)
            ->map(fn (VehicularAccident $accident) => [
                'code' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $accident->location?->barangay?->barangay_name ?? 'Unassigned',
                'severity' => $accident->fatality_count > 0 || $accident->injured_count >= 3 ? 'High' : ($accident->injured_count > 0 ? 'Medium' : 'Low'),
                'status' => ucfirst($accident->status),
                'coordinates' => $this->coordinates($accident->location?->latitude, $accident->location?->longitude),
                'lat' => $this->hasCoordinates($accident->location?->latitude, $accident->location?->longitude) ? (float) $accident->location->latitude : null,
                'lng' => $this->hasCoordinates($accident->location?->latitude, $accident->location?->longitude) ? (float) $accident->location->longitude : null,
                'incident_type' => 'Vehicular Accident',
                'kind' => 'accident',
                'family_points' => [],
                'sort_date' => $accident->incident_datetime,
            ]);

        $recentReports = $recentDisasters
            ->concat($recentAccidents)
            ->sortByDesc('sort_date')
            ->take(6)
            ->map(fn (array $row) => collect($row)->except('sort_date')->all())
            ->values()
            ->all();

        $accidentSummaries = $accidents
            ->groupBy(fn (VehicularAccident $accident) => $accident->location?->road_segment ?? 'Unspecified')
            ->map(fn ($items, $roadSegment) => [
                'location' => $roadSegment,
                'incidents' => $items->count(),
                'trend' => $items->count() >= 5 ? 'High' : ($items->count() >= 2 ? 'Medium' : 'Low'),
            ])
            ->values()
            ->all();

        $workflow = [
            'Authorized users submit a geotagged disaster report or vehicular accident record through the web system.',
            'MDRRMO validates the report through the Web-GIS monitoring interface.',
            'Validated disaster entries are processed by the Decision Tree recommendation module.',
            'The system presents cash assistance, food packs, medicine, and monitoring summaries.',
        ];

        $mapCenter = ['lat' => 6.688099, 'lng' => 125.166607];

        $mapPoints = collect($recentReports)
            ->flatMap(function (array $report): array {
                $points = [];

                if ($report['lat'] === null || $report['lng'] === null) {
                    return $points;
                }

                $points[] = [
                    'kind' => $report['kind'],
                    'code' => $report['code'],
                    'barangay' => $report['barangay'],
                    'severity' => $report['severity'],
                    'status' => $report['status'],
                    'incident_type' => $report['incident_type'],
                    'lat' => $report['lat'],
                    'lng' => $report['lng'],
                ];

                foreach ($report['family_points'] as $familyPoint) {
                    $points[] = [
                        'kind' => 'family_pin',
                        'code' => $report['code'],
                        'barangay' => $report['barangay'],
                        'severity' => $familyPoint['severity'],
                        'status' => $report['status'],
                        'incident_type' => 'Affected Family',
                        'name' => $familyPoint['name'],
                        'household_members' => $familyPoint['household_members'],
                        'evacuation_status' => $familyPoint['evacuation_status'],
                        'lat' => $familyPoint['lat'],
                        'lng' => $familyPoint['lng'],
                    ];
                }

                return $points;
            })
            ->values()
            ->all();

        return view('dashboard', compact(
            'stats',
            'impactByBarangay',
            'recommendations',
            'recentReports',
            'accidentSummaries',
            'workflow',
            'mapCenter',
            'mapPoints',
        ));
    }

    protected function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
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

    protected function responseEmphasis(int $families, string $severity): string
    {
        if ($severity === 'High' || $families >= 150) {
            return 'Immediate cash, food, and medicine support';
        }

        if ($severity === 'Medium' || $families >= 50) {
            return 'Food packs and targeted validation';
        }

        return 'Monitoring and reserve allocation';
    }

    protected function hasCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }

    protected function coordinates(mixed $latitude, mixed $longitude): string
    {
        if (! $this->hasCoordinates($latitude, $longitude)) {
            return 'No GPS coordinates';
        }

        return $latitude.', '.$longitude;
    }
}
