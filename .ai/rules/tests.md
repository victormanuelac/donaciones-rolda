---
paths:
  - 'tests/**'
---

# Tests

## Nunca corras los tests con la configuración cacheada
Con `bootstrap/cache/config.php` presente, los valores de `<env>` de `phpunit.xml` NO se aplican: el arranque lee la config cacheada con lo que quedó grabado de `.env`. Los tests terminan apuntando a la base `laravel` (desarrollo) en vez de `testing`, y `RefreshDatabase` la vacía. También produce errores 419 (CSRF) en masa.

`Tests\TestCase::refreshApplication()` tiene una guarda que aborta en ese caso. Si la ves dispararse, corre `php artisan config:clear` — no la quites.
