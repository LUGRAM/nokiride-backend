# Backend NokiRide

Backend Laravel + Filament + Reverb pour l'application Flutter NokiRide.

## Démarrage local

1. Créer une base MySQL/MariaDB nommée `nokiride_backend`.
2. Installer les dépendances: `composer install`.
3. Configuration: `cp .env.example .env` et générer la clé `php artisan key:generate`.
4. Initialiser la base de données (inclut les comptes de test Enzo et Longa):
   ```bash
   php artisan migrate:fresh --seed
   ```

## Services à lancer (3 terminaux requis)

Pour que le système de dispatching et de tracking fonctionne, vous devez lancer ces 3 services simultanément :

### 1. API REST (Port 9000)
```bash
php artisan serve --host=0.0.0.0 --port=9000
```

### 2. Serveur de WebSockets (Reverb - Port 8080)
```bash
php artisan reverb:start
```

### 3. Moteur de Dispatching (Queue Worker)
```bash
php artisan queue:work
```

## Comptes de Test
- **Client (Enzo Mezui)**: `077000000` / `1234567890`
- **Chauffeur (Longa Lloyd)**: `077111111` / `1234567890`
- **Admin**: `admin@nokiride.local` / `password`

## API mobile
Préfixe: `/api/v1`

- `POST /driver/update-location`: Mise à jour GPS
- `POST /trips/{id}/accept`: Accepter une course
- `POST /trips/{id}/reject`: Refuser une course
- `GET /driver/current-offers`: Sync check au démarrage
