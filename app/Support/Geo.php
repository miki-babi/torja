<?php

namespace App\Support;

/**
 * Great-circle distance math for the location-based features —
 * member suggestions ("≈3 km away") and nearby gyms.
 */
class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Haversine distance in kilometers between two coordinates.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
