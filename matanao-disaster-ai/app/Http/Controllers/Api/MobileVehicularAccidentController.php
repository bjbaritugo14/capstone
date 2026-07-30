<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccidentImage;
use App\Models\AccidentInvolvedPerson;
use App\Models\AccidentValidation;
use App\Models\Barangay;
use App\Models\IncidentLocation;
use App\Models\VehicularAccident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MobileVehicularAccidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accidents = VehicularAccident::query()
            ->with([
                'location.barangay',
                'involvedPersons',
                'images',
                'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
            ])
            ->where('user_id', $request->user()->user_id)
            ->latest('created_at')
            ->get()
            ->map(fn (VehicularAccident $accident) => $this->toMobile($accident));

        return response()->json($accidents);
    }

    public function store(Request $request): JsonResponse
    {
        // involvedPersons may come as JSON string from multipart/form-data
        $personsRaw = $request->input('involvedPersons');
        if (is_string($personsRaw)) {
            $request->merge(['involvedPersons' => json_decode($personsRaw, true) ?? []]);
        }

        $validated = $this->validated($request);
        $barangay = $this->barangayFromPayload($validated);
        $location = $this->createLocation($validated, $barangay);

        $involvedPersons = $validated['involvedPersons'] ?? [];

        $accident = VehicularAccident::create([
            'user_id' => $request->user()->user_id,
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
        ]);

        foreach ($involvedPersons as $person) {
            AccidentInvolvedPerson::create([
                'accident_id' => $accident->accident_id,
                'person_name' => $person['personName'],
                'role' => $person['role'] ?? null,
                'contact_number' => $person['contactNumber'] ?? null,
            ]);
        }

        // Store uploaded photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('accident-photos', 'public');
                AccidentImage::create([
                    'accident_id' => $accident->accident_id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json($this->toMobile($accident->load([
            'location.barangay',
            'involvedPersons',
            'images',
            'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
        ])), 201);
    }

    public function update(Request $request, VehicularAccident $vehicularAccident): JsonResponse
    {
        abort_if($vehicularAccident->user_id !== $request->user()->user_id, 403);

        // involvedPersons may come as JSON string from multipart/form-data
        $personsRaw = $request->input('involvedPersons');
        if (is_string($personsRaw)) {
            $request->merge(['involvedPersons' => json_decode($personsRaw, true) ?? []]);
        }

        $validated = $this->validated($request);
        $barangay = $this->barangayFromPayload($validated);
        $coordinates = $this->resolvedCoordinates(
            $validated['latitude'] ?? null,
            $validated['longitude'] ?? null,
        );

        $vehicularAccident->location()->update([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'road_segment' => $validated['roadSegment'] ?? null,
            'sitio_purok' => $validated['purok'] ?? null,
        ]);

        $vehicularAccident->update([
            'accident_type' => $validated['accidentType'],
            'vehicle_type' => $validated['vehicleType'] ?? null,
            'involved_person_name' => $validated['personName'] ?? null,
            'description' => $validated['description'],
            'vehicles_involved' => $validated['vehiclesInvolved'] ?? 1,
            'injured_count' => $validated['injuredCount'] ?? 0,
            'fatality_count' => $validated['fatalityCount'] ?? 0,
            'incident_datetime' => $validated['incidentDate'].' 00:00:00',
            'status' => $vehicularAccident->status === 'returned' ? 'recorded' : $vehicularAccident->status,
        ]);

        // Replace involved persons
        $vehicularAccident->involvedPersons()->delete();
        foreach (($validated['involvedPersons'] ?? []) as $person) {
            AccidentInvolvedPerson::create([
                'accident_id' => $vehicularAccident->accident_id,
                'person_name' => $person['personName'],
                'role' => $person['role'] ?? null,
                'contact_number' => $person['contactNumber'] ?? null,
            ]);
        }

        // Delete old images
        foreach ($vehicularAccident->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
        $vehicularAccident->images()->delete();

        // Re-add existing photos that were kept
        foreach ($request->input('existing_photos', []) as $existingPath) {
            AccidentImage::create([
                'accident_id' => $vehicularAccident->accident_id,
                'image_path' => $existingPath,
            ]);
        }

        // Store new uploaded photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('accident-photos', 'public');
                AccidentImage::create([
                    'accident_id' => $vehicularAccident->accident_id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json($this->toMobile($vehicularAccident->load([
            'location.barangay',
            'involvedPersons',
            'images',
            'validations' => fn ($query) => $query->with('validator')->latest('validated_at'),
        ])));
    }

    public function destroy(Request $request, VehicularAccident $vehicularAccident): JsonResponse
    {
        abort_if($vehicularAccident->user_id !== $request->user()->user_id, 403);

        // Delete stored images
        foreach ($vehicularAccident->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $vehicularAccident->delete();

        return response()->json(['deleted' => true]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'barangay' => ['required', 'string', 'max:100'],
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
            'existing_photos' => ['nullable', 'array'],
            'existing_photos.*' => ['string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'incidentDate' => ['required', 'date'],
        ]);
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
            'barangay' => 'The selected barangay is not available for accident reporting.',
        ]);
    }

    protected function createLocation(array $payload, Barangay $barangay): IncidentLocation
    {
        $coordinates = $this->resolvedCoordinates(
            $payload['latitude'] ?? null,
            $payload['longitude'] ?? null,
        );

        return IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
            'road_segment' => $payload['roadSegment'] ?? null,
            'sitio_purok' => $payload['purok'] ?? null,
        ]);
    }

    protected function resolvedCoordinates(mixed $latitude, mixed $longitude): array
    {
        if ($this->hasCoordinates($latitude, $longitude)) {
            return [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        }

        throw ValidationException::withMessages([
            'latitude' => 'Capture a valid GPS location before submitting the accident report.',
            'longitude' => 'Capture a valid GPS location before submitting the accident report.',
        ]);
    }

    protected function hasCoordinates(mixed $latitude, mixed $longitude): bool
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return ! ((float) $latitude === 0.0 && (float) $longitude === 0.0);
    }

    protected function toMobile(VehicularAccident $accident): array
    {
        $latestValidation = $accident->validations->first();
        $showValidationFeedback = $accident->status !== 'recorded';

        return [
            'id' => $accident->accident_id,
            'barangay' => $accident->location?->barangay?->barangay_name ?? '',
            'purok' => $accident->location?->sitio_purok ?? '',
            'roadSegment' => $accident->location?->road_segment ?? '',
            'accidentType' => $accident->accident_type,
            'vehicleType' => $accident->vehicle_type ?? '',
            'personName' => $accident->involved_person_name ?? '',
            'description' => $accident->description ?? '',
            'vehiclesInvolved' => $accident->vehicles_involved,
            'injuredCount' => $accident->injured_count,
            'fatalityCount' => $accident->fatality_count,
            'involvedPersons' => $accident->involvedPersons->map(fn ($p) => [
                'personName' => $p->person_name,
                'role' => $p->role ?? '',
                'contactNumber' => $p->contact_number ?? '',
            ])->all(),
            'photos' => $accident->images->pluck('image_path')->all(),
            'longitude' => $this->hasCoordinates($accident->location?->longitude, $accident->location?->latitude)
                ? (string) $accident->location?->longitude
                : '',
            'latitude' => $this->hasCoordinates($accident->location?->latitude, $accident->location?->longitude)
                ? (string) $accident->location?->latitude
                : '',
            'incidentDate' => optional($accident->incident_datetime)->format('Y-m-d'),
            'status' => $accident->status,
            'validationRemarks' => $showValidationFeedback ? ($latestValidation?->remarks ?? '') : '',
            'validatedAt' => $showValidationFeedback ? (optional($latestValidation?->validated_at)->toIso8601String() ?? '') : '',
            'validatedBy' => $showValidationFeedback ? ($latestValidation?->validator?->full_name ?? '') : '',
            'createdAt' => (string) $accident->created_at,
        ];
    }
}
