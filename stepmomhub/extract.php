<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Extractor</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background-color: #1e1e2e;
            color: #e0e0e0;
            line-height: 1.6;
        }
        h1 {
            text-align: center;
            color: #ffffff;
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }
        .container {
            background-color: #2a2a3c;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        textarea {
            width: 100%;
            height: 250px;
            padding: 12px;
            font-size: 1rem;
            background-color: #3b3b4f;
            color: #e0e0e0;
            border: 1px solid #4a4a6a;
            border-radius: 6px;
            resize: vertical;
            margin-bottom: 1rem;
        }
        textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        button {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #6366f1;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #5455d6;
        }
        .result-container {
            margin-top: 1.5rem;
        }
        pre {
            background-color: #3b3b4f;
            padding: 15px;
            border-radius: 6px;
            font-size: 0.95rem;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .error {
            color: #f87171;
            font-weight: 600;
            margin-top: 1rem;
            text-align: center;
        }
        .copy-button {
            display: none;
            margin-top: 1rem;
            padding: 10px;
            background-color: #10b981;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .copy-button:hover {
            background-color: #059669;
        }
        .copy-button.visible {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Domain Extractor</h1>
        <form method="post">
            <textarea name="urls" placeholder="Masukkan daftar URL, satu per baris..."><?php echo isset($_POST['urls']) ? htmlspecialchars($_POST['urls']) : ''; ?></textarea>
            <button type="submit">Ekstrak Domain</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['urls'])) {
            $urls = $_POST['urls'];
            $url_list = explode("\n", trim($urls));
            $domains = [];

            foreach ($url_list as $line) {
                // Bersihkan baris dari whitespace dan nomor urut (misal: "1.", "2.", dst.)
                $line = trim(preg_replace('/^\d+\.\s*/', '', $line));
                
                // Abaikan baris yang bukan URL (misal: "SETORAN")
                if (empty($line) || !preg_match('/^https?:\/\//i', $line)) {
                    continue;
                }

                // Parse URL untuk mendapatkan host
                $parsed = parse_url($line);
                if (isset($parsed['host']) && !empty($parsed['host'])) {
                    $domain = $parsed['host'];
                    // Hapus "www." jika ada
                    $domain = preg_replace('/^www\./i', '', $domain);
                    $domains[] = $domain;
                }
            }

            // Hilangkan duplikat dan urutkan
            $domains = array_unique($domains);
            sort($domains);

            if (!empty($domains)) {
                echo '<div class="result-container">';
                echo '<h2>Hasil Ekstraksi Domain:</h2>';
                echo '<pre id="result">';
                foreach ($domains as $domain) {
                    echo htmlspecialchars($domain) . "\n";
                }
                echo '</pre>';
                echo '<button class="copy-button visible" onclick="copyToClipboard()">Copy to Clipboard</button>';
                echo '</div>';
            } else {
                echo '<p class="error">Tidak ada domain yang valid ditemukan. Pastikan URL dimasukkan dengan format yang benar (http:// atau https://).</p>';
            }
        }
        ?>
    </div>

    <script>
        function copyToClipboard() {
            const result = document.getElementById('result');
            const text = result.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Domains copied to clipboard!');
            }).catch(err => {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>