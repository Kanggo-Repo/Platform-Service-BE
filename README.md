# Platform Service BE

`platform-service-be` adalah backend pusat untuk autentikasi, proyeksi identitas, role dan permission lokal, manajemen user, status registrasi, dan agregasi dashboard lintas service. Repo ini adalah policy hub untuk tiga service split aplikasi: `platform`, `supply`, dan `calculation`.

## Tanggung Jawab Utama

- memvalidasi identitas dari token Keycloak yang diteruskan FE
- memproyeksikan identitas Keycloak menjadi user lokal aplikasi
- menyimpan role lokal, permission lokal, dan service access
- menyediakan API untuk:
  - identity dan navigation
  - profile user
  - manajemen role
  - manajemen user
  - toggle self registration
  - agregasi dashboard lintas service
- menjadi sumber kebenaran authorization lintas service

## Posisi Dalam Arsitektur

```text
platform-service-fe
  -> platform-service-be
    -> supply-service-be
    -> calculation-service-be
    -> Keycloak Admin API

supply-service-fe
  -> platform-service-be (/api/v1/me, /api/v1/navigation)

calculation-service-fe
  -> platform-service-be (/api/v1/me, /api/v1/navigation)
```

## Boundary Service

Repo ini memiliki boundary berikut:

- **identity projection**
  - subject Keycloak, email, display name, dan realm roles diproyeksikan ke user lokal
- **authorization hub**
  - role bisnis disimpan lokal
  - permission snapshot lintas FE dibentuk dari role lokal
- **bootstrap admin**
  - realm role Keycloak `super_admin` dipetakan ke local role `super_admin`
- **service access**
  - status `allowed`, `pending`, dan `blocked` per service dikelola di sini
- **dashboard aggregation**
  - statistik platform dirakit dari owner service lain

## Auth dan Authorization

### Identity Source

- login dilakukan di FE via Keycloak
- FE lalu meneruskan access token ke backend platform
- backend membaca identity dari token dan membentuk `PlatformIdentity`

### Local Projection

Identity yang valid akan disinkronkan ke local user melalui `App\Services\Identity\UserProjectionService`.

Hal yang disinkronkan:

- `keycloak_subject`
- `email`
- `name`
- `display_name`
- `last_login_at`
- local role `super_admin` bila user memiliki realm role `super_admin`
- service access berdasarkan role dan permission lokal

### Authorization Model

- Keycloak dipakai untuk **identity** dan bootstrap admin
- semua role bisnis harian disimpan di database lokal platform
- FE lain (`supply` dan `calculation`) membaca role dan permission efektif dari API platform

## API Surface

Base path API utama adalah `/api/v1`.

### Public Operational Endpoints

- `GET /api/v1/health`
- `GET /api/v1/health/json`
- `GET /api/v1/debug/sentry` hanya di environment `local` dan `testing`

### Identity dan Navigation

- `GET /api/v1/me`
- `GET /api/v1/navigation`

Response `me` memuat:

- identity subject dan realm roles
- profile lokal
- allowed, blocked, dan pending services
- effective roles
- effective permissions
- preferred navigation route

### Profile API

- `GET /api/v1/profile`
- `PUT /api/v1/profile`

Field profile sudah disejajarkan dengan Keycloak:

- `first_name`
- `last_name`
- `full_name`
- `email`
- `identity.username`
- `identity.subject`
- `identity.realm_roles`
- `identity.email_verified`

### Dashboard API

- `GET /api/v1/dashboard`

Dashboard menarik data owner service dari:

- `supply-service-be`
- `calculation-service-be`

### Registration Settings API

- `GET /api/v1/settings/registration`
- `PUT /api/v1/settings/registration`

Toggle ini juga menyinkronkan `registrationAllowed` di realm Keycloak.

### Roles API

- `GET /api/v1/permissions`
- `GET /api/v1/roles`
- `POST /api/v1/roles`
- `PUT /api/v1/roles/{role}`
- `DELETE /api/v1/roles/{role}`

### Users API

- `GET /api/v1/users`
- `POST /api/v1/users`
- `PUT /api/v1/users/{user}`
- `DELETE /api/v1/users/{user}`

User management sudah mengikuti model field Keycloak:

- `first_name`
- `last_name`
- `email`
- `password`
- `roles`

## Integrasi Keluar

Repo ini melakukan HTTP call keluar ke:

- `supply-service-be`
  - dashboard summary dan store sidebar signals
- `calculation-service-be`
  - dashboard summary dan project draft signals
- Keycloak Admin API
  - provisioning user
  - update profile
  - update password
  - sync realm registration policy

Konfigurasi utama ada di [config/services.php](./config/services.php).

## Konfigurasi Environment Penting

Salin `.env.example` menjadi `.env`, lalu isi grup variabel berikut.

### App dan Database

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `DB_*`
- `REDIS_*`

### Internal Service Integration

- `INTERNAL_CALLER_NAME`
- `INTERNAL_SERVICE_TOKEN`
- `SUPPLY_SERVICE_BASE_URL`
- `SUPPLY_SERVICE_VERIFY_SSL`
- `SUPPLY_SERVICE_CA_BUNDLE`
- `CALCULATION_SERVICE_BASE_URL`
- `CALCULATION_SERVICE_VERIFY_SSL`
- `CALCULATION_SERVICE_CA_BUNDLE`

### Keycloak

- `KEYCLOAK_BASE_URL`
- `KEYCLOAK_REALM`
- `KEYCLOAK_CLIENT_ID`
- admin credential / secret yang dipakai provisioning sesuai `.env.example`

### Observability

- `SENTRY_ENABLED`
- `SENTRY_LARAVEL_DSN`
- `SENTRY_RELEASE`
- `SENTRY_ENVIRONMENT`
- `SENTRY_SERVER_NAME`
- `TELESCOPE_ENABLED`
- `TELESCOPE_PATH`

## Local Development Setup

### Prasyarat

- PHP 8.3+
- Composer
- Node.js dan npm
- MySQL atau MariaDB
- Redis
- akses ke Keycloak realm `kanggo`
- owner service lain jika ingin menguji agregasi dashboard penuh

### Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Menjalankan Aplikasi

```bash
composer run dev
```

Atau manual:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Development Commands

```bash
php artisan test
composer analyse
composer quality
vendor/bin/pint
npm run build
```

Keterangan:

- `composer analyse` menjalankan PHPStan + Larastan
- `composer quality` menjalankan `pint` dan `analyse`

## Testing Strategy

Repo ini memakai Pest.

Fokus test utama:

- identity API
- profile API
- registration settings API
- role management API
- user management API
- dashboard aggregation API
- health, Sentry, dan Telescope baseline

## Observability dan Operations

Repo ini sudah memiliki baseline hardening berikut:

- **PHPStan + Larastan** untuk static analysis
- **Spatie Laravel Health**
  - `GET /api/v1/health`
  - `GET /api/v1/health/json`
- **Sentry**
  - route debug lokal untuk verifikasi ingest
- **Laravel Telescope**
  - debugging request, query, exception, cache, dan job
- **request correlation**
  - `X-Request-Id` dipropagasikan ke downstream service

## Docker dan Deploy

Repo ini memiliki artefak deploy berikut:

- `compose.yml` untuk local/dev
- `compose.staging.yml`
- `compose.production.yml`
- `Dockerfile`
- `Dockerfile.production`
- `docker/entrypoint.sh`

Pola production mengikuti baseline monolith-style yang sudah dipindahkan ke repo service:

- image multi-stage
- `php-fpm`
- container `queue`
- container `scheduler`
- blue/green app service di compose production
- network external `frontend` dan `backend`

## CI

Workflow CI tersedia di `.github/workflows/ci.yml`.

Shape-nya:

- `quality`
  - `vendor/bin/pint --test`
  - `composer analyse`
- `test`
  - Laravel test suite
- validasi compose bila file compose tersedia

Runner sudah disejajarkan ke base monolith organization.

## Struktur Folder Penting

- `app/Http/Controllers/Api/V1` API utama backend platform
- `app/Services/Identity` sinkronisasi Keycloak dan user lokal
- `app/Services/Policy` katalog permission dan assignment permission role
- `app/Services/Registration` sync kebijakan registrasi
- `app/Services/Dashboard` agregasi data lintas service
- `config/health.php` baseline health checks
- `config/sentry.php` baseline error tracking
- `config/telescope.php` baseline debugging

## Troubleshooting

### User login tapi role tidak sesuai

Cek:

- realm role `super_admin` di Keycloak bila ini akun bootstrap admin
- mapping role lokal user di database platform
- response `GET /api/v1/me`

### Profile tidak sinkron dengan Keycloak

Cek:

- konektivitas platform ke Keycloak Admin API
- subject user lokal (`keycloak_subject`)
- field `first_name` dan `last_name` yang dikirim FE

### Dashboard kosong atau tidak lengkap

Cek:

- `SUPPLY_SERVICE_BASE_URL`
- `CALCULATION_SERVICE_BASE_URL`
- token internal caller
- health endpoint owner service

### Toggle registration berubah di UI tapi tidak di Keycloak

Cek:

- kredensial admin Keycloak
- Sentry/log error dari `RegistrationPolicyService`

## Related Repositories

- `platform-service-fe` untuk shell admin dan dashboard owner
- `supply-service-be` untuk material, unit, store, dan radius setting
- `supply-service-fe` untuk workspace supply
- `calculation-service-be` untuk kalkulasi, taxonomy, draft, dan finalization
- `calculation-service-fe` untuk workspace kalkulasi