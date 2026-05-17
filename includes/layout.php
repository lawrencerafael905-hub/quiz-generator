<?php
// includes/layout.php — Shared page layout

require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/sanitize.php';

function layoutBase(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($script, '/pages/admin/') ? '../..' : '..';
}

function assetUrl(string $path): string {
    return layoutBase() . '/' . ltrim($path, '/');
}

function pageUrl(string $page): string {
    $inAdmin = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pages/admin/');
    if ($inAdmin) {
        // Strip the 'admin/' prefix so links resolve within the same directory
        return str_starts_with($page, 'admin/')
            ? substr($page, strlen('admin/'))
            : '../' . $page;
    }
    return $page;
}

function renderBackground(): void {
    ?>
<div class="bg-layer">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
<div class="grid"></div>
    <?php
}

function renderHeader(string $title, string $activeNav = '', bool $withNav = true, string $extraCss = ''): void {
    $fullTitle = $title . ' — Quiz Generator';
    $cssUrl    = assetUrl('assets/css/app.css');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($cssUrl) ?>">
<?php if ($extraCss): ?><style><?= $extraCss ?></style><?php endif; ?>
</head>
<body>
<?php
    renderBackground();
    if ($withNav) {
        require __DIR__ . '/nav.php';
        renderNav($activeNav);
    }
}

function renderFooter(string $scripts = ''): void {
    if ($scripts) {
        echo $scripts;
    }
    echo '</body></html>';
}

function renderAuthHeader(string $title): void {
    $fullTitle = $title . ' — Quiz Generator';
    $cssUrl    = assetUrl('assets/css/app.css');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($cssUrl) ?>">
</head>
<body class="auth-page">
<?php
    renderBackground();
}
