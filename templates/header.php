<?php
require_once __DIR__ . '/../functions.php';

$user = current_user();

$page_title = $page_title ?? 'Pet Digital ID';
$page_description = $page_description ?? '大切な家族を見守る、ペットデジタルIDアプリ';
$page_heading = $page_heading ?? null;
$hide_page_header = $hide_page_header ?? false;

$site_name = 'Pet Digital ID';
$site_tagline = '大切なペットと飼い主をつなぐ';

$nav_items = [
    ['label' => 'ホーム', 'href' => BASE_URL . '/', 'icon' => '🏠'],
    ['label' => 'ペット登録', 'href' => BASE_URL . '/shop/register_pet.php', 'icon' => '🐾'],
    ['label' => '個体表示', 'href' => BASE_URL . '/pet/view.php', 'icon' => '🔍'],
    ['label' => '迷子報告', 'href' => BASE_URL . '/lost.php', 'icon' => '🆘'],
    ['label' => '写真で報告', 'href' => BASE_URL . '/lost_unknown.php', 'icon' => '📷'],
    ['label' => '迷子掲示板', 'href' => BASE_URL . '/lost_board.php', 'icon' => '📋'],
    ['label' => '相談掲示板', 'href' => BASE_URL . '/consultation.php', 'icon' => '💬'],
    ['label' => '動物ニュース', 'href' => BASE_URL . '/news.php', 'icon' => '📰'],
];

if (has_role('owner')) {
    $nav_items[] = [
        'label' => 'マイペット',
        'href' => BASE_URL . '/owner/dashboard.php',
        'icon' => '🐶'
    ];
}

if (has_role('shop')) {
    $nav_items[] = [
        'label' => 'ショップ管理',
        'href' => BASE_URL . '/shop/dashboard.php',
        'icon' => '🏪'
    ];
}

if (has_role(['clinic', 'admin'])) {
    $nav_items[] = [
        'label' => '病院記録',
        'href' => BASE_URL . '/clinic/record.php',
        'icon' => '🏥'
    ];
}

if (has_role('admin')) {
    $nav_items[] = [
        'label' => '通知',
        'href' => BASE_URL . '/notifications.php',
        'icon' => '🔔'
    ];

    $nav_items[] = [
        'label' => '管理画面',
        'href' => BASE_URL . '/admin/dashboard.php',
        'icon' => '⚙️'
    ];
}

$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$style_file = __DIR__ . '/../assets/style.css';
$style_version = file_exists($style_file) ? filemtime($style_file) : time();
?>

<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?php echo e($page_title); ?> | <?php echo e($site_name); ?>
    </title>

    <meta
        name="description"
        content="<?php echo e($page_description); ?>"
    >

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo $style_version; ?>"
    >
</head>

<body>

<div class="app-layout">

    <header class="top-banner">

        <div class="banner-left">

            <button
                class="sidebar-toggle"
                type="button"
                aria-label="メニューを開く"
                aria-expanded="false"
                aria-controls="sidebar"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a
                class="banner-brand"
                href="<?php echo BASE_URL; ?>/"
            >

                <img
                    class="banner-logo"
                    src="<?php echo BASE_URL; ?>/assets/paw.svg"
                    alt="Pet Digital ID"
                >

                <span class="banner-brand-text">

                    <strong>
                        <?php echo e($site_name); ?>
                    </strong>

                    <small>
                        <?php echo e($site_tagline); ?>
                    </small>

                </span>

            </a>

        </div>

        <div class="banner-right">

            <?php if (!$user): ?>

                <a
                    class="auth-btn auth-btn-login"
                    href="<?php echo BASE_URL; ?>/login.php"
                >
                    🔑 ログイン
                </a>

            <?php else: ?>

                <span class="auth-user">
                    👤 <?php echo e($user['username']); ?>
                </span>

                <a
                    class="auth-btn auth-btn-logout"
                    href="<?php echo BASE_URL; ?>/logout.php"
                >
                    ログアウト
                </a>

            <?php endif; ?>

        </div>

    </header>

    <div class="app-body">

        <aside
            class="sidebar"
            id="sidebar"
        >

            <nav class="sidebar-nav">

                <p class="sidebar-label">
                    メニュー
                </p>

                <?php foreach ($nav_items as $item): ?>

                    <?php

                    $item_path = parse_url(
                        $item['href'],
                        PHP_URL_PATH
                    ) ?: '';

                    $is_home =
                        $item['href'] === BASE_URL . '/';

                    $is_active =
                        (
                            $is_home &&
                            (
                                $current_path === BASE_URL . '/' ||
                                $current_path === BASE_URL . '/index.php'
                            )
                        )
                        ||
                        (
                            !$is_home &&
                            strpos($current_path, $item_path) === 0
                        );

                    ?>

                    <a
                        class="sidebar-link<?php echo $is_active ? ' is-active' : ''; ?>"
                        href="<?php echo e($item['href']); ?>"
                    >

                        <span class="sidebar-icon">
                            <?php echo $item['icon']; ?>
                        </span>

                        <?php echo e($item['label']); ?>

                    </a>

                <?php endforeach; ?>

            </nav>

        </aside>

        <div
            class="sidebar-overlay"
            id="sidebar-overlay"
        ></div>

        <div class="content-area">

            <main class="main-content">

                <?php if (!$hide_page_header && ($page_heading || $page_description)): ?>

                    <section class="page-header">

                        <?php if ($page_heading): ?>

                            <h1 class="page-title">
                                <?php echo e($page_heading); ?>
                            </h1>

                        <?php endif; ?>

                        <?php if ($page_description): ?>

                            <p class="page-lead">
                                <?php echo e($page_description); ?>
                            </p>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>