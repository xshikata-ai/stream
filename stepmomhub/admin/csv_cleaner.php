<?php
// File: admin/csv_cleaner.php
require_once __DIR__ . '/../include/config.php';

$output_data = '';
$error_message = '';
$processed_rows = 0;
$unique_videos = 0;
$allowed_domains_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $allowed_domains_input = $_POST['allowed_domains'] ?? '';
    $allowed_domains = [];
    if (!empty($allowed_domains_input)) {
        $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_domains_input)));
    }

    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        
        $grouped_videos = [];
        $thumbnail_map = []; // Untuk menyimpan peta thumbnail [kode => url]

        if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
            $headers = array_map('trim', fgetcsv($handle));
            if ($headers === false) {
                $error_message = "Gagal membaca header dari file CSV.";
            } else {
                // ========================================================================
                // TAHAP 1: Membaca seluruh CSV untuk memisahkan data detail dan thumbnail
                // ========================================================================
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($headers) !== count($data)) continue;
                    $row = array_combine($headers, $data);
                    
                    // JIKA INI BARIS KHUSUS THUMBNAIL (thumbnail-src ada, link-href kosong)
                    if (!empty($row['thumbnail-src']) && empty($row['link-href'])) {
                        $thumbnail_url = $row['thumbnail-src'];
                        // Ekstrak kode dari URL thumbnail, cth: "jul-001" dari ".../jul-001.webp"
                        $video_code = strtolower(pathinfo($thumbnail_url, PATHINFO_FILENAME));
                        if (!isset($thumbnail_map[$video_code])) {
                            $thumbnail_map[$video_code] = $thumbnail_url;
                        }
                    } 
                    // JIKA INI BARIS DETAIL VIDEO (link-href ada)
                    else if (!empty($row['link-href'])) {
                        $unique_key = $row['link-href'];

                        if (!isset($grouped_videos[$unique_key])) {
                            $grouped_videos[$unique_key] = [
                                'judul' => $row['judul'] ?? '',
                                'deskripsi' => $row['deskripsi'] ?? '',
                                'thumbnail' => '', // Dikosongkan dulu, akan diisi nanti
                                'release date' => $row['release date'] ?? '',
                                'link embed trailer' => $row['link embed trailer'] ?? '',
                                'studio' => $row['studio'] ?? '',
                                'durasi' => $row['durasi'] ?? '',
                                'embeds' => [],
                                'gallery_images' => [],
                                'all_genres' => [],
                                'all_actresses' => [],
                            ];
                        }
                        
                        // Kumpulkan semua data dari baris-baris duplikat
                        if (!empty($row['link embed player'])) $grouped_videos[$unique_key]['embeds'][] = $row['link embed player'];
                        if (!empty($row['gallery-src']))     $grouped_videos[$unique_key]['gallery_images'][] = $row['gallery-src'];
                        if (!empty($row['genres']))          $grouped_videos[$unique_key]['all_genres'][] = $row['genres'];
                        if (!empty($row['actress']))         $grouped_videos[$unique_key]['all_actresses'][] = $row['actress'];
                    }
                }
                fclose($handle);

                // ========================================================================
                // TAHAP 2: Mencocokkan detail video dengan peta thumbnail
                // ========================================================================
                foreach ($grouped_videos as $link_href => &$video_data) { // Pakai '&' untuk modifikasi langsung
                    $raw_judul = $video_data['judul'];
                    // Cari kode video dalam judul, cth: "JUL-001" dari "JUL-001-SUB..."
                    if (preg_match('/([a-z]+-\d+)/i', $raw_judul, $matches)) {
                        $video_code_from_title = strtolower($matches[1]);
                        
                        // Jika kode dari judul ditemukan di peta thumbnail, pasangkan!
                        if (isset($thumbnail_map[$video_code_from_title])) {
                            $video_data['thumbnail'] = $thumbnail_map[$video_code_from_title];
                        }
                    }
                }
                unset($video_data); // Hapus referensi setelah loop selesai


                // ========================================================================
                // TAHAP 3: Memproses dan memformat output akhir
                // ========================================================================
                $output_lines = [];
                foreach ($grouped_videos as $video_group) {
                    $processed_rows++;
                    
                    $raw_judul = $video_group['judul'];
                    $judul = 'Judul Tidak Ditemukan';
                    if (preg_match('/^(.*?\])/', $raw_judul, $matches)) {
                        $judul = trim($matches[1]);
                    } else if (preg_match('/([a-z]+-\d+.*)/i', $raw_judul, $matches)) {
                        // Fallback jika format [xxx] tidak ada, ambil dari kode video
                        $judul = trim($matches[1]);
                    }
                    
                    $deskripsi = $video_group['deskripsi'];
                    $thumbnail = $video_group['thumbnail']; // Sekarang sudah terisi thumbnail yang benar
                    $trailer = $video_group['link embed trailer'];
                    
                    $collected_embeds = array_unique($video_group['embeds']);
                    $final_embeds = [];
                    if (!empty($allowed_domains)) {
                        foreach ($collected_embeds as $embed_url) {
                            $host = str_replace('www.', '', parse_url($embed_url, PHP_URL_HOST));
                            if (in_array($host, $allowed_domains)) {
                                $url_parts = parse_url($embed_url);
                                if(isset($url_parts['scheme'], $url_parts['host'], $url_parts['path'])){
                                    $final_embeds[] = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
                                }
                            }
                        }
                    } else {
                        foreach ($collected_embeds as $embed_url) {
                            $url_parts = parse_url($embed_url);
                            if(isset($url_parts['scheme'], $url_parts['host'], $url_parts['path'])){
                                $final_embeds[] = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
                            }
                        }
                    }
                    $embed_links = implode(',', $final_embeds);

                    $clean_gallery_urls = array_filter(array_unique($video_group['gallery_images']), function($url) {
                        $isValidUrl = filter_var($url, FILTER_VALIDATE_URL);
                        $isNotGif = strtolower(pathinfo($url, PATHINFO_EXTENSION)) !== 'gif';
                        return $isValidUrl && $isNotGif;
                    });
                    
                    $hd_gallery_urls = [];
                    foreach ($clean_gallery_urls as $url) {
                        if (strpos($url, 'pics.dmm.co.jp') !== false) {
                            $pattern = '/(-\d+\.jpg)$/i';
                            $replacement = 'jp\1';
                            $hd_gallery_urls[] = preg_replace($pattern, $replacement, $url);
                        } else {
                            $hd_gallery_urls[] = $url;
                        }
                    }
                    $gallery = implode(',', $hd_gallery_urls);

                    $genres = implode(',', array_unique(array_filter($video_group['all_genres'])));
                    
                    $clean_actresses = [];
                    foreach(array_unique(array_filter($video_group['all_actresses'])) as $actress_str) {
                        $actress_name = str_ireplace('Cast(s):', '', $actress_str);
                        $actress_name = preg_replace('/\s*\(.*?\)/', '', $actress_name);
                        $clean_actresses[] = trim($actress_name);
                    }
                    $actress = implode(',', array_unique($clean_actresses));
                    
                    $studio = str_ireplace('Studio:', '', $video_group['studio']);
                    $release_date_raw = $video_group['release date'];
                    $release_date = !empty($release_date_raw) ? date('d-m-Y', strtotime($release_date_raw)) : date('d-m-Y');
                    $duration = trim($video_group['durasi']) . ' min';
                    $kategori = '';

                    $output_lines[] = implode('|', array_map('trim', [
                        $judul, $deskripsi, $thumbnail, $embed_links, $trailer, $gallery, 
                        $genres, $kategori, $release_date, $duration, $actress, $studio
                    ]));
                }
                $unique_videos = count($output_lines);
                $output_data = implode("\n", $output_lines);
            }
        } else {
            $error_message = "Gagal membuka file yang diunggah.";
        }
    } else {
        $error_message = "Terjadi kesalahan saat mengunggah file. Kode Error: " . $_FILES['csv_file']['error'];
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pembersih & Formatter CSV</h1>
<p class="page-desc">Unggah file CSV dari web scrapper Anda untuk merapikannya ke dalam format Mass Import.</p>

<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>1. Konfigurasi & Unggah</h3>
    </div>
    <form method="POST" action="" enctype="multipart/form-data" style="padding: 1.5rem;">
        <div class="form-group">
            <label for="allowed_domains">Domain Embed yang Diizinkan (Opsional)</label>
            <textarea name="allowed_domains" id="allowed_domains" class="form-textarea" rows="4" placeholder="Masukkan domain yang ingin dipakai, satu per baris. Contoh:&#10;turboplayers.xyz&#10;stbhg.click"><?php echo isset($_POST['allowed_domains']) ? htmlspecialchars($_POST['allowed_domains']) : ''; ?></textarea>
            <p class="form-hint">Jika dikosongkan, semua link embed dari CSV akan dipakai.</p>
        </div>
        
        <div class="form-group">
            <label for="csv_file">Pilih File CSV Anda</label>
            <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-cog"></i> Proses File</button>
    </form>
</div>

<?php if ($output_data): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3>2. Salin Hasil</h3>
    </div>
    <div style="padding: 1.5rem;">
        <p class="message success">Berhasil memproses dan menggabungkan data menjadi <strong><?php echo $unique_videos; ?></strong> video unik. Salin teks di bawah ini dan tempel ke fitur Mass Import.</p>
        <div class="form-group">
            <label for="output_data">Data Siap Pakai</label>
            <textarea id="output_data" class="form-textarea" rows="20" readonly><?php echo htmlspecialchars($output_data); ?></textarea>
        </div>
        <button class="btn btn-secondary" onclick="copyToClipboard()"><i class="fas fa-copy"></i> Salin ke Clipboard</button>
    </div>
</div>

<script>
function copyToClipboard() {
    const textarea = document.getElementById('output_data');
    textarea.select();
    document.execCommand('copy');
    alert('Teks berhasil disalin ke clipboard!');
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/templates/footer.php';
?>