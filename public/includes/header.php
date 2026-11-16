<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/session.php';

$pageTitle = $page_title ?? 'ByteBack';
$extraCss = $extra_css ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · ByteBack</title>
    <meta name="description" content="ByteBack, the Bicol University lost and found.">
    <link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <?php foreach ($extraCss as $href): ?>
        <link rel="stylesheet" href="<?= e((string) $href) ?>">
    <?php endforeach; ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
