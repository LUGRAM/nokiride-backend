<?php

namespace App\Services;

use App\Models\User;
use App\Models\Trip;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Contract\Messaging;

class FcmNotificationService
{
    public function __construct(protected Messaging $messaging) {}

    /**
     * Envoie une notification Push haute priorité pour une nouvelle course.
     */
    public function sendNewTripPush(User $driver, Trip $trip, int $timeoutSeconds)
    {
        $fcmToken = $driver->fcm_token; // Assurez-vous d'avoir cette colonne dans la table users

        if (!$fcmToken) {
            return;
        }

        $data = [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'type' => 'NEW_TRIP',
            'trip_id' => (string) $trip->id,
            'pickup' => (string) $trip->pickup_address,
            'destination' => (string) $trip->dropoff_address,
            'fare' => (string) $trip->price_fcfa,
            'timeout_seconds' => (string) $timeoutSeconds,
        ];

        $message = CloudMessage::withTarget('token', $fcmToken)
            ->withNotification(Notification::create(
                'Nouvelle course disponible !',
                "Gagnez {$trip->price_fcfa} FCFA. Départ : {$trip->pickup_address}"
            ))
            ->withData($data)
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'notification_ride',
                    'channel_id' => 'high_importance_channel',
                    'icon' => 'ic_notification',
                    'color' => '#2E7D32',
                ],
            ]))
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => 'Nouvelle course disponible !',
                            'body' => "Gagnez {$trip->price_fcfa} FCFA. Départ : {$trip->pickup_address}",
                        ],
                        'sound' => 'notification_ride.mp3',
                        'badge' => 1,
                    ],
                ],
            ]));

        try {
            $this->messaging->send($message);
        } catch (\Exception $e) {
            \Log::error("FCM Error for Driver {$driver->id}: " . $e->getMessage());
        }
    }
}
