<?php
/**
 * Page shell (top). Variables a page may set before including:
 *   $pageTitle, $pageDescription, $bodyClass, $extraHead (html), $hideNavbar (bool), $mainClass
 */
$pageTitle = $pageTitle ?? 'Homes, land & spaces in Kenya';
$pageDescription = $pageDescription ?? 'Buy, rent and lease homes, land, commercial spaces and short stays across Kenya. Find trusted property services on Vikao.';
$me = current_user();
?><!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?> · Vikao</title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(base_path()) ?>">
  <meta name="theme-color" content="#1E293B">
  <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
  <link rel="apple-touch-icon" href="<?= asset('img/logo-mark.png') ?>">
  <script>
    // theme before paint: user pref (db) > localStorage > system
    (function () {
      var pref = <?= json_encode($me['theme'] ?? null) ?>;
      try { pref = (pref && pref !== 'system') ? pref : (localStorage.getItem('vk-theme') || 'system'); } catch (e) {}
      var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'], display: ['Poppins', '"Plus Jakarta Sans"', 'sans-serif'] },
          colors: ['canvas','surface','surface2','ink','inksoft','muted','line','accent','accentink','nav','navink'].reduce(function (o, k) {
            o[k] = 'rgb(var(--vk-' + k + ') / <alpha-value>)'; return o; }, {}),
          boxShadow: { soft: '0 1px 2px rgb(15 23 42 / .04), 0 8px 24px -12px rgb(15 23 42 / .18)', lift: '0 20px 40px -20px rgb(15 23 42 / .35)' },
          borderRadius: { '4xl': '2rem' }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <?= $extraHead ?? '' ?>
</head>
<body class="min-h-full bg-canvas text-ink font-sans antialiased <?= e($bodyClass ?? '') ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] bg-accent text-accentink px-3 py-2 rounded-lg">Skip to content</a>
<?php component('topbar', ['me' => $me]); ?>
<?php if (empty($hideNavbar)) component('navbar'); ?>
<?php foreach (take_flashes() as $f): ?>
  <div class="vk-flash" data-type="<?= e($f['type']) ?>" data-msg="<?= e($f['msg']) ?>" hidden></div>
<?php endforeach; ?>
<main id="main" class="<?= e($mainClass ?? 'pb-24 md:pb-12') ?>">
