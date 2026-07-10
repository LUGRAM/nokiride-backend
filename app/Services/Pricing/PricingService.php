<?php

namespace App\Services\Pricing;

class PricingService
{
    public function trip(float $distanceKm, string $serviceType = 'eco'): array
    {
        $multiplier = $serviceType === 'premium' ? 1.5 : 1;
        $raw = max(800, (500 + $distanceKm * 250) * $multiplier);

        return [
            'distance_km' => round($distanceKm, 2),
            'price_fcfa' => (int) round($raw / 50) * 50,
            'estimated_minutes' => max(5, (int) round($distanceKm / 25 * 60)),
        ];
    }

    public function delivery(float $distanceKm, string $parcelSize = 'medium'): array
    {
        $surcharge = ['small' => 0, 'medium' => 300, 'large' => 700][$parcelSize];
        $raw = max(1000, 600 + $distanceKm * 200 + $surcharge);

        return [
            'distance_km' => round($distanceKm, 2),
            'price_fcfa' => (int) round($raw / 50) * 50,
            'estimated_minutes' => max(8, (int) round($distanceKm / 22 * 60)),
        ];
    }
}
