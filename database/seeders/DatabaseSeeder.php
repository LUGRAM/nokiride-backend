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
        // 1. ADMIN
        User::updateOrCreate(
            ['phone' => '+24100000000'],
            [
                'name' => 'Admin NokiRide',
                'email' => 'admin@nokiride.local',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ],
        );

        // 2. CLIENTS (Compte User uniquement)
        User::updateOrCreate(
            ['phone' => '077000000'],
            [
                'name' => 'Enzo Mezui',
                'email' => 'enzo@nokiride.local',
                'role' => 'customer',
                'password' => Hash::make('1234567890'),
                'wallet_balance' => 15000,
            ],
        );

        $demoCustomer = User::updateOrCreate(
            ['phone' => '+24177123456'],
            [
                'name' => 'Client Démo',
                'email' => 'client@nokiride.local',
                'role' => 'customer',
                'password' => Hash::make('1234'),
                'wallet_balance' => 5000,
            ],
        );

        // 3. CHAUFFEURS (Compte User + Profil Driver)
        $longaUser = User::updateOrCreate(
            ['phone' => '077111111'],
            [
                'name' => 'Longa Lloyd',
                'email' => 'longa@nokiride.local',
                'role' => 'driver',
                'password' => Hash::make('1234567890'),
                'is_online' => true,
                'is_busy' => false,
            ],
        );

        Driver::updateOrCreate(
            ['user_id' => $longaUser->id],
            [
                'name' => $longaUser->name,
                'phone' => $longaUser->phone,
                'vehicle_type' => 'Berline',
                'vehicle_plate' => 'GA-123-AB',
                'rating' => 5.0,
                'status' => 'available'
            ]
        );

        // Autres chauffeurs démo
        $demoDrivers = [
            ['name' => 'Jean Ondo', 'phone' => '+24166000111', 'vehicle_type' => 'Moto', 'vehicle_plate' => 'GA-2041-AA'],
            ['name' => 'Michel Essono', 'phone' => '+24166000222', 'vehicle_type' => 'Moto', 'vehicle_plate' => 'GA-3398-BB'],
        ];

        foreach ($demoDrivers as $data) {
            $u = User::updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'role' => 'driver',
                    'password' => Hash::make('password'),
                    'is_online' => true,
                ]
            );

            Driver::updateOrCreate(
                ['user_id' => $u->id],
                array_merge($data, [
                    'user_id' => $u->id,
                    'rating' => 4.5,
                    'status' => 'available'
                ])
            );
        }

        // 4. PLACES & MERCHANTS
        foreach ($this->places() as $place) {
            Place::updateOrCreate(['name' => $place['name']], $place);
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
                'user_id' => $demoCustomer->id,
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
