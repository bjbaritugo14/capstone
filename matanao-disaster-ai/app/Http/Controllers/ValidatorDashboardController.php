<?php

namespace App\Http\Controllers;

use App\Models\AccidentImage;
use App\Models\AccidentInvolvedPerson;
use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ReportImage;
use App\Models\VehicularAccident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ValidatorDashboardController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $reports = DamageReport::query()
            ->with(['location.barangay', 'affectedFamilyRecords', 'images'])
            ->where('user_id', $userId)
            ->latest('incident_datetime')
            ->get();

        $accidents = VehicularAccident::query()
            ->with(['location.barangay', 'involvedPersons', 'images'])
            ->where('user_id', $userId)
            ->latest('incident_datetime')
            ->get();

        $stats = [
            'disaster_reports' => $reports->count(),
            'accident_reports' => $accidents->count(),
            'pending_review' => $reports->where('status', 'pending')->count()
                + $accidents->where('status', 'recorded')->count(),
            'family_records' => $reports->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->count() ?: (int) $report->affected_families),
        ];

        $recentSubmissions = $this->recentSubmissions($reports, $accidents);
        $mapCenter = $this->mapCenter();
        $mapPoints = $this->mapPoints($reports, $accidents);
        $barangays = Barangay::query()->active()->orderBy('barangay_name')->get();

        return view('validator.dashboard', compact('stats', 'recentSubmissions', 'mapCenter', 'mapPoints', 'barangays'));
    }

    public function storeReport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'barangay_id')->where('status', 'active')],
            'purok' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'disasterType' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:minor,moderate,severe'],
            'families' => ['required', 'array', 'min:1'],
            'families.*.familyHeadName' => ['required', 'string', 'max:150'],
            'families.*.householdMembers' => ['required', 'integer', 'min:1'],
            'families.*.contactNumber' => ['nullable', 'string', 'max:30'],
            'families.*.evacuationStatus' => ['nullable', 'string', 'max:50'],
            'families.*.description' => ['nullable', 'string'],
            'families.*.severity' => ['nullable', 'in:minor,moderate,severe'],
            'families.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'families.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'affectedStructures' => ['nullable', 'integer', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'image', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'reportDate' => ['required', 'date'],
            'form_context' => ['nullable', 'string'],
        ]);

        $families = collect($validated['families'])
            ->filter(fn (array $family) => filled($family['familyHeadName'] ?? null))
            ->values();

        DB::transaction(function () use ($request, $validated, $families): void {
            $barangay = Barangay::query()->findOrFail($validated['barangay_id']);
            $coordinates = $this->resolvedLocationCoordinates(
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
                $families,
            );

            $location = IncidentLocation::create([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
                'sitio_purok' => $validated['purok'] ?? null,
            ]);

            $report = DamageReport::create([
                'user_id' => Auth::id(),
                'location_id' => $location->location_id,
                'disaster_type' => $validated['disasterType'],
                'description' => $validated['description'] ?? '',
                'damage_severity' => $validated['severity'],
                'affected_families' => $families->count(),
                'affected_structures' => (int) ($validated['affectedStructures'] ?? 0),
                'incident_datetime' => $validated['reportDate'].' 00:00:00',
                'status' => 'pending',
                'created_at' => now(),
            ]);

            $families->each(function (array $family, int $index) use ($request, $report): void {
                $familyRecord = AffectedFamily::create([
                    'report_id' => $report->report_id,
                    'family_head_name' => $family['familyHeadName'],
                    'household_members' => $family['householdMembers'],
                    'contact_number' => $family['contactNumber'] ?? null,
                    'evacuation_status' => $family['evacuationStatus'] ?? null,
                    'description' => $family['description'] ?? null,
                    'damage_severity' => $family['severity'] ?? null,
                    'latitude' => $family['latitude'] ?? null,
                    'longitude' => $family['longitude'] ?? null,
                ]);

                $familyPhotosKey = "family_photos_{$index}";
                if (! $request->hasFile($familyPhotosKey)) {
                    return;
                }

                foreach ($request->file($familyPhotosKey) as $photo) {
                    $path = $photo->store('report-photos', 'public');

                    ReportImage::create([
                        'report_id' => $report->report_id,
                        'family_id' => $familyRecord->family_id,
                        'image_path' => $path,
                    ]);
                }
            });

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('report-photos', 'public');

                    ReportImage::create([
                        'report_id' => $report->report_id,
                        'image_path' => $path,
                    ]);
                }
            }
        });

        return redirect()
            ->route('field-officer.dashboard')
            ->with('status', 'Disaster report submitted from the web reporting dashboard.');
    }

    public function storeAccident(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'barangay_id')->where('status', 'active')],
            'purok' => ['nullable', 'string', 'max:150'],
            'roadSegment' => ['nullable', 'string', 'max:150'],
            'accidentType' => ['required', 'string', 'max:100'],
            'vehicleType' => ['nullable', 'string', 'max:100'],
            'personName' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'vehiclesInvolved' => ['nullable', 'integer', 'min:1'],
            'injuredCount' => ['nullable', 'integer', 'min:0'],
            'fatalityCount' => ['nullable', 'integer', 'min:0'],
            'involvedPersons' => ['nullable', 'array'],
            'involvedPersons.*.personName' => ['required', 'string', 'max:150'],
            'involvedPersons.*.role' => ['nullable', 'string', 'max:50'],
            'involvedPersons.*.contactNumber' => ['nullable', 'string', 'max:30'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'image', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'incidentDate' => ['required', 'date'],
            'form_context' => ['nullable', 'string'],
        ]);

        $people = collect($validated['involvedPersons'] ?? [])
            ->filter(fn (array $person) => filled($person['personName'] ?? null))
            ->values();

        DB::transaction(function () use ($request, $validated, $people): void {
            $barangay = Barangay::query()->findOrFail($validated['barangay_id']);
            $coordinates = $this->resolvedLocationCoordinates(
                $validated['latitude'] ?? null,
                $validated['longitude'] ?? null,
            );

            $location = IncidentLocation::create([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
                'road_segment' => $validated['roadSegment'] ?? null,
                'sitio_purok' => $validated['purok'] ?? null,
            ]);

            $accident = VehicularAccident::create([
                'user_id' => Auth::id(),
                'location_id' => $location->location_id,
                'accident_type' => $validated['accidentType'],
                'vehicle_type' => $validated['vehicleType'] ?? null,
                'involved_person_name' => $validated['personName'] ?? null,
                'description' => $validated['description'],
                'vehicles_involved' => $validated['vehiclesInvolved'] ?? 1,
                'injured_count' => $validated['injuredCount'] ?? 0,
                'fatality_count' => $validated['fatalityCount'] ?? 0,
                'incident_datetime' => $validated['incidentDate'].' 00:00:00',
                'status' => 'recorded',
                'created_at' => now(),
            ]);

            $people->each(function (array $person) use ($accident): void {
                AccidentInvolvedPerson::create([
                    'accident_id' => $accident->accident_id,
                    'person_name' => $person['personName'],
                    'role' => $person['role'] ?? null,
                    'contact_number' => $person['contactNumber'] ?? null,
                ]);
            });

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('accident-photos', 'public');

                    AccidentImage::create([
                        'accident_id' => $accident->accident_id,
                        'image_path' => $path,
                    ]);
                }
            }
        });

        return redirect()
            ->route('field-officer.dashboard')
            ->with('status', 'Vehicular accident report submitted from the web reporting dashboard.');
    }

    protected function recentSubmissions(Collection $reports, Collection $accidents): array
    {
        $reportRows = $reports->map(function (DamageReport $report): array {
            $families = $report->affectedFamilyRecords->count() ?: (int) $report->affected_families;
            $members = (int) $report->affectedFamilyRecords->sum('household_members');

            return [
                'code' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'module' => 'Disaster Report',
                'title' => $report->disaster_type,
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'status' => ucfirst($report->status),
                'severity' => $this->reportSeverity($report->damage_severity),
                'details' => $families.' families | '.$members.' household members',
                'coordinates' => $this->coordinateLabel($report->location?->latitude, $report->location?->longitude),
                'submitted_at' => optional($report->incident_datetime)->format('M d, Y') ?? 'No date',
                'sort_timestamp' => optional($report->incident_datetime)?->getTimestamp() ?? 0,
            ];
        });

        $accidentRows = $accidents->map(function (VehicularAccident $accident): array {
            return [
                'code' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                'module' => 'Accident Report',
                'title' => $accident->accident_type,
                'barangay' => $accident->location?->barangay?->barangay_name ?? 'Unassigned',
                'status' => ucfirst($accident->status),
                'severity' => $this->accidentSeverity($accident),
                'details' => ($accident->vehicles_involved ?? 1).' vehicles | '.($accident->injured_count ?? 0).' injured | '.($accident->fatality_count ?? 0).' fatalities',
                'coordinates' => $this->coordinateLabel($accident->location?->latitude, $accident->location?->longitude),
                'submitted_at' => optional($accident->incident_datetime)->format('M d, Y') ?? 'No date',
                'sort_timestamp' => optional($accident->incident_datetime)?->getTimestamp() ?? 0,
            ];
        });

        return $reportRows
            ->concat($accidentRows)
            ->sortByDesc('sort_timestamp')
            ->take(10)
            ->map(fn (array $row) => collect($row)->except('sort_timestamp')->all())
            ->values()
            ->all();
    }

    protected function mapPoints(Collection $reports, Collection $accidents): array
    {
        $reportPoints = $reports
            ->filter(fn (DamageReport $report) => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude))
            ->map(function (DamageReport $report): array {
                return [
                    'kind' => 'report',
                    'title' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                    'subtitle' => $report->disaster_type.' | '.($report->location?->barangay?->barangay_name ?? 'Unassigned'),
                    'status' => ucfirst($report->status),
                    'severity' => $this->reportSeverity($report->damage_severity),
                    'lat' => (float) $report->location->latitude,
                    'lng' => (float) $report->location->longitude,
                ];
            });

        $accidentPoints = $accidents
            ->filter(fn (VehicularAccident $accident) => $this->hasCoordinates($accident->location?->latitude, $accident->location?->longitude))
            ->map(function (VehicularAccident $accident): array {
                return [
                    'kind' => 'accident',
                    'title' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                    'subtitle' => $accident->accident_type.' | '.($accident->location?->barangay?->barangay_name ?? 'Unassigned'),
                    'status' => ucfirst($accident->status),
                    'severity' => $this->accidentSeverity($accident),
                    'lat' => (float) $accident->location->latitude,
                    'lng' => (float) $accident->location->longitude,
                ];
            });

        return $reportPoints
            ->concat($accidentPoints)
            ->sortByDesc(fn (array $point) => $point['kind'] === 'report' ? 1 : 0)
            ->values()
            ->all();
    }

    protected function resolvedLocationCoordinates(mixed $latitude, mixed $longitude, ?Collection $familyRows = null): array
    {
        if ($this->hasCoordinates($latitude, $longitude)) {
            return [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        }

        foreach ($familyRows ?? collect() as $family) {
            if ($this->hasCoordinates($family['latitude'] ?? null, $family['longitude'] ?? null)) {
                return [
                    'lat' => (float) $family['latitude'],
                    'lng' => (float) $family['longitude'],
                ];
            }
        }

        return ['lat' => 0.0, 'lng' => 0.0];
    }

    protected function hasCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }

    protected function coordinateLabel(mixed $latitude, mixed $longitude): string
    {
        if (! $this->hasCoordinates($latitude, $longitude)) {
            return 'No GPS coordinates';
        }

        return $latitude.', '.$longitude;
    }

    protected function reportSeverity(?string $severity): string
    {
        return match ($severity) {
            'severe' => 'High',
            'moderate' => 'Medium',
            default => 'Low',
        };
    }

    protected function accidentSeverity(VehicularAccident $accident): string
    {
        if ($accident->fatality_count > 0 || $accident->injured_count >= 3) {
            return 'High';
        }

        if ($accident->injured_count > 0 || $accident->vehicles_involved > 1) {
            return 'Medium';
        }

        return 'Low';
    }

    protected function mapCenter(): array
    {
        return ['lat' => 6.688099, 'lng' => 125.166607];
    }
}
