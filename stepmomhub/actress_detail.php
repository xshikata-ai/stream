<?php
// File: actress_detail.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    header("Location: " . BASE_URL);
    exit();
}

$actress = getActressBySlug($slug);
if (!$actress) {
    http_response_code(404);
    die("Aktris tidak ditemukan.");
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$videosPerPage = 12;
$offset = ($page - 1) * $videosPerPage;

$videoData = getVideosByActressName($actress['name'], $videosPerPage, $offset);
$videos = $videoData['videos'];
$totalVideos = $videoData['total'];
$totalPages = ceil($totalVideos / $videosPerPage);

require_once __DIR__ . '/templates/header.php';
?>

<section class="video-section" style="padding-top: 2.5rem;">
    <div class="page-header-profile">
        <h1>Video oleh: <?php echo htmlspecialchars($actress['name']); ?></h1>
    </div>
    
    <?php if (!empty($videos)): ?>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                 <?php
                $border_style = '';
                if (!empty($video['category_color'])) { // Asumsi ada setting global untuk border
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
                    echo '<a href="' . BASE_URL . 'actress/' . $slug . '?page=' . ($page - 1) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">&laquo; Previous</a>';
                }
                ?>

                <span class="page-indicator" style="color: var(--text-secondary); font-weight: 500;">
                    Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
                </span>

                <?php
                // Logika untuk Tombol Next
                if ($page < $totalPages) {
                     echo '<a href="' . BASE_URL . 'actress/' . $slug . '?page=' . ($page + 1) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">Next &raquo;</a>';
                }
                ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <p style="text-align: center; margin-top: 2rem;">Belum ada video untuk aktris ini.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>