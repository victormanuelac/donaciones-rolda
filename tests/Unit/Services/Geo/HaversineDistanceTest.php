<?php

use App\Services\Geo\HaversineDistance;

test('la distancia entre un punto y sí mismo es cero', function () {
    expect(HaversineDistance::kilometers(4.4144, -76.1536, 4.4144, -76.1536))->toBe(0.0);
});

test('calcula correctamente la distancia entre Roldanillo y Cali (~85km en línea recta)', function () {
    // Roldanillo, Valle del Cauca
    $distance = HaversineDistance::kilometers(4.4144, -76.1536, 3.4516, -76.5320);

    expect($distance)->toBeGreaterThan(80.0)->toBeLessThan(120.0);
});
