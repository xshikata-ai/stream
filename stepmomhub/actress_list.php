<?php
// File: actress_list.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Ambil semua data aktris dari database
$actressData = getAllActresses(null); // null untuk mendapatkan semua
$allActresses = $actressData['data'];

// Buat array untuk mengelompokkan aktris berdasarkan abjad
$groupedActresses = [];
foreach ($allActresses as $actress) {
    // Ambil huruf pertama, pastikan uppercase, dan valid
    $firstLetter = strtoupper(substr($actress['name'], 0, 1));
    if (ctype_alpha($firstLetter)) {
        $groupedActresses[$firstLetter][] = $actress;
    } else {
        // Kelompokkan yang bukan huruf ke dalam '#'
        $groupedActresses['#'][] = $actress;
    }
}
// Urutkan grup berdasarkan kuncinya (A, B, C, ...)
ksort($groupedActresses);

$pageTitle = 'Daftar Semua Aktris';

require_once __DIR__ . '/templates/header.php';
?>

<style>
    /* Style khusus untuk halaman daftar aktris */
    .az-filter-actresses {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 2.5rem;
        padding: 1rem;
        background-color: var(--bg-secondary);
        border-radius: var(--border-radius-md);
    }
    .az-filter-actresses a {
        color: var(--text-secondary);
        font-weight: 600;
        text-decoration: none;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        min-width: 40px;
        text-align: center;
        transition: all 0.3s ease;
    }
    .az-filter-actresses a:hover {
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
    }
    .actress-group {
        margin-bottom: 2.5rem;
    }
    .actress-group-header {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-accent);
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    .actresses-list {
        display: flex; 
        flex-wrap: wrap; 
        gap: 1rem;
    }
    .actress-tag-link {
        padding: 0.6rem 1.2rem; 
        background-color: var(--bg-tertiary); 
        border-radius: 8px; 
        font-weight: 500; 
        transition: background-color 0.3s, color 0.3s;
    }
    .actress-tag-link:hover {
        background-color: var(--primary-accent);
        color: #fff;
    }
</style>

<section class="generic-section">
    <div class="container">
        <div class="page-header" style="margin-bottom: 2rem; padding-top: 2.5rem;">
            <h1 class="page-title" style="font-size: 1.8rem; text-align: center;"><?php echo $pageTitle; ?></h1>
        </div>
        
        <nav class="az-filter-actresses">
            <?php foreach (array_keys($groupedActresses) as $letter): ?>
                <a href="#actress-<?php echo $letter; ?>"><?php echo $letter; ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!empty($groupedActresses)): ?>
            <?php foreach ($groupedActresses as $letter => $actresses): ?>
                <div id="actress-<?php echo $letter; ?>" class="actress-group">
                    <h2 class="actress-group-header"><?php echo $letter; ?></h2>
                    <div class="actresses-list">
                        <?php 
                        // Urutkan aktris dalam grup ini berdasarkan nama
                        usort($actresses, function($a, $b) {
                            return strcmp($a['name'], $b['name']);
                        });
                        foreach ($actresses as $actress): 
                        ?>
                            <a href="<?php echo BASE_URL; ?>actress/<?php echo urlencode($actress['slug']); ?>" class="actress-tag-link">
                                <?php echo htmlspecialchars($actress['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center;">Belum ada aktris yang tersedia.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>