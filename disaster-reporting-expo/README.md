# Disaster Reporting Expo App

The Field Officer app connects directly to Supabase Auth, PostgreSQL RPC, and
private Storage over HTTPS. Laravel, a PC on the same Wi-Fi, and a local API
server are no longer required. Internet access is required.

## Run with Expo Go

```powershell
npm install
npm start
```

Scan the QR code using Expo Go. The Expo development server is needed only for
development; an installed release APK contains its own bundle.

## Configuration

Copy `.env.example` to `.env` and set the project's public Supabase URL and anon
key. Never put a database password, service-role key, or media bridge password
in an `EXPO_PUBLIC_` variable. Restart Expo after changing the environment.

## Build an installable Android APK

Requires the Android SDK and JDK 17 or newer.

```powershell
npm run build:android
# With all Gradle dependencies already cached:
npm run build:android -- -Offline
```

Install `android/app/build/outputs/apk/release/app-release.apk` on the phone.
It opens without Expo Go, Metro, or Laravel. The APK uses the development signing
key for local installation. Changing the Supabase project requires rebuilding.

## Database setup and account migration

The scripts below run from `C:/project`. They use the existing Laravel `.env`
database connection, which stays outside the mobile bundle.

```powershell
node scripts/supabase-db.cjs supabase/migrations/202611120001_mobile_api.sql
node scripts/supabase-db.cjs supabase/migrations/202611120002_media_bridge.sql
node scripts/migrate-mobile-media.cjs
node scripts/test-mobile-supabase.cjs
```

The first migration provisions existing Field Officer accounts in Supabase Auth
with their current bcrypt password hashes. Newly created Field Officer accounts
are provisioned automatically. Password changes synchronize between the mobile
app and Laravel. Every RPC checks the account's current active status and role.
Only the officer's own reports and accidents are accessible. Raw Laravel tables
(including users, passwords, and Sanctum tokens) are not exposed through the
public Data API.

Report/location/family/person/photo-reference changes save atomically in
PostgreSQL. New photos go to the private `mobile-evidence` bucket; the app uses
temporary signed URLs for viewing. Photos are limited to 10 MB each.

The media migration copies available existing files from Laravel public storage
without deleting the originals. It also provisions a server-only media identity
with access restricted to this bucket, so authorized Laravel website pages can
still display mobile photos. Its credentials are written only to Laravel's
ignored `.env`; the APK contains only the public anon key. Clear Laravel's config
cache after setting up that identity. Database and Storage remain hosted even
when Laravel is stopped.

Fourteen pre-existing image files were missing from this PC during migration.
Restore those files to Laravel's `storage/app/public` folder and rerun
`node scripts/migrate-mobile-media.cjs` to copy them to Supabase. The missing
references have been preserved.

The integration test creates disposable accounts and records in the configured
Supabase project and cleans them up. It checks real Auth, ownership, validation,
private uploads, password synchronization, and disabled-account access.

Legacy `start:full` scripts now start Expo only. The old Laravel setup document
is historical and is not required for the direct Supabase app.
