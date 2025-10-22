<?php
session_start();

// --- KONFIGURASI ---
define('USERNAME', 'admin');
define('PASSWORD', 'admin'); // GANTI INI!
define('JSON_FILE', 'ssh.json');
// PENTING: Path ke kunci SSH privat di Server B yang akan digunakan untuk koneksi
define('SSH_PRIVATE_KEY_PATH', '/root/.ssh/id_ed25519.pub'); // GANTI SESUAI LOKASI KUNCI ANDA

// --- FUNGSI BANTUAN ---
function get_servers() {
    if (!file_exists(JSON_FILE)) return [];
    return json_decode(file_get_contents(JSON_FILE), true);
}

function save_servers($data) {
    file_put_contents(JSON_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Inisialisasi file json jika belum ada
if (!file_exists(JSON_FILE)) save_servers([]);

// --- LOGIKA APLIKASI ---
$error = '';
$page_title = 'SSH Manager'; // Judul halaman default

// Logika Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === USERNAME && $_POST['password'] === PASSWORD) {
        $_SESSION['loggedin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}

// Logika Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Logika Tambah Server
if ($_SESSION['loggedin'] ?? false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_server'])) {
        $servers = get_servers();
        $servers[] = [
            'nama' => htmlspecialchars($_POST['nama']),
            'host' => htmlspecialchars($_POST['host']),
            'user' => htmlspecialchars($_POST['user'])
        ];
        save_servers($servers);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- BAGIAN INTI: LOGIKA TERMINAL SSH ---
$output = '';
$current_server_config = null;
if (($_SESSION['loggedin'] ?? false) && isset($_GET['action']) && $_GET['action'] === 'terminal') {
    $servers = get_servers();
    $server_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
    
    if (isset($servers[$server_id])) {
        $current_server_config = $servers[$server_id];
        $page_title = 'Terminal: ' . $current_server_config['nama'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
            $command = $_POST['command'];
            
            // Cek apakah ekstensi ssh2 ada
            if (!function_exists('ssh2_connect')) {
                $output = "ERROR: Ekstensi PHP SSH2 tidak terinstal atau tidak aktif di Server B.";
            } else {
                $connection = @ssh2_connect($current_server_config['host'], 22);
                if (!$connection) {
                    $output = "ERROR: Gagal terhubung ke " . $current_server_config['host'];
                } else {
                    // Autentikasi menggunakan kunci SSH
                    if (@ssh2_auth_pubkey_file($connection, $current_server_config['user'], SSH_PRIVATE_KEY_PATH . '.pub', SSH_PRIVATE_KEY_PATH)) {
                        $stream = ssh2_exec($connection, $command);
                        stream_set_blocking($stream, true);
                        $stream_out = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
                        $output = stream_get_contents($stream_out);
                        fclose($stream);
                    } else {
                        $output = "ERROR: Autentikasi kunci SSH gagal. Pastikan kunci dari Server B sudah terdaftar di server tujuan.";
                    }
                }
            }
        }
    } else {
        // Jika ID server tidak valid, kembalikan ke dashboard
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        /* CSS dari sebelumnya, tidak perlu diubah */
        body { font-family: sans-serif; background-color: #f4f4f4; color: #333; line-height: 1.6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #555; }
        input[type="text"], input[type="password"] { width: 95%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ddd; }
        button { background: #337ab7; color: #fff; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #286090; }
        .error { color: red; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        a { color: #337ab7; text-decoration: none; }
        .logout { float: right; }
        .form-tambah { margin-top: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 5px; }
        pre { background-color: #222; color: #eee; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; }
        .terminal-form input[type="text"] { font-family: monospace; background: #333; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if (!($_SESSION['loggedin'] ?? false)): // HALAMAN LOGIN ?>
            <h1>SSH Connection Manager</h1>
            <h2>Login</h2>
            <?php if ($error): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="login" value="1">
                <label for="username">Username:</label><input type="text" id="username" name="username" required>
                <label for="password">Password:</label><input type="password" id="password" name="password" required>
                <button type="submit">Login</button>
            </form>

        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'terminal' && $current_server_config): // HALAMAN TERMINAL ?>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="logout">&larr; Kembali ke Dashboard</a>
            <h1>Terminal: <?php echo htmlspecialchars($current_server_config['nama']); ?></h1>
            <p>Menjalankan perintah sebagai <strong><?php echo htmlspecialchars($current_server_config['user'] . '@' . $current_server_config['host']); ?></strong></p>

            <?php if ($output): ?>
                <h2>Output:</h2>
                <pre><?php echo htmlspecialchars($output); ?></pre>
            <?php endif; ?>

            <form method="post" class="terminal-form">
                <input type="hidden" name="command" value="dummy">
                <label for="command">$</label>
                <input type="text" id="command" name="command" size="80" autofocus autocomplete="off">
                <button type="submit">Jalankan</button>
            </form>

        <?php else: // HALAMAN UTAMA (DASHBOARD) ?>
            <a href="?action=logout" class="logout">Logout</a>
            <h1>SSH Connection Manager</h1>
            <h2>Daftar Server</h2>
            <table>
                <thead><tr><th>Nama Server</th><th>Host</th><th>User</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php
                        $servers = get_servers();
                        if (empty($servers)) {
                            echo '<tr><td colspan="4">Belum ada server yang ditambahkan.</td></tr>';
                        } else {
                            foreach ($servers as $index => $server) {
                                echo '<tr>';
                                echo '<td>' . $server['nama'] . '</td>';
                                echo '<td>' . $server['host'] . '</td>';
                                echo '<td>' . $server['user'] . '</td>';
                                // Link Aksi untuk ke terminal
                                echo '<td><a href="?action=terminal&id=' . $index . '">Jalankan Perintah</a></td>';
                                echo '</tr>';
                            }
                        }
                    ?>
                </tbody>
            </table>

            <div class="form-tambah">
                <h2>Tambah Server Baru</h2>
                <form method="post">
                    <input type="hidden" name="add_server" value="1">
                    <label for="nama">Nama Server:</label><input type="text" id="nama" name="nama" placeholder="Contoh: Server Utama" required>
                    <label for="host">Host (IP/Domain):</label><input type="text" id="host" name="host" placeholder="Contoh: 192.168.1.100" required>
                    <label for="user">Username SSH:</label><input type="text" id="user" name="user" placeholder="Contoh: root" required>
                    <button type="submit">Tambah Server</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
