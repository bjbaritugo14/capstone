# Supabase/Postgres Migration

The Laravel backend is configured to use Supabase's Postgres database.

## 1. Fill in Supabase credentials

Open `.env` and replace:

```env
DB_HOST=db.korrliecssecwcifdbaw.supabase.co
DB_PASSWORD=YOUR_SUPABASE_DATABASE_PASSWORD
```

Use the host and database password from Supabase project settings.

## 2. Clear cached Laravel config

```bash
php artisan config:clear
```

## 3. Create the schema in Supabase

For a fresh Supabase database:

```bash
php artisan migrate
php artisan db:seed
```

If you already have MySQL data, export/import the data before using the app so old reports, users, accidents, and validations are preserved.

## 4. Move existing MySQL data

Recommended options:

- Use DBeaver to copy tables from MySQL to the Supabase Postgres connection.
- Use `pgloader` if it is available on your machine.
- Export each MySQL table to CSV, then import into Supabase Table Editor in dependency order.

Suggested dependency order:

1. `roles`
2. `users`
3. `barangays`
4. `incident_locations`
5. `disaster_events`
6. `damage_reports`
7. `report_images`
8. `affected_families`
9. `report_validations`
10. `vehicular_accidents`
11. `accident_involved_persons`
12. `accident_images`
13. `accident_validations`
14. `resource_recommendations`
15. `relief_distributions`
16. `analytics_summaries`
17. `audit_logs`
18. `system_settings`
19. `personal_access_tokens`

## 5. Verify

```bash
php artisan migrate:status
php artisan test
```

Uploaded report/accident images are stored outside the database. Move those files separately if the app needs old image files from another device or server.
