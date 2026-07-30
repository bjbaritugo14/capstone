# Disaster Reporting Expo App

A Field Officer disaster and vehicular-accident reporting app for the Matanao Laravel backend.

## Included screens

- Login
- Dashboard with record count, search, edit, and delete
- Reporting form with address, damage details, photos, GPS, and date
- Profile with change password
- Field Officer-only authenticated access

## Tech stack

- Expo SDK 54
- Expo Router
- React Native + TypeScript
- `expo-image-picker` for photos
- `expo-location` for GPS
- `@react-native-picker/picker` for dropdowns

## 1) Install prerequisites

Make sure you have Node.js LTS installed.

## 2) Install dependencies

```bash
npm install
```

## 3) Configure mock or real Laravel API

Copy the example environment file:

```bash
cp .env.example .env
```

### Use mock mode

Keep this value in `.env`:

```env
EXPO_PUBLIC_USE_MOCK_API=true
```

### Use your Laravel API

Change `.env` to:

```env
EXPO_PUBLIC_USE_MOCK_API=false
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.10:8000/api
```

Use your computer's LAN IP for a physical phone. `127.0.0.1` or `localhost` will only work from the same machine/emulator.

## 4) Start the app

```bash
npx expo start
```

You can also run:

```bash
npm run android
npm run ios
npm run web
```

## 5) Open it

- Android/iPhone with Expo Go: scan the QR code shown by `npx expo start`
- Android emulator: press `a`
- iOS simulator on macOS: press `i`
- Web browser: press `w`

## Laravel API routes expected by the app

```php
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
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
});
```

## Suggested Laravel JSON shapes

### `POST /login`

```json
{
  "token": "plain-text-token",
  "user": {
    "id": 1,
    "name": "Bryan Barz",
    "email": "bryan@example.com"
  }
}
```

### `GET /reports`

```json
[
  {
    "id": 1,
    "province": "Cebu",
    "city": "Cebu City",
    "barangay": "Lahug",
    "purok": "Purok 3",
    "description": "Strong winds damaged several roofs.",
    "disasterType": "Typhoon",
    "severity": "severe",
    "affectedFamilies": 12,
    "affectedStructures": 8,
    "photos": ["https://example.com/photo1.jpg"],
    "longitude": "123.8854",
    "latitude": "10.3157",
    "reportDate": "2026-03-22",
    "createdAt": "2026-03-22T08:00:00.000000Z"
  }
]
```

## Notes

- In mock mode, login accepts any email and password.
- In real API mode, only active `field_officer` accounts can use the mobile reporting screens.
- Image upload currently stores local image URIs in the app. For Laravel production use, upload files as `multipart/form-data` and store returned URLs.
- GPS uses the device's current foreground location permission.
