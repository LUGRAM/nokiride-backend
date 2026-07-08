<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Merchant;
use App\Models\Place;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '+24100000000'],
            [
                'name' => 'Admin NokiRide',
                'email' => 'admin@nokiride.local',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ],
        );

        $customer = User::updateOrCreate(
            ['phone' => '+24177123456'],
            [
                'name' => 'Client Démo',
                'email' => 'client@nokiride.local',
                'role' => 'customer',
                'password' => Hash::make('1234'),
                'wallet_balance' => 5000,
            ],
        );

        foreach ($this->places() as $place) {
            Place::updateOrCreate(['name' => $place['name']], $place);
        }

        foreach ($this->drivers() as $driver) {
            Driver::updateOrCreate(['phone' => $driver['phone']], $driver);
        }

        foreach ($this->merchants() as $merchantData) {
            $merchant = Merchant::updateOrCreate(['name' => $merchantData['name']], $merchantData);

            foreach ($this->products()[$merchantData['name']] ?? [] as $product) {
                Product::updateOrCreate(
                    ['merchant_id' => $merchant->id, 'name' => $product['name']],
                    $product + ['merchant_id' => $merchant->id],
                );
            }
        }

        WalletTransaction::updateOrCreate(
            ['reference' => 'WLT-DEMO-001'],
            [
                'user_id' => $customer->id,
                'label' => 'Recharge Mobile Money',
                'type' => 'credit',
                'method' => 'airtel_money',
                'amount_fcfa' => 10000,
                'status' => 'completed',
            ],
        );
    }

    private function places(): array
    {
        return [
            ['name' => 'Akanda', 'address' => 'Quartier Akanda, Libreville', 'latitude' => 0.4477, 'longitude' => 9.4321],
            ['name' => 'Charbonnages', 'address' => 'Quartier Charbonnages, Libreville', 'latitude' => 0.3875, 'longitude' => 9.4523],
            ['name' => 'Batterie IV', 'address' => 'Batterie IV, Libreville', 'latitude' => 0.3812, 'longitude' => 9.4502],
            ['name' => 'Nzeng-Ayong', 'address' => 'Nzeng-Ayong, Libreville', 'latitude' => 0.3761, 'longitude' => 9.4689],
            ['name' => 'Glass', 'address' => 'Quartier Glass, Libreville', 'latitude' => 0.3906, 'longitude' => 9.4441],
            ['name' => 'Louis', 'address' => 'Quartier Louis, Libreville', 'latitude' => 0.3847, 'longitude' => 9.4378],
            ['name' => 'Owendo', 'address' => 'Owendo, Libreville', 'latitude' => 0.3021, 'longitude' => 9.5012],
            ['name' => 'Marché Mont-Bouët', 'address' => 'Marché Mont-Bouët, Libreville', 'latitude' => 0.3945, 'longitude' => 9.4534],
        ];
    }

    private function drivers(): array
    {
        return [
            ['name' => 'Jean Ondo', 'phone' => '+24166000111', 'vehicle_type' => 'Moto', 'vehicle_plate' => 'GA-2041-AA', 'rating' => 4.8, 'status' => 'available'],
            ['name' => 'Michel Essono', 'phone' => '+24166000222', 'vehicle_type' => 'Moto', 'vehicle_plate' => 'GA-3398-BB', 'rating' => 4.6, 'status' => 'available'],
        ];
    }

    private function merchants(): array
    {
        return [
            ['name' => 'Marché Mont-Bouët', 'category' => 'Alimentation', 'location' => 'Centre-Ville', 'price_range' => 'F-FF', 'rating' => 4.5, 'review_count' => 128, 'delivery_minutes' => 35, 'delivery_fee' => 500, 'emoji' => '🥦'],
            ['name' => 'Boulangerie Moderne', 'category' => 'Boulangerie', 'location' => 'Akanda', 'price_range' => 'F', 'rating' => 4.8, 'review_count' => 89, 'delivery_minutes' => 20, 'delivery_fee' => 300, 'emoji' => '🥖'],
            ['name' => 'Pharmacie Akanda', 'category' => 'Pharmacie', 'location' => 'Akanda', 'price_range' => 'FF', 'rating' => 4.6, 'review_count' => 64, 'delivery_minutes' => 25, 'delivery_fee' => 400, 'emoji' => '💊'],
            ['name' => 'Express Food', 'category' => 'Restaurant', 'location' => 'Glass', 'price_range' => 'FF', 'rating' => 4.3, 'review_count' => 210, 'delivery_minutes' => 30, 'delivery_fee' => 600, 'emoji' => '🍽️'],
        ];
    }

    private function products(): array
    {
        return [
            'Marché Mont-Bouët' => [
                ['name' => 'Tomates fraîches', 'description' => '1kg de tomates locales', 'price' => 500, 'emoji' => '🍅'],
                ['name' => 'Oignons', 'description' => 'Filet de 2kg', 'price' => 800, 'emoji' => '🧅'],
                ['name' => 'Poivrons', 'description' => 'Lot de 6 poivrons', 'price' => 600, 'emoji' => '🫑'],
            ],
            'Boulangerie Moderne' => [
                ['name' => 'Pain baguette', 'description' => 'Baguette fraîche du jour', 'price' => 300, 'emoji' => '🥖'],
                ['name' => 'Croissant', 'description' => 'Lot de 4 croissants', 'price' => 800, 'emoji' => '🥐'],
            ],
            'Pharmacie Akanda' => [
                ['name' => 'Paracétamol 1000mg', 'description' => 'Boîte de 16 comprimés', 'price' => 1200, 'emoji' => '💊'],
            ],
            'Express Food' => [
                ['name' => 'Poulet braisé', 'description' => 'Demi-poulet + frites', 'price' => 3500, 'emoji' => '🍗'],
                ['name' => 'Jus de gingembre', 'description' => 'Bouteille 50cl artisanale', 'price' => 800, 'emoji' => '🧃'],
            ],
        ];
    }
}
