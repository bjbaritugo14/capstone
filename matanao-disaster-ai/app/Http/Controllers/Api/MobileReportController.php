<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ReportImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MobileReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = DamageReport::query()
            ->with(['location.barangay', 'images', 'affectedFamilyRecords.images'])
            ->where('user_id', $request->user()->user_id)
            ->latest('created_at')
            ->get()
            ->map(fn (DamageReport $report) => $this->toMobile($report));

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        \Log::info('MobileReportController@store called', [
            'user_id' => $request->user()?->user_id,
            'payload' => $request->except('photos', 'family_photos_*'),
        ]);

        // Families may come as JSON string (from multipart/form-data)
        $familiesRaw = $request->input('families');
        if (is_string($familiesRaw)) {
            $request->merge(['families' => json_decode($familiesRaw, true) ?? []]);
        }

        $validated = $request->validate([
            'barangay' => ['required', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'disasterType' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:minor,moderate,severe'],
            'families' => ['nullable', 'array'],
            'families.*.familyHeadName' => ['required', 'string', 'max:150'],
            'families.*.householdMembers' => ['required', 'integer', 'min:0'],
            'families.*.contactNumber' => ['nullable', 'string', 'max:20'],
            'families.*.evacuationStatus' => ['nullable', 'string', 'max:50'],
            'families.*.description' => ['nullable', 'string'],
            'families.*.severity' => ['nullable', 'in:minor,moderate,severe'],
            'families.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'families.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'affectedStructures' => ['nullable', 'integer', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'image', 'max:10240'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'reportDate' => ['required', 'date'],
        ]);

        try {
            $barangay = $this->barangayFromPayload($validated);
            $location = $this->createLocation($validated, $barangay);

            $families = $validated['families'] ?? [];

            // Auto-count structures from families
            $affectedStructures = count($families) > 0 ? count($families) : ($validated['affectedStructures'] ?? 0);

            $report = DamageReport::create([
                'user_id' => $request->user()->user_id,
                'location_id' => $location->location_id,
                'disaster_type' => $validated['disasterType'],
                'description' => $validated['description'] ?? '',
                'damage_severity' => $validated['severity'],
                'affected_families' => count($families),
                'affected_structures' => $affectedStructures,
                'incident_datetime' => $validated['reportDate'].' 00:00:00',
                'status' => 'pending',
                'created_at' => now(),
            ]);

            foreach ($families as $index => $family) {
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

                // Store per-family photos
                $familyPhotosKey = "family_photos_{$index}";
                if ($request->hasFile($familyPhotosKey)) {
                    foreach ($request->file($familyPhotosKey) as $photo) {
                        $path = $photo->store('report-photos', 'public');
                        ReportImage::create([
                            'report_id' => $report->report_id,
                            'family_id' => $familyRecord->family_id,
                            'image_path' => $path,
                        ]);
                    }
                }
            }

            // Store top-level photos (backward compat)
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('report-photos', 'public');
                    ReportImage::create([
                        'report_id' => $report->report_id,
                        'image_path' => $path,
                    ]);
                }
            }

            return response()->json($this->toMobile($report->load(['location.barangay', 'images', 'affectedFamilyRecords.images'])), 201);
        } catch (\Exception $e) {
            \Log::error('MobileReportController@store failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to save report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, DamageReport $report): JsonResponse
    {
        abort_if($report->user_id !== $request->user()->user_id, 403);

        // Families may come as JSON string (from multipart/form-data)
        $familiesRaw = $request->input('families');
        if (is_string($familiesRaw)) {
            $request->merge(['families' => json_decode($familiesRaw, true) ?? []]);
        }

        $validated = $request->validate([
            'barangay' => ['required', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'disasterType' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:minor,moderate,severe'],
            'families' => ['nullable', 'array'],
            'families.*.familyHeadName' => ['required', 'string', 'max:150'],
            'families.*.householdMembers' => ['required', 'integer', 'min:0'],
            'families.*.contactNumber' => ['nullable', 'string', 'max:20'],
            'families.*.evacuationStatus' => ['nullable', 'string', 'max:50'],
            'families.*.description' => ['nullable', 'string'],
            'families.*.severity' => ['nullable', 'in:minor,moderate,severe'],
            'families.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'families.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'affectedStructures' => ['nullable', 'integer', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'image', 'max:10240'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'reportDate' => ['required', 'date'],
        ]);

        $barangay = $this->barangayFromPayload($validated);
        $report->location()->update([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'sitio_purok' => $validated['purok'] ?? null,
        ]);

        $families = $validated['families'] ?? [];
        $affectedStructures = count($families) > 0 ? count($families) : ($validated['affectedStructures'] ?? 0);

        $report->update([
            'disaster_type' => $validated['disasterType'],
            'description' => $validated['description'] ?? '',
            'damage_severity' => $validated['severity'],
            'affected_families' => count($families),
            'affected_structures' => $affectedStructures,
            'incident_datetime' => $validated['reportDate'].' 00:00:00',
        ]);

        // Delete old images from storage
        foreach ($report->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        $report->images()->delete();

        // Replace families
        $report->affectedFamilyRecords()->delete();
        foreach ($families as $index => $family) {
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

            // Store per-family photos (new uploads)
            $familyPhotosKey = "family_photos_{$index}";
            if ($request->hasFile($familyPhotosKey)) {
                foreach ($request->file($familyPhotosKey) as $photo) {
                    $path = $photo->store('report-photos', 'public');
                    ReportImage::create([
                        'report_id' => $report->report_id,
                        'family_id' => $familyRecord->family_id,
                        'image_path' => $path,
                    ]);
                }
            }

            // Re-add existing per-family photos
            $existingKey = "existing_family_photos_{$index}";
            foreach ($request->input($existingKey, []) as $existingPath) {
                ReportImage::create([
                    'report_id' => $report->report_id,
                    'family_id' => $familyRecord->family_id,
                    'image_path' => $existingPath,
                ]);
            }
        }

        // Store top-level photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('report-photos', 'public');
                ReportImage::create([
                    'report_id' => $report->report_id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json($this->toMobile($report->load(['location.barangay', 'images', 'affectedFamilyRecords.images'])));
    }

    public function destroy(Request $request, DamageReport $report): JsonResponse
    {
        abort_if($report->user_id !== $request->user()->user_id, 403);

        // Delete stored images
        foreach ($report->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $report->delete();

        return response()->json(['deleted' => true]);
    }

    protected function barangayFromPayload(array $payload): Barangay
    {
        return Barangay::firstOrCreate([
            'barangay_name' => $payload['barangay'],
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
        ]);
    }

    protected function createLocation(array $payload, Barangay $barangay): IncidentLocation
    {
        return IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $payload['latitude'] ?? 0.0,
            'longitude' => $payload['longitude'] ?? 0.0,
            'sitio_purok' => $payload['purok'] ?? null,
        ]);
    }

    protected function toMobile(DamageReport $report): array
    {
        return [
            'id' => $report->report_id,
            'barangay' => $report->location?->barangay?->barangay_name ?? '',
            'purok' => $report->location?->sitio_purok ?? '',
            'description' => $report->description ?? '',
            'disasterType' => $report->disaster_type,
            'severity' => $report->damage_severity,
            'families' => $report->affectedFamilyRecords->map(fn (AffectedFamily $f) => [
                'familyHeadName' => $f->family_head_name,
                'householdMembers' => (int) $f->household_members,
                'contactNumber' => $f->contact_number ?? '',
                'evacuationStatus' => $f->evacuation_status ?? '',
                'description' => $f->description ?? '',
                'severity' => $f->damage_severity ?? $report->damage_severity,
                'latitude' => $f->latitude ? (string) $f->latitude : '',
                'longitude' => $f->longitude ? (string) $f->longitude : '',
                'photos' => $f->images->pluck('image_path')->all(),
            ])->all(),
            'affectedStructures' => $report->affected_structures,
            'photos' => $report->images->whereNull('family_id')->pluck('image_path')->all(),
            'longitude' => (string) $report->location?->longitude,
            'latitude' => (string) $report->location?->latitude,
            'reportDate' => optional($report->incident_datetime)->format('Y-m-d'),
            'createdAt' => (string) $report->created_at,
        ];
    }
}
