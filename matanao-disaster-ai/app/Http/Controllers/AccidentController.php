<?php

namespace App\Http\Controllers;

use App\Models\VehicularAccident;
use App\Models\Barangay;
use App\Models\IncidentLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccidentController extends Controller
{
    protected function accidents(): array
    {
        return VehicularAccident::query()
            ->with(['location.barangay'])
            ->latest('incident_datetime')
            ->get()
            ->map(fn (VehicularAccident $accident) => [
                'id' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $accident->location?->barangay?->barangay_name ?? 'Unassigned',
                'road_segment' => $accident->location?->road_segment ?? 'Unspecified',
                'incident_type' => $accident->accident_type,
                'vehicle_type' => $accident->vehicle_type ?? 'Not specified',
                'person_name' => $accident->involved_person_name ?? 'Not specified',
                'time' => optional($accident->incident_datetime)->format('h:i A') ?? '',
                'date' => optional($accident->incident_datetime)->format('Y-m-d') ?? '',
                'status' => ucfirst($accident->status),
                'coordinates' => $accident->location?->latitude.', '.$accident->location?->longitude,
                'severity' => $this->severity($accident),
            ])
            ->all();
    }

    public function index(): View
    {
        $accidents = $this->accidents();

        $stats = [
            'total' => count($accidents),
            'high_risk' => count(array_filter($accidents, fn (array $accident) => $accident['severity'] === 'High')),
            'under_review' => count(array_filter($accidents, fn (array $accident) => $accident['status'] === 'Recorded')),
            'hotspots' => collect($accidents)->groupBy('road_segment')->filter(fn ($items) => $items->count() >= 2)->count(),
        ];

        $hotspots = collect($accidents)
            ->groupBy('road_segment')
            ->map(fn ($items, $roadSegment) => [
                'location' => $roadSegment,
                'incidents' => $items->count(),
                'trend' => $items->count() >= 5 ? 'High' : ($items->count() >= 2 ? 'Medium' : 'Low'),
            ])
            ->values()
            ->all();

        $mapCenter = ['lat' => 6.688099, 'lng' => 125.166607];

        $mapPoints = array_map(function (array $accident) {
            [$lat, $lng] = array_map('trim', explode(',', $accident['coordinates']));

            return [
                'id' => $accident['id'],
                'barangay' => $accident['barangay'],
                'road_segment' => $accident['road_segment'],
                'incident_type' => $accident['incident_type'],
                'status' => $accident['status'],
                'severity' => $accident['severity'],
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }, $accidents);

        $barangays = Barangay::orderBy('barangay_name')->get();

        return view('accidents.index', compact('accidents', 'stats', 'hotspots', 'mapCenter', 'mapPoints', 'barangays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'exists:barangays,barangay_id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'road_segment' => ['nullable', 'string', 'max:150'],
            'sitio_purok' => ['nullable', 'string', 'max:150'],
            'accident_type' => ['required', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'involved_person_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'vehicles_involved' => ['nullable', 'integer', 'min:1'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'fatality_count' => ['nullable', 'integer', 'min:0'],
            'incident_datetime' => ['required', 'date'],
        ]);

        $location = IncidentLocation::create([
            'barangay_id' => $validated['barangay_id'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'road_segment' => $validated['road_segment'] ?? null,
            'sitio_purok' => $validated['sitio_purok'] ?? null,
        ]);

        VehicularAccident::create([
            'user_id' => Auth::id(),
            'location_id' => $location->location_id,
            'accident_type' => $validated['accident_type'],
            'vehicle_type' => $validated['vehicle_type'] ?? null,
            'involved_person_name' => $validated['involved_person_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'vehicles_involved' => $validated['vehicles_involved'] ?? 1,
            'injured_count' => $validated['injured_count'] ?? 0,
            'fatality_count' => $validated['fatality_count'] ?? 0,
            'incident_datetime' => $validated['incident_datetime'],
            'status' => 'recorded',
        ]);

        return redirect()->route('accidents.index')->with('status', 'Vehicular accident record saved.');
    }

    protected function severity(VehicularAccident $accident): string
    {
        if ($accident->fatality_count > 0 || $accident->injured_count >= 3) {
            return 'High';
        }

        if ($accident->injured_count > 0 || $accident->vehicles_involved > 1) {
            return 'Medium';
        }

        return 'Low';
    }
}
