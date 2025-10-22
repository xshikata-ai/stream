<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengecek Domain Lanjutan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #555;
            text-align: center;
        }
        textarea {
            width: 100%;
            height: 150px;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .result h3 {
            margin-top: 0;
            color: #333;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            padding: 8px;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Pengecek Domain Lanjutan</h1>
    <p>Masukkan daftar domain atau URL. Skrip ini akan mengabaikan protokol (https://), www, path, dan teks/angka di awal baris.</p>
    <form action="" method="post">
        <h2>Daftar 1</h2>
        <textarea name="list1" placeholder="Contoh:&#10;1. https://albertaocctesting.com/v2.php&#10;www.josepharmacy.online" required><?php echo isset($_POST['list1']) ? htmlspecialchars($_POST['list1']) : ''; ?></textarea>
        <h2>Daftar 2</h2>
        <textarea name="list2" placeholder="Contoh:&#10;1. https://gadgetzngizmos.com/v2.php&#10;poshify.pk" required><?php echo isset($_POST['list2']) ? htmlspecialchars($_POST['list2']) : ''; ?></textarea>
        <input type="submit" value="Cek Domain">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        function extract_domain($url) {
            $url = trim($url);

            // Bersihkan teks atau angka di awal baris
            $url = preg_replace('/^\s*(\d+\.?\s*|\w+\.?\s*)?(https?:\/\/)/i', 'https://', $url);
            
            // Tambahkan protokol jika tidak ada
            if (strpos($url, 'http') !== 0) {
                $url = 'https://' . $url;
            }

            $parsed_url = parse_url($url);
            $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';

            // Hapus 'www.' jika ada
            $host = str_replace('www.', '', $host);

            return $host;
        }

        $list1_raw = $_POST['list1'];
        $list2_raw = $_POST['list2'];

        $lines1 = explode("\n", $list1_raw);
        $lines2 = explode("\n", $list2_raw);

        $domains1 = array_map('extract_domain', $lines1);
        $domains2 = array_map('extract_domain', $lines2);

        // Hapus entri kosong
        $domains1 = array_filter($domains1);
        $domains2 = array_filter($domains2);

        // Ubah semua ke huruf kecil untuk perbandingan yang konsisten
        $domains1 = array_map('strtolower', $domains1);
        $domains2 = array_map('strtolower', $domains2);

        // Gunakan array_unique untuk menghapus duplikat dalam setiap daftar
        $domains1 = array_unique($domains1);
        $domains2 = array_unique($domains2);

        // Cari irisan (domain yang ada di kedua array)
        $common_domains = array_intersect($domains1, $domains2);

        echo '<div class="result">';
        echo '<h3>Hasil Pengecekan</h3>';
        echo '<p>Jumlah domain yang sama: <strong>' . count($common_domains) . '</strong></p>';

        if (!empty($common_domains)) {
            echo '<h4>Daftar Domain yang Sama:</h4>';
            echo '<ul>';
            foreach ($common_domains as $domain) {
                echo '<li>' . htmlspecialchars($domain) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Tidak ada domain yang sama ditemukan.</p>';
        }
        echo '</div>';
    }
    ?>
</div>

</body>
</html>
