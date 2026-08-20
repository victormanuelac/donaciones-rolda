<?php

declare(strict_types=1);

namespace App\Services\Geo;

class HaversineDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Distancia en línea recta entre dos coordenadas, en kilómetros.
     */
    public static function kilometers(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
