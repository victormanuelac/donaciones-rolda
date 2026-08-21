<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Con la configuración cacheada, los valores de `<env>` de phpunit.xml no se
     * aplican: el arranque lee `bootstrap/cache/config.php` con lo que quedó
     * grabado de `.env`. En la práctica eso hace que los tests apunten a la base
     * de desarrollo y que `RefreshDatabase` la borre.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if ($this->app->configurationIsCached()) {
            throw new RuntimeException(
                'La configuración está cacheada y los tests correrían contra la base de datos de desarrollo. Ejecuta `php artisan config:clear` antes de correr los tests.'
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
