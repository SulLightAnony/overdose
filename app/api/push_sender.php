<?php
require_once __DIR__ . '/../config/config.php';

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

function sendWebPushToUser(PDO $pdo, int $userId, array $payload): int
{
    if (!class_exists(WebPush::class) || VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT subscriptionId, endpoint, p256dhKey, authToken FROM user_push_subscriptions WHERE userId = :userId');
    $stmt->execute(['userId' => $userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$subscriptions) {
        return 0;
    }

    $webPush = new WebPush([
        'VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY
        ]
    ], ['TTL' => 300, 'urgency' => 'normal']);

    $sentCount = 0;
    foreach ($subscriptions as $row) {
        try {
            $subscription = Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys' => ['p256dh' => $row['p256dhKey'], 'auth' => $row['authToken']],
                'contentEncoding' => 'aes128gcm'
            ]);
            $report = $webPush->sendOneNotification(
                $subscription,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            if ($report->isSuccess()) {
                $sentCount++;
            } elseif ($report->isSubscriptionExpired()) {
                $delete = $pdo->prepare('DELETE FROM user_push_subscriptions WHERE subscriptionId = :id');
                $delete->execute(['id' => $row['subscriptionId']]);
            }
        } catch (Throwable $exception) {
            error_log('Web Push delivery failed: ' . $exception->getMessage());
        }
    }

    return $sentCount;
}
