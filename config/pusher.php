<?php
// config/pusher.php — Server-side Pusher SDK initialisation
// Install: composer require pusher/pusher-php-server
require_once __DIR__ . '/env.php';

function getPusher(): Pusher\Pusher {
    static $pusher = null;
    if ($pusher === null) {
        $pusher = new Pusher\Pusher(
            getenv('PUSHER_APP_KEY'),
            getenv('PUSHER_APP_SECRET'),
            getenv('PUSHER_APP_ID'),
            [
                'cluster'   => getenv('PUSHER_APP_CLUSTER') ?: 'ap1',
                'useTLS'    => true,
            ]
        );
    }
    return $pusher;
}

/**
 * Broadcast an event to a Pusher channel.
 *
 * @param string $channel e.g. 'quiz-1'
 * @param string $event   e.g. 'submission-received'
 * @param array  $data
 */
function broadcastEvent(string $channel, string $event, array $data): void {
    try {
        getPusher()->trigger($channel, $event, $data);
    } catch (Exception $e) {
        error_log('Pusher broadcast failed: ' . $e->getMessage());
    }
}
