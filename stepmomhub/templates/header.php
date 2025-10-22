<?php
// File: templates/header.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

function is_active_link($page_name) {
    return basename($_SERVER['PHP_SELF']) == 'index.php' && $page_name == 'index.php';
}

$allCategories = getCategories();
$siteTitle = getSetting('site_title');
$siteLogoUrl = getSetting('site_logo_url');
$menuItems = getMenuItemsFromDB();

// Ambil pengaturan kolom grid dan layout dari DB
$desktopCols = getSetting('grid_columns_desktop') ?? 4;
$mobileCols = getSetting('grid_columns_mobile') ?? 2;
$cardLayout = getSetting('video_card_layout') ?? 'landscape';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle ?? 'Situs Video'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(getSetting('meta_description') ?? 'Koleksi video pilihan.'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(getSetting('meta_keywords') ?? 'video, streaming'); ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/style.css">

    <style>
        :root {
            --grid-desktop-columns: <?php echo intval($desktopCols); ?>;
            --grid-mobile-columns: <?php echo intval($mobileCols); ?>;
        }
    </style>
</head>
<body id="body" class="layout-<?php echo htmlspecialchars($cardLayout); ?>">
    <header class="site-header">
        <div class="container header-container">
            <a href="<?php echo BASE_URL; ?>" class="site-logo">
                <?php if (!empty($siteLogoUrl)): ?>
                    <img src="<?php echo htmlspecialchars($siteLogoUrl); ?>" alt="<?php echo htmlspecialchars($siteTitle); ?>" class="site-logo-img">
                <?php else: ?>
                    <i class="ph-fill ph-play-circle logo-icon"></i>
                    <span><?php echo htmlspecialchars($siteTitle); ?></span>
                <?php endif; ?>
            </a>
            <nav class="main-nav">
                <?php if (isset($menuItems['home']) && $menuItems['home']['is_visible']): ?>
                    <div class="nav-item">
                        <a href="<?php echo BASE_URL; ?>" class="nav-link <?php echo is_active_link('index.php') ? 'active' : ''; ?>">
                            <i class="ph ph-house"></i> <?php echo htmlspecialchars($menuItems['home']['display_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($menuItems['videos']) && $menuItems['videos']['is_visible']): ?>
                    <div class="nav-item has-submenu">
                        <a href="<?php echo BASE_URL; ?>videos" class="nav-link">
                            <i class="ph ph-film-strip"></i>
                            <span><?php echo htmlspecialchars($menuItems['videos']['display_name']); ?></span>
                            <i class="ph ph-caret-down dropdown-icon"></i>
                        </a>
                        <ul class="nav-submenu">
                            <li><a href="<?php echo BASE_URL; ?>videos?sort=latest"><span>New Release</span></a></li>
                            <li><a href="<?php echo BASE_URL; ?>videos?sort=views"><span>Most Viewed</span></a></li>
                            <li><a href="<?php echo BASE_URL; ?>videos?sort=likes"><span>Most Liked</span></a></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($menuItems['categories']) && $menuItems['categories']['is_visible']): ?>
                    <div class="nav-item has-submenu">
                        <a href="#" class="nav-link">
                            <i class="ph ph-tag"></i>
                            <span><?php echo htmlspecialchars($menuItems['categories']['display_name']); ?></span>
                            <i class="ph ph-caret-down dropdown-icon"></i>
                        </a>
                        <ul class="nav-submenu">
                            <?php foreach($allCategories as $category): ?>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>videos?category=<?php echo $category['slug']; ?>">
                                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($menuItems['actress']) && $menuItems['actress']['is_visible']): ?>
                    <div class="nav-item">
                        <a href="<?php echo BASE_URL; ?>actress" class="nav-link"><i class="ph ph-user-list"></i> <?php echo htmlspecialchars($menuItems['actress']['display_name']); ?></a>
                    </div>
                <?php endif; ?>

                <?php if (isset($menuItems['genres']) && $menuItems['genres']['is_visible']): ?>
                    <div class="nav-item">
                        <a href="<?php echo BASE_URL; ?>genres" class="nav-link"><i class="ph ph-film-slate"></i> <?php echo htmlspecialchars($menuItems['genres']['display_name']); ?></a>
                    </div>
                <?php endif; ?>

                <?php if (isset($menuItems['studios']) && $menuItems['studios']['is_visible']): ?>
                    <div class="nav-item">
                        <a href="#" class="nav-link"><i class="ph ph-buildings"></i> <?php echo htmlspecialchars($menuItems['studios']['display_name']); ?></a>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="mobile-header-controls">
                <button id="open-search-btn" class="mobile-search-open-btn" aria-label="Buka Pencarian">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
                <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Buka Menu">
                    <i class="ph ph-list"></i>
                </button>
            </div>
        </div>
        
        <div id="search-popup" class="search-popup">
            <button id="close-search-btn" class="search-popup-close-btn" aria-label="Tutup Pencarian">&times;</button>
            <div class="search-popup-content">
                <form action="<?php echo BASE_URL; ?>videos" method="get" class="search-popup-form">
                    <input type="search" name="search" class="search-popup-input" placeholder="Ketik untuk mencari video..." required autocomplete="off">
                    <button type="submit" class="search-popup-submit-btn" aria-label="Cari">
                        <i class="ph ph-magnifying-glass"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>
    <nav class="mobile-nav" id="mobile-nav">
        <ul class="mobile-menu-list">
            <?php if (isset($menuItems['home']) && $menuItems['home']['is_visible']): ?>
                <li><a href="<?php echo BASE_URL; ?>" class="mobile-menu-link"><i class="ph ph-house"></i><span><?php echo htmlspecialchars($menuItems['home']['display_name']); ?></span></a></li>
            <?php endif; ?>
            
            <?php if (isset($menuItems['videos']) && $menuItems['videos']['is_visible']): ?>
                <li class="has-submenu">
                    <div class="mobile-menu-link" data-toggle="submenu">
                        <i class="ph ph-film-strip"></i>
                        <span><?php echo htmlspecialchars($menuItems['videos']['display_name']); ?></span>
                        <i class="ph ph-caret-down dropdown-icon"></i>
                    </div>
                    <ul class="submenu">
                        <li><a href="<?php echo BASE_URL; ?>videos?sort=latest"><span>New Release</span></a></li>
                        <li><a href="<?php echo BASE_URL; ?>videos?sort=views"><span>Most Viewed</span></a></li>
                        <li><a href="<?php echo BASE_URL; ?>videos?sort=likes"><span>Most Liked</span></a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (isset($menuItems['categories']) && $menuItems['categories']['is_visible']): ?>
                <li class="has-submenu">
                    <div class="mobile-menu-link" data-toggle="submenu">
                        <i class="ph ph-tag"></i>
                        <span><?php echo htmlspecialchars($menuItems['categories']['display_name']); ?></span>
                        <i class="ph ph-caret-down dropdown-icon"></i>
                    </div>
                    <ul class="submenu">
                        <?php foreach($allCategories as $category): ?>
                            <li>
                                <a href="<?php echo BASE_URL; ?>videos?category=<?php echo $category['slug']; ?>">
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if (isset($menuItems['actress']) && $menuItems['actress']['is_visible']): ?>
                <li><a href="<?php echo BASE_URL; ?>actress" class="mobile-menu-link"><i class="ph ph-user-list"></i><span><?php echo htmlspecialchars($menuItems['actress']['display_name']); ?></span></a></li>
            <?php endif; ?>
            
            <?php if (isset($menuItems['genres']) && $menuItems['genres']['is_visible']): ?>
                <li><a href="<?php echo BASE_URL; ?>genres" class="mobile-menu-link"><i class="ph ph-film-slate"></i><span><?php echo htmlspecialchars($menuItems['genres']['display_name']); ?></span></a></li>
            <?php endif; ?>

            <?php if (isset($menuItems['studios']) && $menuItems['studios']['is_visible']): ?>
                <li><a href="#" class="mobile-menu-link"><i class="ph ph-buildings"></i><span><?php echo htmlspecialchars($menuItems['studios']['display_name']); ?></span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main class="container">
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Skrip untuk menu mobile (tetap sama)
            const toggleButton = document.getElementById('mobile-menu-toggle');
            const mobileNav = document.getElementById('mobile-nav');
            const overlay = document.getElementById('mobile-nav-overlay');
            const body = document.getElementById('body');
            const toggleMenu = () => {
                const isOpen = mobileNav.classList.contains('is-open');
                mobileNav.classList.toggle('is-open');
                overlay.classList.toggle('is-open');
                body.style.overflow = !isOpen ? 'hidden' : '';
            };
            if (toggleButton && mobileNav && overlay) {
                toggleButton.addEventListener('click', toggleMenu);
                overlay.addEventListener('click', toggleMenu);
            }
            const submenuToggles = document.querySelectorAll('[data-toggle="submenu"]');
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    e.preventDefault(); 
                    const parentLi = toggle.closest('.has-submenu');
                    parentLi.classList.toggle('submenu-is-open');
                });
            });

            // SKRIP BARU UNTUK POPUP PENCARIAN
            const openSearchBtn = document.getElementById('open-search-btn');
            const closeSearchBtn = document.getElementById('close-search-btn');
            const searchPopup = document.getElementById('search-popup');
            const searchInput = document.querySelector('.search-popup-input');

            if (openSearchBtn && closeSearchBtn && searchPopup && searchInput) {
                openSearchBtn.addEventListener('click', () => {
                    searchPopup.classList.add('is-open');
                    searchInput.focus(); // Langsung fokus ke input
                });

                closeSearchBtn.addEventListener('click', () => {
                    searchPopup.classList.remove('is-open');
                });

                // Tutup popup jika klik di luar area konten
                searchPopup.addEventListener('click', (e) => {
                    if (e.target === searchPopup) {
                        searchPopup.classList.remove('is-open');
                    }
                });
                
                // Tutup popup dengan tombol Escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && searchPopup.classList.contains('is-open')) {
                        searchPopup.classList.remove('is-open');
                    }
                });
            }
        });
    </script>