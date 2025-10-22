<?php
// File: genres.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Ambil semua tag/genre unik dari database
$allGenres = getUniqueTags();

// Buat array untuk mengelompokkan genre berdasarkan abjad
$groupedGenres = [];
foreach ($allGenres as $genre) {
    // Ambil huruf pertama, pastikan uppercase, dan valid
    $firstLetter = strtoupper(substr($genre, 0, 1));
    if (ctype_alpha($firstLetter)) {
        $groupedGenres[$firstLetter][] = $genre;
    } else {
        // Kelompokkan yang bukan huruf ke dalam '#'
        $groupedGenres['#'][] = $genre;
    }
}
// Urutkan grup berdasarkan kuncinya (A, B, C, ...)
ksort($groupedGenres);

$pageTitle = 'Daftar Semua Genre';

require_once __DIR__ . '/templates/header.php';
?>

<style>
    /* Tambahan style untuk halaman genre agar lebih rapi */
    .az-filter-genres {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 2.5rem;
        padding: 1rem;
        background-color: var(--bg-secondary);
        border-radius: var(--border-radius-md);
    }
    .az-filter-genres a {
        color: var(--text-secondary);
        font-weight: 600;
        text-decoration: none;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        min-width: 40px;
        text-align: center;
        transition: all 0.3s ease;
    }
    .az-filter-genres a:hover {
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
    }
    .genre-group {
        margin-bottom: 2.5rem;
    }
    .genre-group-header {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-accent);
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    .genres-list {
        display: flex; 
        flex-wrap: wrap; 
        gap: 1rem;
    }
    .genre-tag-link {
        padding: 0.6rem 1.2rem; 
        background-color: var(--bg-tertiary); 
        border-radius: 8px; 
        font-weight: 500; 
        transition: background-color 0.3s, color 0.3s;
    }
    .genre-tag-link:hover {
        background-color: var(--primary-accent);
        color: #fff;
    }
</style>

<section class="generic-section">
    <div class="container">
        <div class="page-header" style="margin-bottom: 2rem;">
            <h1 class="page-title" style="font-size: 1.8rem; text-align: center;"><?php echo $pageTitle; ?></h1>
        </div>
        
        <nav class="az-filter-genres">
            <?php foreach (array_keys($groupedGenres) as $letter): ?>
                <a href="#genre-<?php echo $letter; ?>"><?php echo $letter; ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!empty($groupedGenres)): ?>
            <?php foreach ($groupedGenres as $letter => $genres): ?>
                <div id="genre-<?php echo $letter; ?>" class="genre-group">
                    <h2 class="genre-group-header"><?php echo $letter; ?></h2>
                    <div class="genres-list">
                        <?php 
                        // Urutkan genre dalam grup ini
                        sort($genres);
                        foreach ($genres as $genre): 
                        ?>
                            <a href="<?php echo BASE_URL; ?>genres/tag/<?php echo urlencode($genre); ?>" class="genre-tag-link">
                                <?php echo htmlspecialchars($genre); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center;">Belum ada genre yang tersedia.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>