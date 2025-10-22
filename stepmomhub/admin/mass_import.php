<?php
// File: admin/mass_import.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$successCount = 0;
$errorLines = [];
$warningLines = [];
$allowed_domains_input = '';
$selected_category_id = null;

$allCategories = getCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mass_data'])) {
    
    $allowed_domains_input = $_POST['allowed_domains'] ?? '';
    $allowed_domains = [];
    if (!empty($allowed_domains_input)) {
        $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_domains_input)));
    }
    
    $mass_category_id = !empty($_POST['mass_category_id']) ? (int)$_POST['mass_category_id'] : null;
    $selected_category_id = $mass_category_id;

    $massData = trim($_POST['mass_data']);
    $lines = explode("\n", $massData);

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = array_map('trim', explode('|', $line));
        
        if (count($parts) !== 12) {
            $errorLines[] = "Baris " . ($index + 1) . ": Format salah, harus ada 12 kolom.";
            continue;
        }

        list($title, $description, $thumbnail, $embed_links_str, $trailer_link, $gallery_links_str, $genres, $category_from_csv, $release_date_str, $duration_str, $actresses_str, $studios) = $parts;

        if (empty($title)) {
            $errorLines[] = "Baris " . ($index + 1) . ": Judul tidak boleh kosong.";
            continue;
        }

        if (doesVideoTitleExist($title)) {
             $errorLines[] = "Baris " . ($index + 1) . ": Judul '" . htmlspecialchars($title) . "' sudah ada.";
             continue;
        }

        // Proses Kloning Embed Player
        $original_embed_urls = !empty($embed_links_str) ? array_map('trim', explode(',', $embed_links_str)) : [];
        $cloned_embed_urls = [];
        if (!empty($original_embed_urls)) {
            foreach($original_embed_urls as $url) {
                $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST));
                $file_code = basename($url);
                $new_url = null;

                if (!empty($allowed_domains) && in_array($host, $allowed_domains)) {
                    // Cek Earnvids
                    $clone_api_url_ev = EARNVIDS_CLONE_URL_BASE . "?key=" . urlencode(EARNVIDS_API_KEY) . "&file_code=" . urlencode($file_code);
                    $result_ev = makeExternalApiCall($clone_api_url_ev);
                    if (isset($result_ev['status']) && $result_ev['status'] === 200 && !empty($result_ev['result']['filecode'])) {
                        $new_url = EARNVIDS_EMBED_NEW_DOMAIN . EARNVIDS_EMBED_NEW_PATH . $result_ev['result']['filecode'];
                    } else {
                        // Cek StreamHG
                        $clone_api_url_hg = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode(STREAMHG_CLONE_API_KEY) . "&file_code=" . urlencode($file_code);
                        $result_hg = makeExternalApiCall($clone_api_url_hg);
                        if (isset($result_hg['status']) && $result_hg['status'] === 200 && !empty($result_hg['result']['filecode'])) {
                            $new_url = STREAMHG_EMBED_NEW_DOMAIN . STREAMHG_EMBED_NEW_PATH . $result_hg['result']['filecode'];
                        } else {
                            // Cek Doodstream
                            $clone_api_url_dd = DOODSTREAM_CLONE_URL_BASE . "?key=" . urlencode(DOODSTREAM_API_KEY) . "&file_code=" . urlencode($file_code);
                            $result_dd = makeExternalApiCall($clone_api_url_dd);
                            if (isset($result_dd['status']) && $result_dd['status'] === 200 && !empty($result_dd['result']['filecode'])) {
                                $new_url = DOODSTREAM_EMBED_NEW_DOMAIN . DOODSTREAM_EMBED_NEW_PATH . $result_dd['result']['filecode'];
                            } else {
                                $warningLines[] = "Baris " . ($index + 1) . ": Gagal kloning URL " . htmlspecialchars($url);
                            }
                        }
                    }
                }
                $cloned_embed_urls[] = $new_url ?? $url;
            }
        }

        // Mengurutkan URL Hasil Kloning Sebelum Disimpan
        $vh_urls = []; $sw_urls = []; $dd_urls = []; $other_urls = [];
        $vh_domains = [parse_url(EARNVIDS_EMBED_NEW_DOMAIN, PHP_URL_HOST)];
        $sw_domains = [parse_url(STREAMHG_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dhcplay.com', 'stbhg.click'];
        $dd_domains = [parse_url(DOODSTREAM_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dood.re'];

        foreach ($cloned_embed_urls as $url) {
            $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST));
            $found = false;
            foreach ($vh_domains as $d) { if (strpos($host, $d) !== false) { $vh_urls[] = $url; $found = true; break; } } if ($found) continue;
            foreach ($sw_domains as $d) { if (strpos($host, $d) !== false) { $sw_urls[] = $url; $found = true; break; } } if ($found) continue;
            foreach ($dd_domains as $d) { if (strpos($host, $d) !== false) { $dd_urls[] = $url; $found = true; break; } } if ($found) continue;
            $other_urls[] = $url;
        }
        $sorted_urls = array_merge($vh_urls, $sw_urls, $dd_urls, $other_urls);
        
        // Proses Kloning Trailer
        $final_trailer_url = $trailer_link;
        if (!empty($trailer_link) && !empty($allowed_domains)) {
            $trailer_host = str_replace('www.', '', parse_url($trailer_link, PHP_URL_HOST));
            if (in_array($trailer_host, $allowed_domains)) {
                $trailer_file_code = basename($trailer_link);
                $cloned_trailer_url = null;
                $clone_api_url_trailer = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode(STREAMHG_CLONE_API_KEY) . "&file_code=" . urlencode($trailer_file_code);
                $result_trailer = makeExternalApiCall($clone_api_url_trailer);
                if (isset($result_trailer['status']) && $result_trailer['status'] === 200 && !empty($result_trailer['result']['filecode'])) {
                    $cloned_trailer_url = STREAMHG_EMBED_NEW_DOMAIN . STREAMHG_EMBED_NEW_PATH . $result_trailer['result']['filecode'];
                }
                
                if($cloned_trailer_url) {
                    $final_trailer_url = $cloned_trailer_url;
                    $warningLines[] = "Baris " . ($index + 1) . ": URL Trailer berhasil di-clone.";
                }
            }
        }
        
        $main_embed_url = array_shift($sorted_urls);
        $extra_embed_urls = !empty($sorted_urls) ? implode(',', $sorted_urls) : null;

        if (empty($main_embed_url)) {
            $errorLines[] = "Baris " . ($index + 1) . ": Link embed player tidak ada setelah diproses.";
            continue;
        }

        if (doesEmbedUrlExist($main_embed_url)) {
            $errorLines[] = "Baris " . ($index + 1) . ": Link embed utama '" . htmlspecialchars($main_embed_url) . "' sudah ada.";
            continue;
        }

        $final_category_id = $mass_category_id ?: insertCategoryIfNotExist($category_from_csv);
        if (!empty($actresses_str)) { addActressesIfNotExist(array_map('trim', explode(',', $actresses_str))); }
        $duration_seconds = (int)filter_var($duration_str, FILTER_SANITIZE_NUMBER_INT) * 60;
        $release_date_obj = DateTime::createFromFormat('d-m-Y', $release_date_str);
        $release_date_mysql = ($release_date_obj) ? $release_date_obj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $randomViews = rand(1000, 10000); $randomLikes = rand(500, $randomViews);

        $videoData = [
            'original_title' => $title, 'description' => $description, 'tags' => $genres,
            'actresses' => $actresses_str, 'studios' => $studios, 'category_id' => $final_category_id,
            'embed_url' => $main_embed_url, 'extra_embed_urls' => $extra_embed_urls,
            'api_source' => 'mass_import_clone', 'image_url' => $thumbnail, 'duration' => $duration_seconds,
            'quality' => 'HD', 'views' => $randomViews, 'likes' => $randomLikes,
            'trailer_embed_url' => $final_trailer_url,
            'gallery_image_urls' => !empty($gallery_links_str) ? $gallery_links_str : null,
            'download_links' => null, 'cloned_at' => $release_date_mysql,
        ];

        if (insertMassVideo($videoData)) { $successCount++; } else { $errorLines[] = "Baris " . ($index + 1) . ": Gagal menyimpan ke DB."; }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Mass Import & Clone Video</h1>
<p class="page-desc">Impor video sekaligus dengan kloning otomatis untuk embed player dan trailer.</p>

<?php if (!empty($successCount)): ?>
    <div class="message success">Berhasil mengimpor dan memproses <?php echo $successCount; ?> video!</div>
<?php endif; ?>

<?php if (!empty($warningLines)): ?>
    <div class="message warning">
        <strong>Peringatan:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <?php foreach ($warningLines as $warning): ?>
                <li><?php echo $warning; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($errorLines)): ?>
    <div class="message error">
        <strong>Terjadi <?php echo count($errorLines); ?> kesalahan (video tidak diimpor):</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <?php foreach ($errorLines as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="card">
        <div class="card-header">
            <h3>Konfigurasi Import</h3>
        </div>
        <div style="padding: 1.5rem;">
            <div class="form-group">
                <label for="mass_category_id">Pilih Kategori untuk Semua Video (Opsional)</label>
                <select name="mass_category_id" id="mass_category_id" class="form-select">
                    <option value="">-- Biarkan Sesuai Data di CSV --</option>
                    <?php foreach ($allCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($selected_category_id == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Jika kategori di sini dipilih, maka akan mengabaikan kolom kategori dari data CSV.</p>
            </div>
            
            <div class="form-group">
                <label for="allowed_domains">Domain yang Diizinkan untuk Cloning (Opsional)</label>
                <textarea name="allowed_domains" id="allowed_domains" class="form-textarea" rows="4" placeholder="Masukkan domain yang ingin di-clone, satu per baris. Contoh:&#10;ryderjet.com&#10;stbhg.click&#10;vidply.com&#10;trailerhg.xyz"><?php echo htmlspecialchars($allowed_domains_input); ?></textarea>
                <p class="form-hint">Masukkan semua domain yang bisa dikloning di sini, termasuk domain player dan trailer.</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3>Input Data Video</h3>
        </div>
        <div class="form-group" style="padding: 1.5rem;">
            <label for="mass_data">Data Video (satu video per baris)</label>
            <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                <strong>Format:</strong><br>
                <code>judul|deskripsi|thumbnail|link embed player|link embed trailer|url gambar gallery|genres|kategori|release date|duration|actress|studio</code>
            </p>
            <textarea name="mass_data" id="mass_data" class="form-textarea" rows="15" placeholder="Tempel data video Anda di sini..."></textarea>
        </div>
        <div style="padding: 0 1.5rem 1.5rem 1.5rem;">
             <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Proses Import</button>
        </div>
    </div>
</form>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
