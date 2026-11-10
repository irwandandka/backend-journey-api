# backend-journey-api

The API behind Backend Journey, a quiz platform for backend engineering knowledge. It serves both front-ends: the learner SPA (`backend-journey-web`) and the authoring SPA (`backend-journey-admin`).

- Laravel 13 on PHP 8.4, PostgreSQL 17, Redis for session, cache and queue
- Sanctum **cookie** authentication, not bearer tokens: the SPAs call `/sanctum/csrf-cookie` first and send `X-XSRF-TOKEN`
- Roles `learner` and `admin` via spatie/laravel-permission; `/api/v1/admin/*` requires the `manage-content` permission
- Bilingual (EN/ID) through per-entity translation tables, resolved `?locale=` → user locale → `Accept-Language` → fallback

## Running it

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

`SANCTUM_STATEFUL_DOMAINS` must list the SPA origin, or login returns *Session store not set on request*.

Seeded logins: `learner@backendjourney.test` and `admin@backendjourney.test`, password `password123`.

## Content

Questions are authored as JSON in `database/content/` and synced idempotently:

```bash
php artisan db:seed --class=ContentSeeder
```

`questions.key` is the stable authoring id. A question version is created only when its content hash changes, so an attempt's snapshot keeps showing what the learner actually answered. `tests/Feature/Content/AuthoredContentTest.php` runs against the real file and refuses content that cannot be reached: every published track needs a quiz on a journey a new learner can open.

A served question carries no correctness at all — no `is_correct`, no explanations. Grading reads `question_options` of the served version on the server. There is a test for that.

## TypeScript types for the SPAs

The Laravel Data DTOs in `app/Data` are the contract. Regenerate the declarations after changing one:

```bash
php artisan typescript:transform   # writes resources/types/api.d.ts
```

Then in either SPA:

```bash
npm run sync:types
```

The file is committed, so a clone of a SPA type-checks without this repo present.

## Tests

```bash
php vendor/bin/phpunit
```

They run against a real PostgreSQL database (`backend_journey_test`), not sqlite, because the schema uses `jsonb` and partial unique indexes.
