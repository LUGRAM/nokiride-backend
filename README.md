# Backend NokiRide

Backend Laravel + Filament pour l'application Flutter NokiRide.

## Démarrage local

1. Créer une base MySQL/MariaDB nommée `nokiride_backend`.
2. Installer les dépendances si nécessaire: `composer install`.
3. Migrer et charger les données: `php artisan migrate --seed`.
4. Lancer le serveur: `php artisan serve`.
5. Ouvrir l'admin Filament: `http://127.0.0.1:8000/admin`.

Compte admin de démonstration:

- Email: `admin@nokiride.local`
- Mot de passe: `password`

## API mobile

Préfixe: `/api/v1`

- `POST /auth/register`
- `POST /auth/login`
- `GET /places?q=akanda`
- `POST /trips/estimate`
- `POST /trips`
- `POST /deliveries/estimate`
- `POST /deliveries`
- `GET /market/merchants`
- `GET /market/merchants/{merchant}/products`
- `GET /wallet/{user}`
- `POST /wallet/{user}/recharge`
