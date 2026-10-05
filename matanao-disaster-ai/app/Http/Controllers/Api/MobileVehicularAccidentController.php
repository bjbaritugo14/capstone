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

        $primaryPerson = $this->normalizedPersonName(
            $validated['personFirstName'] ?? null,
            $validated['personLastName'] ?? null,
            $validated['personName'] ?? null,
        );
        $involvedPersons = $this->normalizedPersonRows($validated['involvedPersons'] ?? []);
        if ($primaryPerson['personName'] === '' && $involvedPersons !== []) {
            $primaryPerson = $involvedPersons[0];
        }

        $accident = VehicularAccident::create([
            'user_id' => $request->user()->user_id,
            'location_id' => $location->location_id,
            'accident_type' => $validated['accidentType'],
            'vehicle_type' => $validated['vehicleType'] ?? null,
            'involved_person_name' => $primaryPerson['personName'] ?: null,
            'involved_person_first_name' => $primaryPerson['firstName'] ?: null,
            'involved_person_last_name' => $primaryPerson['lastName'] ?: null,
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
                'first_name' => $person['firstName'],
                'last_name' => $person['lastName'],
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
        $primaryPerson = $this->normalizedPersonName(
            $validated['personFirstName'] ?? null,
            $validated['personLastName'] ?? null,
            $validated['personName'] ?? null,
        );
        $involvedPersons = $this->normalizedPersonRows($validated['involvedPersons'] ?? []);
        if ($primaryPerson['personName'] === '' && $involvedPersons !== []) {
            $primaryPerson = $involvedPersons[0];
        }
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
            'involved_person_name' => $primaryPerson['personName'] ?: null,
            'involved_person_first_name' => $primaryPerson['firstName'] ?: null,
            'involved_person_last_name' => $primaryPerson['lastName'] ?: null,
            'description' => $validated['description'],
            'vehicles_involved' => $validated['vehiclesInvolved'] ?? 1,
            'injured_count' => $validated['injuredCount'] ?? 0,
            'fatality_count' => $validated['fatalityCount'] ?? 0,
            'incident_datetime' => $validated['incidentDate'].' 00:00:00',
            'status' => $vehicularAccident->status === 'returned' ? 'recorded' : $vehicularAccident->status,
        ]);

        // Replace involved persons
        $vehicularAccident->involvedPersons()->delete();
        foreach ($involvedPersons as $person) {
            AccidentInvolvedPerson::create([
                'accident_id' => $vehicularAccident->accident_id,
                'person_name' => $person['personName'],
                'first_name' => $person['firstName'],
                'last_name' => $person['lastName'],
                'role' => $person['role'] ?? null,
                'contact_number' => $person['contactNumber'] ?? null,
            ]);
        }

        // Delete old images
        foreach ($vehicularAccident->images as $image) {
            Storage::disk('public')->delete($this->storedImagePath($image->image_path));
        }
        $vehicularAccident->images()->delete();

        // Re-add existing photos that were kept
        foreach ($request->input('existing_photos', []) as $existingPath) {
            $storedPath = $this->storedImagePath($existingPath);

            if ($storedPath === null) {
                continue;
            }

            AccidentImage::create([
                'accident_id' => $vehicularAccident->accident_id,
                'image_path' => $storedPath,
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
            Storage::disk('public')->delete($this->storedImagePath($image->image_path));
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
            'personFirstName' => ['nullable', 'string', 'max:75'],
            'personLastName' => ['nullable', 'string', 'max:75'],
            'personName' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'vehiclesInvolved' => ['nullable', 'integer', 'min:1'],
            'injuredCount' => ['nullable', 'integer', 'min:0'],
            'fatalityCount' => ['nullable', 'integer', 'min:0'],
            'involvedPersons' => ['nullable', 'array'],
            'involvedPersons.*.firstName' => ['nullable', 'string', 'max:75'],
            'involvedPersons.*.lastName' => ['nullable', 'string', 'max:75'],
            'involvedPersons.*.personName' => ['nullable', 'string', 'max:150'],
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

    protected function normalizedPersonName(mixed $firstName, mixed $lastName, mixed $fallbackName = null): array
    {
        $firstName = trim((string) $firstName);
        $lastName = trim((string) $lastName);
        $fallbackName = trim((string) $fallbackName);

        if (($firstName === '' || $lastName === '') && $fallbackName !== '') {
            [$firstName, $lastName] = $this->splitName($fallbackName);
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'personName' => trim($firstName.' '.$lastName),
        ];
    }

    protected function normalizedPersonRows(array $people): array
    {
        $rows = collect($people)
            ->map(function (array $person): array {
                return [
                    ...$person,
                    ...$this->normalizedPersonName(
                        $person['firstName'] ?? null,
                        $person['lastName'] ?? null,
                        $person['personName'] ?? null,
                    ),
                ];
            })
            ->filter(fn (array $person): bool => $person['firstName'] !== '' || $person['lastName'] !== '')
            ->values();

        $invalidIndex = $rows->search(fn (array $person): bool => $person['firstName'] === '' || $person['lastName'] === '');

        if ($invalidIndex !== false) {
            throw ValidationException::withMessages([
                "involvedPersons.{$invalidIndex}.firstName" => 'Enter both first name and last name for each involved person.',
            ]);
        }

        return $rows->all();
    }

    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) <= 1) {
            return [$parts[0] ?? '', ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
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
        [$personFirstName, $personLastName] = filled($accident->involved_person_first_name) || filled($accident->involved_person_last_name)
            ? [(string) $accident->involved_person_first_name, (string) $accident->involved_person_last_name]
            : $this->splitName($accident->involved_person_name ?? '');

        return [
            'id' => $accident->accident_id,
            'barangay' => $accident->location?->barangay?->barangay_name ?? '',
            'purok' => $accident->location?->sitio_purok ?? '',
            'roadSegment' => $accident->location?->road_segment ?? '',
            'accidentType' => $accident->accident_type,
            'vehicleType' => $accident->vehicle_type ?? '',
            'personFirstName' => $personFirstName,
            'personLastName' => $personLastName,
            'personName' => $accident->involved_person_name ?? '',
            'description' => $accident->description ?? '',
            'vehiclesInvolved' => $accident->vehicles_involved,
            'injuredCount' => $accident->injured_count,
            'fatalityCount' => $accident->fatality_count,
            'involvedPersons' => $accident->involvedPersons->map(function ($p): array {
                [$firstName, $lastName] = filled($p->first_name) || filled($p->last_name)
                    ? [(string) $p->first_name, (string) $p->last_name]
                    : $this->splitName($p->person_name);

                return [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'personName' => $p->person_name,
                    'role' => $p->role ?? '',
                    'contactNumber' => $p->contact_number ?? '',
                ];
            })->all(),
            'photos' => $accident->images
                ->map(fn (AccidentImage $image): string => $this->publicImageUrl($image->image_path))
                ->values()
                ->all(),
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

    protected function publicImageUrl(mixed $path): string
    {
        $storedPath = $this->storedImagePath($path);

        return $storedPath === null ? '' : asset('storage/'.$storedPath);
    }

    protected function storedImagePath(mixed $path): ?string
    {
        $value = trim(str_replace('\\', '/', (string) $path));

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $value = rawurldecode((string) parse_url($value, PHP_URL_PATH));
        }

        $value = ltrim($value, '/');

        foreach (['public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $value = substr($value, strlen($prefix));
            }
        }

        return $value === '' ? null : $value;
    }
}
