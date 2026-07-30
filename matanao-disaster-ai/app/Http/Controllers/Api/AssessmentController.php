<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'submitted_reports' => DamageReport::count(),
            'validated_reports' => DamageReport::where('status', 'validated')->count(),
            'affected_households' => DamageReport::sum('affected_families'),
            'high_severity_areas' => DamageReport::where('damage_severity', 'severe')->count(),
        ]);
    }

    public function index(): JsonResponse
    {
        $reports = DamageReport::query()
            ->with(['location.barangay', 'user'])
            ->latest('created_at')
            ->get()
            ->map(fn (DamageReport $report) => [
                'id' => $report->report_id,
                'report_code' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'barangay' => $report->location?->barangay?->barangay_name,
                'disaster_type' => $report->disaster_type,
                'damage_severity' => $report->damage_severity,
                'affected_families' => $report->affected_families,
                'affected_structures' => $report->affected_structures,
                'latitude' => $report->location?->latitude,
                'longitude' => $report->location?->longitude,
                'status' => $report->status,
                'submitted_by' => $report->user?->full_name,
                'incident_datetime' => $report->incident_datetime,
            ]);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
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
        ]);

        $location = IncidentLocation::create([
            'barangay_id' => $validated['barangay_id'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'road_segment' => $validated['road_segment'] ?? null,
            'sitio_purok' => $validated['sitio_purok'] ?? null,
        ]);

        $report = DamageReport::create([
            'user_id' => $request->user()->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => $validated['disaster_type'],
            'description' => $validated['description'] ?? null,
            'damage_severity' => $validated['damage_severity'],
            'affected_families' => $validated['affected_families'] ?? 0,
            'affected_structures' => $validated['affected_structures'] ?? 0,
            'incident_datetime' => $validated['incident_datetime'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Assessment stored.',
            'report_id' => $report->report_id,
        ], 201);
    }
}
