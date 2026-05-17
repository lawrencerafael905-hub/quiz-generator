<?php
// includes/flash.php — Session flash messages

require_once __DIR__ . '/sanitize.php';

function setFlash(string $type, string $message): void {
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) {
        return;
    }
    $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
    printf(
        '<div class="alert %s" role="alert">%s</div>',
        $class,
        e($flash['message'])
    );
}
