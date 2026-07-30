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
use Illuminate\Validation\ValidationException;

class MobileReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = DamageReport::query()
            ->with([
                'location.barangay',
                'images',
                'affectedFamilyRecords.images',
                'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
            ])
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
            $families = $validated['families'] ?? [];
            $location = $this->createLocation($validated, $barangay, $families);

            $report = DamageReport::create([
                'user_id' => $request->user()->user_id,
                'location_id' => $location->location_id,
                'disaster_type' => $validated['disasterType'],
                'description' => $validated['description'] ?? '',
                'damage_severity' => $validated['severity'],
                'affected_families' => count($families),
                'affected_structures' => $validated['affectedStructures'] ?? 0,
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

            return response()->json($this->toMobile($report->load([
                'location.barangay',
                'images',
                'affectedFamilyRecords.images',
                'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
            ])), 201);
        } catch (ValidationException $e) {
            throw $e;
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
        $families = $validated['families'] ?? [];
        $coordinates = $this->resolvedCoordinates(
            $validated['latitude'] ?? null,
            $validated['longitude'] ?? null,
            $families,
        );

        $report->location()->update([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'sitio_purok' => $validated['purok'] ?? null,
        ]);

        $report->update([
            'disaster_type' => $validated['disasterType'],
            'description' => $validated['description'] ?? '',
            'damage_severity' => $validated['severity'],
            'affected_families' => count($families),
            'affected_structures' => $validated['affectedStructures'] ?? 0,
            'incident_datetime' => $validated['reportDate'].' 00:00:00',
            'status' => $report->status === 'returned' ? 'pending' : $report->status,
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

        return response()->json($this->toMobile($report->load([
            'location.barangay',
            'images',
            'affectedFamilyRecords.images',
            'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
        ])));
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
        $barangay = Barangay::query()
            ->active()
            ->where('barangay_name', trim((string) $payload['barangay']))
            ->first();

        if ($barangay !== null) {
            return $barangay;
        }

        throw ValidationException::withMessages([
            'barangay' => 'The selected barangay is not available for reporting.',
        ]);
    }

    protected function createLocation(array $payload, Barangay $barangay, array $families): IncidentLocation
    {
        $coordinates = $this->resolvedCoordinates(
            $payload['latitude'] ?? null,
            $payload['longitude'] ?? null,
            $families,
        );

        return IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'sitio_purok' => $payload['purok'] ?? null,
        ]);
    }

    protected function resolvedCoordinates(mixed $latitude, mixed $longitude, array $families): array
    {
        if ($this->hasCoordinates($latitude, $longitude)) {
            return [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        }

        foreach ($families as $family) {
            if ($this->hasCoordinates($family['latitude'] ?? null, $family['longitude'] ?? null)) {
                return [
                    'lat' => (float) $family['latitude'],
                    'lng' => (float) $family['longitude'],
                ];
            }
        }

        throw ValidationException::withMessages([
            'latitude' => 'Capture a valid GPS location before submitting the report.',
            'longitude' => 'Capture a valid GPS location before submitting the report.',
        ]);
    }

    protected function hasCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }

    protected function toMobile(DamageReport $report): array
    {
        $latestValidation = $report->validations->first();
        $showValidationFeedback = $report->status !== 'pending';

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
            'longitude' => $this->hasCoordinates($report->location?->longitude, $report->location?->latitude)
                ? (string) $report->location?->longitude
                : '',
            'latitude' => $this->hasCoordinates($report->location?->latitude, $report->location?->longitude)
                ? (string) $report->location?->latitude
                : '',
            'reportDate' => optional($report->incident_datetime)->format('Y-m-d'),
            'status' => $report->status,
            'validationRemarks' => $showValidationFeedback ? ($latestValidation?->remarks ?? '') : '',
            'validatedAt' => $showValidationFeedback ? (optional($latestValidation?->validated_at)->toIso8601String() ?? '') : '',
            'validatedBy' => $showValidationFeedback ? ($latestValidation?->validator?->full_name ?? '') : '',
            'createdAt' => (string) $report->created_at,
        ];
    }
}
