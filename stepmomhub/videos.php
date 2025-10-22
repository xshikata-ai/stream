<?php
// File: videos.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Ambil parameter dari URL
$searchKeyword = $_GET['search'] ?? null;
$categorySlug = $_GET['category'] ?? null;
$tag = isset($_GET['tag']) ? urldecode($_GET['tag']) : null;
$sort = $_GET['sort'] ?? 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$desktopCols = getSetting('grid_columns_desktop') ?? 4;
if ($desktopCols == 6) {
    $videosPerPage = 18; 
} elseif ($desktopCols == 5) {
    $videosPerPage = 15;
} else {
    $videosPerPage = 12;
}

$offset = ($page - 1) * $videosPerPage;
$category = null;
$categoryId = null;
$pageTitle = 'Semua Video';

if ($categorySlug) {
    $allCategories = getCategories();
    foreach ($allCategories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $category = $cat;
            $categoryId = $cat['id'];
            break;
        }
    }
}

$orderBy = 'id';
$sortLabel = 'Terbaru';

// Jika ada pencarian, jangan gunakan sorting lain
if (empty($searchKeyword)) {
    switch ($sort) {
        case 'views':
            $orderBy = 'views';
            $sortLabel = 'Paling Banyak Dilihat';
            break;
        case 'likes':
            $orderBy = 'likes';
            $sortLabel = 'Paling Banyak Disukai';
            break;
        case 'latest':
        default:
            $orderBy = 'id';
            $sortLabel = 'Terbaru';
            break;
    }
}

// Logika judul halaman diperbarui
if ($searchKeyword) {
    $pageTitle = 'Hasil Pencarian: "' . htmlspecialchars($searchKeyword) . '"';
} elseif ($category) {
    $pageTitle = "Kategori: " . htmlspecialchars($category['name']);
} elseif ($tag) {
    $pageTitle = "Genre: " . htmlspecialchars($tag);
} else {
    $pageTitle = "Semua Video";
}
// Tambahkan label pengurutan hanya jika tidak sedang mencari
if (empty($searchKeyword)) {
    $pageTitle .= " - " . $sortLabel;
}

// Panggilan fungsi ke database diperbarui untuk menyertakan $searchKeyword
$videos = getVideosFromDB($videosPerPage, $offset, $searchKeyword, $categoryId, $tag, $orderBy, 'DESC');
$totalVideos = getTotalVideoCountDB($searchKeyword, $categoryId, $tag);
$totalPages = ceil($totalVideos / $videosPerPage);

$enable_border = getSetting('enable_category_border');

require_once __DIR__ . '/templates/header.php';
?>

<section class="video-section">
    <div class="section-header"> <h2 class="section-title">
            <i class="ph-fill ph-grid-four icon"></i>
            <span><?php echo $pageTitle; ?></span>
        </h2>
    </div> <?php if (!empty($videos)): ?>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                <?php
                $border_style = '';
                if ($enable_border === '1' && !empty($video['category_color'])) {
                    $border_style = 'style="border-color: ' . htmlspecialchars($video['category_color']) . ';"';
                }
                ?>
                <a href="<?php echo BASE_URL . htmlspecialchars($video['slug']); ?>" class="video-card" <?php echo $border_style; ?>>
                    <div class="thumbnail-container">
                        <?php if (!empty($video['category_name'])): ?>
                            <span class="badge category-badge" style="background-color: <?php echo htmlspecialchars($video['category_color'] ?? '#D91881'); ?>;"><?php echo htmlspecialchars($video['category_name']); ?></span>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="<?php echo htmlspecialchars($video['original_title']); ?>" loading="lazy">
                        <span class="badge duration-badge"><i class="ph ph-timer"></i><?php echo formatDuration($video['duration']); ?></span>
                        <?php if (!empty($video['quality'])): ?>
                            <span class="badge quality-badge"><?php echo htmlspecialchars($video['quality']); ?></span>
                        <?php endif; ?>

                        <div class="portrait-info-overlay">
                            <div class="portrait-meta">
                                <?php if (!empty($video['quality'])): ?>
                                    <span class="portrait-meta-item meta-quality"><?php echo htmlspecialchars($video['quality']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($video['duration'])): ?>
                                    <span class="portrait-meta-item meta-duration"><?php echo formatDurationToMinutes($video['duration']); ?></span>
                                <?php endif; ?>
                                <span class="portrait-title"><?php echo htmlspecialchars(getThumbnailTitle($video['original_title'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="video-info">
                        <h3><?php echo htmlspecialchars($video['original_title']); ?></h3>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 2rem; display:flex; justify-content:center; align-items: center; gap: 1rem;">
                <?php
                // Logika untuk Tombol Previous
                if ($page > 1) {
                    $prevParams = $_GET;
                    $prevParams['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($prevParams) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">&laquo; Previous</a>';
                }
                ?>

                <span class="page-indicator" style="color: var(--text-secondary); font-weight: 500;">
                    Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
                </span>

                <?php
                // Logika untuk Tombol Next
                if ($page < $totalPages) {
                    $nextParams = $_GET;
                    $nextParams['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($nextParams) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">Next &raquo;</a>';
                }
                ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <p style="text-align: center; padding: 2rem;">Tidak ada video yang ditemukan untuk kriteria ini.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>