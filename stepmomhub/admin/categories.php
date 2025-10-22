<?php
// File: admin/categories.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;
$editCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category' || $action === 'update_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color_hex = trim($_POST['color_hex'] ?? '#D91881');

        if (empty($name)) {
            $error = "Nama kategori tidak boleh kosong.";
        } else {
            if ($action === 'add_category') {
                $success = insertCategory($name, $description, $color_hex);
                if($success) $message = "Kategori '$name' berhasil ditambahkan.";
                else $error = "Gagal menambahkan. Mungkin nama sudah ada?";
            } elseif ($action === 'update_category') {
                $id = (int)($_POST['id'] ?? 0);
                $success = updateCategory($id, $name, $description, $color_hex);
                if($success) $message = "Kategori '$name' berhasil diperbarui.";
                else $error = "Gagal memperbarui kategori.";
            }
        }
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $success = deleteCategory($id);
        if ($success) {
            $message = "Kategori berhasil dihapus.";
        } else {
            $error = "Gagal menghapus kategori.";
        }
    } 
    // LOGIKA PENGATURAN TAMPILAN: Menangani submit form untuk pengaturan border
    elseif ($action === 'save_display_settings') {
        $border_enabled = isset($_POST['enable_category_border']) ? '1' : '0';
        if (updateSetting('enable_category_border', $border_enabled)) {
            $message = "Pengaturan tampilan berhasil disimpan.";
        } else {
            $error = "Gagal menyimpan pengaturan tampilan.";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editCategory = getCategories((int)$_GET['id']);
}

// LOGIKA PENGATURAN TAMPILAN: Mengambil data pengaturan border untuk ditampilkan di form
$enable_border_setting = getSetting('enable_category_border');

$categories = getCategories();
require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Kelola Kategori</h1>
<p class="page-desc">Tambah, edit, atau hapus kategori untuk mengelompokkan video Anda. Anda juga bisa mengatur tampilan terkait kategori di sini.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?php echo $editCategory ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h3></div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editCategory ? 'update_category' : 'add_category'; ?>">
        <?php if ($editCategory): ?><input type="hidden" name="id" value="<?php echo htmlspecialchars($editCategory['id']); ?>"><?php endif; ?>

        <div class="form-group"><label for="name">Nama Kategori</label><input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="description">Deskripsi (Opsional)</label><textarea name="description" id="description" class="form-textarea"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea></div>
        
        <div class="form-group">
            <label for="color_hex">Warna Latar Badge</label>
            <input type="color" name="color_hex" id="color_hex" value="<?php echo htmlspecialchars($editCategory['color_hex'] ?? '#D91881'); ?>" style="height: 40px; width: 100px;">
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editCategory ? 'Simpan Perubahan' : 'Tambah Kategori'; ?></button>
        <?php if ($editCategory): ?><a href="<?php echo ADMIN_PATH; ?>categories.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal Edit</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Pengaturan Tampilan Border Kategori</h3></div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="save_display_settings">
        <div class="form-group">
            <label for="enable_category_border">Border Kategori pada Thumbnail</label>
            <div class="checkbox-wrapper">
                <input type="checkbox" name="enable_category_border" id="enable_category_border" value="1" <?php if ($enable_border_setting === '1') echo 'checked'; ?>>
                <label for="enable_category_border" class="checkbox-label">Aktifkan border dengan warna sesuai kategori.</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan Tampilan</button>
    </form>
</div>


<div class="card">
    <div class="card-header"><h3>Daftar Kategori Saat Ini</h3></div>
     <?php if (!empty($categories)): ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Warna</th>
                        <th>Nama</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><div style="width: 30px; height: 30px; background-color: <?php echo htmlspecialchars($category['color_hex']); ?>; border-radius: 50%;"></div></td>
                        <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($category['description'] ?? '...'); ?></td>
                        <td class="actions">
                            <a href="?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus kategori ini? Video terkait akan menjadi tidak berkategori.');"><i class="fas fa-trash"></i> Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="message info">Belum ada kategori yang dibuat.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>