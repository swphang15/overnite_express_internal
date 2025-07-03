<?php

namespace App;

use App\Models\Client;
use App\Models\ShippingRate;

trait ManifestTrait
{
    public function calculate_total_price(
        $fuel_surcharge,
        $misc_charge,
        $from,
        $to,
        $consignor_id,
        $kg
    ) {
        $client = Client::find($consignor_id);
        if (!$client) {
            return ['base_price' => 0, 'misc_charge' => $misc_charge, 'total' => 0];
        }

        $from = strtoupper($from);
        $to = strtoupper($to);

        $shippingRate = ShippingRate::where('origin', $from)
            ->where('destination', $to)
            ->where('shipping_plan_id', $client->shipping_plan_id)
            ->first();

        if (!$shippingRate) {
            return ['base_price' => 0, 'misc_charge' => $misc_charge, 'total' => 0];
        }
        $kg = ceil($kg);
        if ($kg <= $shippingRate->minimum_weight) {
            $basePrice = $shippingRate->minimum_price;
        } else {
            $basePrice = $kg * $shippingRate->additional_price_per_kg;
        }
        $total = $basePrice + ($fuel_surcharge * $kg) + $misc_charge;

        return [
            'base_price' => (float) $basePrice,
            'misc_charge' => (float) $misc_charge,
            'total' => (float) $total,
        ];
    }
}
