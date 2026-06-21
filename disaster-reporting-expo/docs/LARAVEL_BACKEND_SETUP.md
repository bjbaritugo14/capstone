# Laravel Backend Setup for `mdrrmo`

This Expo app should not connect directly to MySQL. Connect it like this:

```text
Expo mobile app -> Laravel API -> MySQL database mdrrmo
```

## 1. Laravel `.env`

In your Laravel web project, set:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mdrrmo
DB_USERNAME=root
DB_PASSWORD=
```

If you use Laravel Sanctum for mobile login:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Your existing `users` table uses `user_id`, not Laravel's default `id`, so configure the model.

## 2. Laravel Models

Create or update these files in your Laravel project.

### `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];
}
```

### `app/Models/Barangay.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $table = 'barangays';
    protected $primaryKey = 'barangay_id';
    public $timestamps = false;

    protected $fillable = ['barangay_name', 'municipality', 'province'];
}
```

### `app/Models/IncidentLocation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentLocation extends Model
{
    protected $table = 'incident_locations';
    protected $primaryKey = 'location_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'barangay_id',
        'latitude',
        'longitude',
        'road_segment',
        'sitio_purok',
    ];

    public function barangay()
    {
        return $this->belongsTo(Barangay::class, 'barangay_id', 'barangay_id');
    }
}
```

### `app/Models/DisasterReport.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisasterReport extends Model
{
    protected $table = 'disaster_reports';
    protected $primaryKey = 'report_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'location_id',
        'disaster_type',
        'description',
        'damage_severity',
        'affected_families',
        'affected_structures',
        'incident_datetime',
        'status',
    ];

    protected $casts = [
        'incident_datetime' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(IncidentLocation::class, 'location_id', 'location_id');
    }

    public function images()
    {
        return $this->hasMany(ReportImage::class, 'report_id', 'report_id');
    }

    public function affectedFamilyRecords()
    {
        return $this->hasMany(AffectedFamily::class, 'report_id', 'report_id');
    }
}
```

### `app/Models/ReportImage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportImage extends Model
{
    protected $table = 'report_images';
    protected $primaryKey = 'image_id';
    const CREATED_AT = 'uploaded_at';
    const UPDATED_AT = null;

    protected $fillable = ['report_id', 'image_path'];
}
```

### `app/Models/AffectedFamily.php`

Use this model for your new `affected_families` table.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffectedFamily extends Model
{
    protected $table = 'affected_families';
    protected $primaryKey = 'family_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'family_head_name',
        'household_members',
        'contact_number',
        'evacuation_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(DisasterReport::class, 'report_id', 'report_id');
    }
}
```

Your table should have a foreign key back to `disaster_reports`:

```sql
ALTER TABLE affected_families
ADD CONSTRAINT fk_affected_family_report
FOREIGN KEY (report_id) REFERENCES disaster_reports(report_id)
ON DELETE CASCADE
ON UPDATE CASCADE;
```

Note: `disaster_reports.affected_families` is still the total count. The new `affected_families` table stores the detailed family records. If you want to avoid confusion later, you can rename the count column to `affected_families_count`, but the current Expo app expects the count value.

### `app/Models/VehicularAccident.php`

Use this model for your `vehicular_accidents` table.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicularAccident extends Model
{
    protected $table = 'vehicular_accidents';
    protected $primaryKey = 'accident_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'location_id',
        'accident_type',
        'description',
        'vehicles_involved',
        'injured_count',
        'fatality_count',
        'incident_datetime',
        'status',
    ];

    protected $casts = [
        'incident_datetime' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(IncidentLocation::class, 'location_id', 'location_id');
    }
}
```

## 3. API Routes

Put this in `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VehicularAccidentController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::put('/reports/{report}', [ReportController::class, 'update']);
    Route::delete('/reports/{report}', [ReportController::class, 'destroy']);

    Route::get('/vehicular-accidents', [VehicularAccidentController::class, 'index']);
    Route::post('/vehicular-accidents', [VehicularAccidentController::class, 'store']);
    Route::put('/vehicular-accidents/{accident}', [VehicularAccidentController::class, 'update']);
    Route::delete('/vehicular-accidents/{accident}', [VehicularAccidentController::class, 'destroy']);
});
```

## 4. Auth Controller

Create `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid login credentials.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('expo-mobile')->plainTextToken,
            'user' => [
                'id' => $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
        ]);
    }
}
```

## 5. Report Controller

Create `app/Http/Controllers/Api/ReportController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\DisasterReport;
use App\Models\IncidentLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return DisasterReport::with(['location.barangay', 'images'])
            ->latest('created_at')
            ->get()
            ->map(fn (DisasterReport $report) => $this->toMobileJson($report));
    }

    public function store(Request $request)
    {
        $data = $this->validateReport($request);

        $report = DB::transaction(function () use ($data, $request) {
            $barangay = Barangay::firstOrCreate(
                ['barangay_name' => $data['barangay']],
                [
                    'municipality' => $data['city'] ?: 'Matanao',
                    'province' => $data['province'] ?: 'Davao del Sur',
                ]
            );

            $location = IncidentLocation::create([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'sitio_purok' => $data['purok'] ?? null,
            ]);

            return DisasterReport::create([
                'user_id' => $request->user()->user_id,
                'location_id' => $location->location_id,
                'disaster_type' => $data['disasterType'],
                'description' => $data['description'],
                'damage_severity' => $data['severity'],
                'affected_families' => $data['affectedFamilies'] ?? 0,
                'affected_structures' => $data['affectedStructures'] ?? 0,
                'incident_datetime' => $data['reportDate'] . ' 00:00:00',
                'status' => 'pending',
            ]);
        });

        return response()->json($this->toMobileJson($report->load(['location.barangay', 'images'])), 201);
    }

    public function update(Request $request, DisasterReport $report)
    {
        $data = $this->validateReport($request);

        DB::transaction(function () use ($data, $report) {
            $barangay = Barangay::firstOrCreate(
                ['barangay_name' => $data['barangay']],
                [
                    'municipality' => $data['city'] ?: 'Matanao',
                    'province' => $data['province'] ?: 'Davao del Sur',
                ]
            );

            $report->location()->update([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'sitio_purok' => $data['purok'] ?? null,
            ]);

            $report->update([
                'disaster_type' => $data['disasterType'],
                'description' => $data['description'],
                'damage_severity' => $data['severity'],
                'affected_families' => $data['affectedFamilies'] ?? 0,
                'affected_structures' => $data['affectedStructures'] ?? 0,
                'incident_datetime' => $data['reportDate'] . ' 00:00:00',
            ]);
        });

        return $this->toMobileJson($report->refresh()->load(['location.barangay', 'images']));
    }

    public function destroy(DisasterReport $report)
    {
        $report->delete();

        return response()->json(['success' => true]);
    }

    private function validateReport(Request $request): array
    {
        return $request->validate([
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'disasterType' => ['required', 'string', 'max:100'],
            'severity' => ['required', 'in:minor,moderate,severe'],
            'affectedFamilies' => ['nullable', 'integer', 'min:0'],
            'affectedStructures' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'reportDate' => ['required', 'date_format:Y-m-d'],
            'photos' => ['array'],
        ]);
    }

    private function toMobileJson(DisasterReport $report): array
    {
        $location = $report->location;
        $barangay = $location?->barangay;

        return [
            'id' => $report->report_id,
            'province' => $barangay?->province ?? '',
            'city' => $barangay?->municipality ?? '',
            'barangay' => $barangay?->barangay_name ?? '',
            'purok' => $location?->sitio_purok ?? '',
            'description' => $report->description,
            'disasterType' => $report->disaster_type,
            'severity' => $report->damage_severity,
            'affectedFamilies' => $report->affected_families,
            'affectedStructures' => $report->affected_structures,
            'photos' => $report->images->pluck('image_path')->values(),
            'longitude' => (string) $location?->longitude,
            'latitude' => (string) $location?->latitude,
            'reportDate' => optional($report->incident_datetime)->format('Y-m-d'),
            'createdAt' => optional($report->created_at)->toISOString(),
        ];
    }
}
```

## 6. Vehicular Accident Controller

Create `app/Http/Controllers/Api/VehicularAccidentController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\IncidentLocation;
use App\Models\VehicularAccident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicularAccidentController extends Controller
{
    public function index()
    {
        return VehicularAccident::with('location.barangay')
            ->latest('created_at')
            ->get()
            ->map(fn (VehicularAccident $accident) => $this->toMobileJson($accident));
    }

    public function store(Request $request)
    {
        $data = $this->validateAccident($request);

        $accident = DB::transaction(function () use ($data, $request) {
            $barangay = Barangay::firstOrCreate(
                ['barangay_name' => $data['barangay']],
                [
                    'municipality' => $data['city'] ?: 'Matanao',
                    'province' => $data['province'] ?: 'Davao del Sur',
                ]
            );

            $location = IncidentLocation::create([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'sitio_purok' => $data['purok'] ?? null,
            ]);

            return VehicularAccident::create([
                'user_id' => $request->user()->user_id,
                'location_id' => $location->location_id,
                'accident_type' => $data['accidentType'],
                'description' => $data['description'],
                'vehicles_involved' => $data['vehiclesInvolved'] ?? 1,
                'injured_count' => $data['injuredCount'] ?? 0,
                'fatality_count' => $data['fatalityCount'] ?? 0,
                'incident_datetime' => $data['incidentDate'] . ' 00:00:00',
                'status' => 'recorded',
            ]);
        });

        return response()->json($this->toMobileJson($accident->load('location.barangay')), 201);
    }

    public function update(Request $request, VehicularAccident $accident)
    {
        $data = $this->validateAccident($request);

        DB::transaction(function () use ($data, $accident) {
            $barangay = Barangay::firstOrCreate(
                ['barangay_name' => $data['barangay']],
                [
                    'municipality' => $data['city'] ?: 'Matanao',
                    'province' => $data['province'] ?: 'Davao del Sur',
                ]
            );

            $accident->location()->update([
                'barangay_id' => $barangay->barangay_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'sitio_purok' => $data['purok'] ?? null,
            ]);

            $accident->update([
                'accident_type' => $data['accidentType'],
                'description' => $data['description'],
                'vehicles_involved' => $data['vehiclesInvolved'] ?? 1,
                'injured_count' => $data['injuredCount'] ?? 0,
                'fatality_count' => $data['fatalityCount'] ?? 0,
                'incident_datetime' => $data['incidentDate'] . ' 00:00:00',
            ]);
        });

        return $this->toMobileJson($accident->refresh()->load('location.barangay'));
    }

    public function destroy(VehicularAccident $accident)
    {
        $accident->delete();

        return response()->json(['success' => true]);
    }

    private function validateAccident(Request $request): array
    {
        return $request->validate([
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'purok' => ['nullable', 'string', 'max:150'],
            'accidentType' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'vehiclesInvolved' => ['nullable', 'integer', 'min:1'],
            'injuredCount' => ['nullable', 'integer', 'min:0'],
            'fatalityCount' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'incidentDate' => ['required', 'date_format:Y-m-d'],
        ]);
    }

    private function toMobileJson(VehicularAccident $accident): array
    {
        $location = $accident->location;
        $barangay = $location?->barangay;

        return [
            'id' => $accident->accident_id,
            'province' => $barangay?->province ?? '',
            'city' => $barangay?->municipality ?? '',
            'barangay' => $barangay?->barangay_name ?? '',
            'purok' => $location?->sitio_purok ?? '',
            'accidentType' => $accident->accident_type,
            'description' => $accident->description,
            'vehiclesInvolved' => $accident->vehicles_involved,
            'injuredCount' => $accident->injured_count,
            'fatalityCount' => $accident->fatality_count,
            'longitude' => (string) $location?->longitude,
            'latitude' => (string) $location?->latitude,
            'incidentDate' => optional($accident->incident_datetime)->format('Y-m-d'),
            'status' => $accident->status,
            'createdAt' => optional($accident->created_at)->toISOString(),
        ];
    }
}
```

## 7. Expo `.env`

In this Expo project, create `.env` from `.env.example`:

```env
EXPO_PUBLIC_USE_MOCK_API=false
EXPO_PUBLIC_API_BASE_URL=http://YOUR_COMPUTER_LAN_IP:8000/api
```

Examples:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.50:8000/api
```

Run Laravel so your phone can reach it:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Then restart Expo:

```bash
npx expo start -c
```

Use your computer's LAN IP when testing on a physical phone. `127.0.0.1` points to the phone itself, not your Laravel server.
