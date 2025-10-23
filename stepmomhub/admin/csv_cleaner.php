<?php
// File: admin/csv_cleaner.php
require_once __DIR__ . '/../include/config.php';
// Mulai sesi untuk menyimpan data sementara
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$output_data = '';
$error_message = '';
$processed_rows = 0;
$unique_videos = 0;
$allowed_domains_input = $_POST['allowed_domains'] ?? ($_SESSION['csv_cleaner_allowed_domains'] ?? ''); // Ambil dari POST atau Session
$step = 'upload'; // Langkah awal: 'upload', 'fix_thumbs', 'show_result'
$videos_to_fix = []; // Video yang butuh perbaikan thumbnail
$grouped_videos_from_session = null; // Data video sementara

// --- Logika Penanganan Langkah ---

// Langkah 3: Menampilkan Hasil Akhir (setelah koreksi atau jika tidak ada masalah)
if (isset($_SESSION['csv_cleaner_final_output'])) {
    $step = 'show_result';
    $output_data = $_SESSION['csv_cleaner_final_output'];
    $unique_videos = substr_count($output_data, "\n") + 1; // Hitung baris
    // Hapus data session setelah ditampilkan
    unset($_SESSION['csv_cleaner_grouped_videos']);
    unset($_SESSION['csv_cleaner_videos_to_fix']);
    unset($_SESSION['csv_cleaner_allowed_domains']);
    unset($_SESSION['csv_cleaner_final_output']);
}

// Langkah 2b: Memproses Koreksi Thumbnail yang Disubmit
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_thumbnail_corrections') {
    if (isset($_SESSION['csv_cleaner_grouped_videos']) && isset($_SESSION['csv_cleaner_videos_to_fix'])) {
        $grouped_videos = $_SESSION['csv_cleaner_grouped_videos'];
        $submitted_thumbnails = $_POST['thumbnails'] ?? [];

        // Update thumbnail di data grup
        foreach ($_SESSION['csv_cleaner_videos_to_fix'] as $key => $details) {
            $unique_key = $details['unique_key'];
            if (isset($submitted_thumbnails[$unique_key]) && !empty(trim($submitted_thumbnails[$unique_key]))) {
                 // Validasi URL sederhana
                 if (filter_var(trim($submitted_thumbnails[$unique_key]), FILTER_VALIDATE_URL)) {
                    $grouped_videos[$unique_key]['thumbnail'] = trim($submitted_thumbnails[$unique_key]);
                 } else {
                     // Jika URL tidak valid, mungkin beri error atau biarkan kosong? Kita biarkan kosong saja.
                     $grouped_videos[$unique_key]['thumbnail'] = '';
                      $error_message = "URL Thumbnail untuk " . $details['code'] . " tidak valid."; // Beri tahu user
                 }
            } else {
                // Jika input kosong, biarkan thumbnail kosong
                $grouped_videos[$unique_key]['thumbnail'] = '';
            }
        }

         // Jika tidak ada error URL invalid, proses ke tahap akhir
        if (empty($error_message)) {
            // --- Proses ke Output Final ---
            $final_output_lines = process_grouped_videos_to_output($grouped_videos, $_SESSION['csv_cleaner_allowed_domains']);
            $_SESSION['csv_cleaner_final_output'] = implode("\n", $final_output_lines);
            // Redirect untuk menampilkan hasil (mencegah resubmit form)
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
             // Jika ada error URL invalid, kembali tampilkan form koreksi dengan pesan error
             $step = 'fix_thumbs';
             $videos_to_fix = $_SESSION['csv_cleaner_videos_to_fix']; // Perlu dikirim lagi ke view
             // Tidak unset session agar data tetap ada
        }

    } else {
        $error_message = "Data sesi hilang. Silakan unggah ulang file CSV.";
        $step = 'upload'; // Kembali ke langkah awal
        // Hapus session jika ada yang tersisa
        unset($_SESSION['csv_cleaner_grouped_videos']);
        unset($_SESSION['csv_cleaner_videos_to_fix']);
        unset($_SESSION['csv_cleaner_allowed_domains']);
    }
}

// Langkah 1 & 2a: Memproses Upload CSV Awal & Mendeteksi Thumbnail Bermasalah
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Hapus data sesi lama sebelum memulai
    unset($_SESSION['csv_cleaner_grouped_videos']);
    unset($_SESSION['csv_cleaner_videos_to_fix']);
    unset($_SESSION['csv_cleaner_allowed_domains']);
    unset($_SESSION['csv_cleaner_final_output']);

    $allowed_domains = [];
    if (!empty($allowed_domains_input)) {
        $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_domains_input)));
    }
     // Simpan allowed domains ke session
    $_SESSION['csv_cleaner_allowed_domains'] = $allowed_domains_input;


    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        $grouped_videos = []; // Data video yang sudah digabung
        $videos_to_fix = []; // Video yang thumbnailnya perlu diperbaiki

        if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
            $headers = array_map('trim', fgetcsv($handle));
            if ($headers === false || empty($headers)) {
                $error_message = "Gagal membaca header dari file CSV atau header kosong.";
            } elseif (!in_array('thumbnail-src', $headers) || !in_array('link-href', $headers) || !in_array('judul', $headers)) {
                 $error_message = "Header CSV tidak valid. Pastikan kolom 'thumbnail-src', 'link-href', dan 'judul' ada.";
            } else {
                // TAHAP 1: Baca dan Grouping (Sama seperti versi sebelumnya)
                while (($data = fgetcsv($handle)) !== FALSE) {
                     if (count($headers) !== count($data)) {
                        $data = array_pad($data, count($headers), null);
                        if (count($headers) !== count($data)) continue;
                    }
                    $row = @array_combine($headers, $data);
                    if ($row === false) continue;

                    $unique_key = trim($row['link-href'] ?? '');

                    if (!empty($unique_key)) {
                        if (!isset($grouped_videos[$unique_key])) {
                            $thumbnail_url_from_row = trim($row['thumbnail-src'] ?? '');
                            $grouped_videos[$unique_key] = [
                                'judul' => trim($row['judul'] ?? ''),
                                'deskripsi' => trim($row['deskripsi'] ?? ''),
                                'thumbnail' => $thumbnail_url_from_row, // Ambil langsung
                                'release date' => trim($row['release date'] ?? ''),
                                'link embed trailer' => trim($row['link embed trailer'] ?? ''),
                                'studio' => trim($row['studio'] ?? ''),
                                'durasi' => trim($row['durasi'] ?? ''),
                                'embeds' => [],
                                'gallery_images' => [],
                                'all_genres' => [],
                                'all_actresses' => [],
                            ];
                        }
                        // Kumpulkan data lain
                        if (!empty($row['link embed player'])) $grouped_videos[$unique_key]['embeds'][] = trim($row['link embed player']);
                        if (!empty($row['gallery-src']))     $grouped_videos[$unique_key]['gallery_images'][] = trim($row['gallery-src']);
                        if (!empty($row['genres']))          $grouped_videos[$unique_key]['all_genres'][] = trim($row['genres']);
                        if (!empty($row['actress']))         $grouped_videos[$unique_key]['all_actresses'][] = trim($row['actress']);
                        // Fallback thumbnail jika di baris awal kosong
                        if (empty($grouped_videos[$unique_key]['thumbnail']) && !empty($row['thumbnail-src'])) {
                             $grouped_videos[$unique_key]['thumbnail'] = trim($row['thumbnail-src']);
                        }
                    }
                }
                fclose($handle);

                // TAHAP DETEKSI THUMBNAIL BERMASALAH
                foreach ($grouped_videos as $key => &$video_data) {
                    $thumbnail_check = $video_data['thumbnail'];
                    $is_invalid = false;
                    $reason = '';

                    if (empty($thumbnail_check)) {
                        $is_invalid = true;
                        $reason = 'Kosong';
                    } elseif ($thumbnail_check === '/images/default-cover.jpg') {
                        $is_invalid = true;
                        $reason = 'Default Cover';
                        $video_data['thumbnail'] = ''; // Langsung kosongkan di data utama
                    }

                    if ($is_invalid) {
                        // Dapatkan kode video untuk ditampilkan ke user
                        $code_for_noti = 'UNKNOWN_CODE';
                        $raw_judul_check = $video_data['judul'];
                        if (preg_match('/^([A-Z]+-\d+)/i', $raw_judul_check, $code_match)) {
                             $code_for_noti = strtoupper($code_match[1]);
                        } elseif (preg_match('/([A-Z]+-\d+)/i', $raw_judul_check, $code_match_fallback)) {
                             $code_for_noti = strtoupper($code_match_fallback[1]);
                        }
                        // Simpan info video yang perlu diperbaiki
                        $videos_to_fix[$key] = [
                            'code' => $code_for_noti,
                            'reason' => $reason,
                            'unique_key' => $key // Simpan unique key untuk mapping saat submit
                        ];
                    }
                }
                unset($video_data); // Hapus referensi

                // Putuskan langkah selanjutnya
                if (!empty($videos_to_fix)) {
                    $step = 'fix_thumbs';
                    // Simpan data ke session untuk langkah berikutnya
                    $_SESSION['csv_cleaner_grouped_videos'] = $grouped_videos;
                    $_SESSION['csv_cleaner_videos_to_fix'] = $videos_to_fix;
                } else {
                    // Langsung proses ke output jika tidak ada masalah
                     $final_output_lines = process_grouped_videos_to_output($grouped_videos, $allowed_domains_input);
                     $_SESSION['csv_cleaner_final_output'] = implode("\n", $final_output_lines);
                     // Redirect untuk menampilkan hasil
                     header("Location: " . $_SERVER['REQUEST_URI']);
                     exit;
                }
            }
        } else {
            $error_message = "Gagal membuka file yang diunggah.";
        }
    } else {
        $error_message = "Terjadi kesalahan saat mengunggah file. Kode Error: " . ($_FILES['csv_file']['error'] ?? 'Tidak diketahui');
    }
}


/**
 * Fungsi terpisah untuk memproses data video yang sudah digabung menjadi format output akhir.
 * @param array $grouped_videos Data video yang sudah digabung.
 * @param string $allowed_domains_input String domain yang diizinkan (dari form/session).
 * @return array Array berisi baris-baris output final.
 */
function process_grouped_videos_to_output(array $grouped_videos, string $allowed_domains_input): array {
    $output_lines = [];
    $allowed_domains = [];
     if (!empty($allowed_domains_input)) {
        $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_domains_input)));
    }

    foreach ($grouped_videos as $video_group) {
        // Logika pembersihan Judul (Sama seperti sebelumnya)
        $raw_judul = $video_group['judul'];
        $judul = 'Judul Tidak Ditemukan';
         if (preg_match('/^(.*?\])/', $raw_judul, $matches)) {
            $judul = trim($matches[1]);
        } else if (preg_match('/([a-z]+-\d+.*)/i', $raw_judul, $matches)) {
            $judul = trim($matches[1]);
        } else {
            $judul = $raw_judul;
        }

        $deskripsi = $video_group['deskripsi'];
        $thumbnail = $video_group['thumbnail']; // Ambil thumbnail yang sudah final (mungkin sudah dikoreksi)
        $trailer = $video_group['link embed trailer'];

        // Logika Embed (Sama seperti sebelumnya)
        $collected_embeds = array_unique(array_filter($video_group['embeds']));
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

        // Logika Galeri (Sama seperti sebelumnya)
        $clean_gallery_urls = array_filter(array_unique($video_group['gallery_images']), function($url) {
            return filter_var($url, FILTER_VALIDATE_URL);
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

        // Logika Genres, Actress, Studio (Sama seperti sebelumnya)
        $genres = implode(',', array_unique(array_filter($video_group['all_genres'])));
        $clean_actresses = [];
        foreach(array_unique(array_filter($video_group['all_actresses'])) as $actress_str) {
            $actress_name = str_ireplace('Cast(s):', '', $actress_str);
            $actress_name = preg_replace('/\s*\(.*?\)/', '', $actress_name);
            $clean_actresses[] = trim($actress_name);
        }
        $actress = implode(',', array_unique($clean_actresses));
        $studio = trim(str_ireplace('Studio:', '', $video_group['studio']));

        // Logika Tanggal (Sama seperti sebelumnya)
        $release_date_raw = $video_group['release date'];
        $release_date = !empty($release_date_raw) ? date('d-m-Y', strtotime($release_date_raw)) : date('d-m-Y');

        // Logika Durasi (Sama seperti sebelumnya)
        $duration = trim($video_group['durasi']);
        if (is_numeric($duration)) {
            $duration .= ' min';
        } elseif (empty($duration)) {
            $duration = '0 min';
        }
        $kategori = '';

        $output_columns = array_map('trim', [
            $judul, $deskripsi, $thumbnail, $embed_links, $trailer, $gallery,
            $genres, $kategori, $release_date, $duration, $actress, $studio
        ]);
        $output_columns = array_pad($output_columns, 12, '');
        $output_lines[] = implode('|', $output_columns);
    }
    return $output_lines;
}


require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pembersih & Formatter CSV</h1>
<p class="page-desc">Unggah file CSV dari web scrapper Anda untuk merapikannya ke dalam format Mass Import.</p>

<?php if ($error_message): ?>
    <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<?php if ($step === 'upload'): ?>
<div class="card">
    <div class="card-header">
        <h3>1. Konfigurasi & Unggah</h3>
    </div>
    <form method="POST" action="" enctype="multipart/form-data" style="padding: 1.5rem;">
        <div class="form-group">
            <label for="allowed_domains">Domain Embed yang Diizinkan (Opsional)</label>
            <textarea name="allowed_domains" id="allowed_domains" class="form-textarea" rows="4" placeholder="Masukkan domain yang ingin dipakai, satu per baris. Contoh:&#10;turboplayers.xyz&#10;stbhg.click"><?php echo htmlspecialchars($allowed_domains_input); ?></textarea>
            <p class="form-hint">Jika dikosongkan, semua link embed dari CSV akan dipakai.</p>
        </div>

        <div class="form-group">
            <label for="csv_file">Pilih File CSV Anda</label>
            <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-cog"></i> Proses File</button>
    </form>
</div>
<?php endif; ?>


<?php if ($step === 'fix_thumbs' && !empty($videos_to_fix)): ?>
<div class="card">
    <div class="card-header">
        <h3 style="color: var(--warning-accent);"><i class="fas fa-edit"></i> Perbaiki URL Thumbnail</h3>
    </div>
    <form method="POST" action="" style="padding: 1.5rem;">
        <input type="hidden" name="action" value="submit_thumbnail_corrections">
        <input type="hidden" name="allowed_domains" value="<?php echo htmlspecialchars($allowed_domains_input); ?>">

        <p>Beberapa video memiliki thumbnail yang kosong atau tidak valid. Silakan masukkan URL thumbnail yang benar di bawah ini:</p>

        <?php foreach ($videos_to_fix as $key => $details): ?>
            <div class="form-group" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
                <label for="thumb_<?php echo htmlspecialchars($key); ?>">
                    <strong><?php echo htmlspecialchars($details['code']); ?></strong>
                    <span style="color: var(--text-secondary); font-size: 0.8em;"> (Alasan: <?php echo htmlspecialchars($details['reason']); ?>)</span>
                </label>
                <input type="url"
                       name="thumbnails[<?php echo htmlspecialchars($details['unique_key']); ?>]"
                       id="thumb_<?php echo htmlspecialchars($key); ?>"
                       class="form-input"
                       placeholder="Masukkan URL thumbnail yang benar di sini...">
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan Koreksi & Lanjutkan</button>
         <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Batal & Unggah Ulang</a>

    </form>
</div>
<?php endif; ?>


<?php if ($step === 'show_result' && $output_data): ?>
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
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary" style="margin-left: 10px;">Proses File Lain</a>
    </div>
</div>

<script>
function copyToClipboard() {
    const textarea = document.getElementById('output_data');
    textarea.select();
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            alert('Teks berhasil disalin ke clipboard!');
        }, function(err) {
            document.execCommand('copy');
            alert('Teks berhasil disalin (fallback)!');
        });
    } catch (err) {
        document.execCommand('copy');
        alert('Teks berhasil disalin (fallback total)!');
    }
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/templates/footer.php';
// Hapus session setelah selesai (opsional, tergantung preferensi)
// session_destroy();
?>
