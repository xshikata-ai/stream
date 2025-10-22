<?php
// File: admin/video_list.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video_from_list') {
    $videoId = $_POST['video_id'] ?? null;
    if ($videoId && deleteVideo($videoId)) {
        $message = "Video berhasil dihapus.";
    } else {
        $error = "Gagal menghapus video.";
    }
}

$videosPerPage = 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $videosPerPage;

$videos = getVideosFromDB($videosPerPage, $offset, '', null, null, 'id', 'DESC');
$totalVideos = getTotalVideoCountDB();
$totalPages = ceil($totalVideos / $videosPerPage);

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Daftar Video</h1>
<p class="page-desc">Kelola semua video yang telah Anda kloning ke database.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Menampilkan <?php echo count($videos); ?> dari <?php echo $totalVideos; ?> Video</h3>
    </div>
    <?php if (!empty($videos)): ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thumbnail</th>
                        <th>Judul</th>
                        <th>Kategori</th>
                        <th>Durasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($video['id']); ?></td>
                        <td><img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="Thumb"></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($video['slug']); ?>" target="_blank">
                                <?php echo htmlspecialchars($video['original_title']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($video['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatDuration($video['duration']); ?></td>
                        <td class="actions">
                            <a href="<?php echo ADMIN_PATH; ?>edit_video.php?id=<?php echo $video['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" action="" style="display: inline-block;">
                                <input type="hidden" name="action" value="delete_video_from_list">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Anda yakin ingin menghapus video ini?');">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <ul class="page-list">
                <?php if ($currentPage > 1): ?>
                    <li class="page-item"><a href="?page=<?php echo $currentPage - 1; ?>" class="page-link">Sebelumnya</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item"><a href="?page=<?php echo $i; ?>" class="page-link <?php echo ($i === $currentPage) ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item"><a href="?page=<?php echo $currentPage + 1; ?>" class="page-link">Berikutnya</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p class="message info">Belum ada video yang dikloning. Mulai dari <a href="<?php echo ADMIN_PATH; ?>search_clone.php">halaman Kloning</a>.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>