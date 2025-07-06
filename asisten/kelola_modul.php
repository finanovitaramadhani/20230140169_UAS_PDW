<?php
// asisten/kelola_modul.php
// Halaman ini memungkinkan asisten untuk mengelola (CRUD) modul/pertemuan
// untuk setiap mata praktikum, termasuk unggah file materi.

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file konfigurasi database
require_once '../config.php';

// Cek jika pengguna belum login atau bukan asisten
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Kelola Modul Praktikum';
$activePage = 'modul'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

// Direktori tempat menyimpan file materi
$upload_dir = '../uploads/materi_modul/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
}

// --- Logika CRUD ---

// 1. Tambah/Edit Modul
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_modul'])) {
    $id_praktikum = intval($_POST['id_praktikum']);
    $nama_modul = trim($_POST['nama_modul']);
    $deskripsi = trim($_POST['deskripsi']);
    $modul_id = isset($_POST['modul_id']) ? intval($_POST['modul_id']) : 0;
    $file_materi_lama = trim($_POST['file_materi_lama'] ?? ''); // Untuk update

    if (empty($id_praktikum) || empty($nama_modul)) {
        $message = '<p class="text-red-500">Mata Praktikum dan Nama Modul tidak boleh kosong!</p>';
    } else {
        $file_materi_path = $file_materi_lama; // Default ke file lama jika tidak ada upload baru

        // Handle file upload
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['file_materi']['tmp_name'];
            $file_name = basename($_FILES['file_materi']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'docx']; // Hanya izinkan PDF dan DOCX

            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = uniqid('modul_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $file_materi_path = $new_file_name;
                    // Hapus file lama jika ada dan ini adalah update
                    if ($modul_id > 0 && !empty($file_materi_lama) && file_exists($upload_dir . $file_materi_lama)) {
                        unlink($upload_dir . $file_materi_lama);
                    }
                } else {
                    $message = '<p class="text-red-500">Gagal mengunggah file materi.</p>';
                }
            } else {
                $message = '<p class="text-red-500">Format file tidak diizinkan. Hanya PDF atau DOCX.</p>';
            }
        }

        if (empty($message)) { // Lanjutkan jika tidak ada error upload
            if ($modul_id > 0) {
                // Update data
                $sql = "UPDATE modul SET id_praktikum = ?, nama_modul = ?, deskripsi = ?, file_materi = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssi", $id_praktikum, $nama_modul, $deskripsi, $file_materi_path, $modul_id);
                if ($stmt->execute()) {
                    $message = '<p class="text-green-500">Modul berhasil diperbarui!</p>';
                } else {
                    $message = '<p class="text-red-500">Gagal memperbarui modul: ' . $conn->error . '</p>';
                }
                $stmt->close();
            } else {
                // Tambah data baru
                $sql = "INSERT INTO modul (id_praktikum, nama_modul, deskripsi, file_materi) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isss", $id_praktikum, $nama_modul, $deskripsi, $file_materi_path);
                if ($stmt->execute()) {
                    $message = '<p class="text-green-500">Modul baru berhasil ditambahkan!</p>';
                } else {
                    $message = '<p class="text-red-500">Gagal menambahkan modul: ' . $conn->error . '</p>';
                }
                $stmt->close();
            }
        }
    }
}

// 2. Hapus Modul
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);

    // Ambil nama file materi sebelum dihapus dari database
    $sql_get_file = "SELECT file_materi FROM modul WHERE id = ?";
    $stmt_get_file = $conn->prepare($sql_get_file);
    $stmt_get_file->bind_param("i", $id_to_delete);
    $stmt_get_file->execute();
    $result_get_file = $stmt_get_file->get_result();
    $file_to_delete = '';
    if ($result_get_file->num_rows > 0) {
        $row = $result_get_file->fetch_assoc();
        $file_to_delete = $row['file_materi'];
    }
    $stmt_get_file->close();

    $sql = "DELETE FROM modul WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        // Hapus file materi dari server jika ada
        if (!empty($file_to_delete) && file_exists($upload_dir . $file_to_delete)) {
            unlink($upload_dir . $file_to_delete);
        }
        $message = '<p class="text-green-500">Modul berhasil dihapus!</p>';
    } else {
        $message = '<p class="text-red-500">Gagal menghapus modul: ' . $conn->error . '</p>';
    }
    $stmt->close();
    // Redirect untuk menghindari resubmission form
    header("Location: kelola_modul.php?message=" . urlencode(strip_tags($message)));
    exit();
}

// Ambil data mata praktikum untuk dropdown
$mata_praktikum_list = [];
$sql_praktikum = "SELECT id, nama_praktikum FROM mata_praktikum ORDER BY nama_praktikum ASC";
$result_praktikum = $conn->query($sql_praktikum);
if ($result_praktikum) {
    while ($row = $result_praktikum->fetch_assoc()) {
        $mata_praktikum_list[] = $row;
    }
}

// Ambil data modul untuk ditampilkan
$modul_list = [];
$sql_modul = "SELECT m.id, m.nama_modul, m.deskripsi, m.file_materi, mp.nama_praktikum 
              FROM modul m JOIN mata_praktikum mp ON m.id_praktikum = mp.id 
              ORDER BY mp.nama_praktikum, m.nama_modul ASC";
$result_modul = $conn->query($sql_modul);
if ($result_modul) {
    while ($row = $result_modul->fetch_assoc()) {
        $modul_list[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data modul: ' . $conn->error . '</p>';
}

// Ambil pesan dari redirect (setelah delete)
if (isset($_GET['message'])) {
    $message = '<p class="text-green-500">' . htmlspecialchars($_GET['message']) . '</p>';
}

// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Modul Praktikum</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah/Edit Modul -->
    <div class="mb-8 p-6 border border-gray-200 rounded-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">
            <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Edit Modul' : 'Tambah Modul Baru'; ?>
        </h3>
        <?php
        $edit_data = ['id' => '', 'id_praktikum' => '', 'nama_modul' => '', 'deskripsi' => '', 'file_materi' => ''];
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $id_to_edit = intval($_GET['id']);
            $sql_edit = "SELECT id, id_praktikum, nama_modul, deskripsi, file_materi FROM modul WHERE id = ?";
            $stmt_edit = $conn->prepare($sql_edit);
            $stmt_edit->bind_param("i", $id_to_edit);
            $stmt_edit->execute();
            $result_edit = $stmt_edit->get_result();
            if ($result_edit->num_rows > 0) {
                $edit_data = $result_edit->fetch_assoc();
            }
            $stmt_edit->close();
        }
        ?>
        <form action="kelola_modul.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="modul_id" value="<?php echo htmlspecialchars($edit_data['id']); ?>">
            <input type="hidden" name="file_materi_lama" value="<?php echo htmlspecialchars($edit_data['file_materi']); ?>">

            <div class="mb-4">
                <label for="id_praktikum" class="block text-gray-700 text-sm font-bold mb-2">Mata Praktikum:</label>
                <select id="id_praktikum" name="id_praktikum"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="">Pilih Mata Praktikum</option>
                    <?php foreach ($mata_praktikum_list as $praktikum): ?>
                        <option value="<?php echo htmlspecialchars($praktikum['id']); ?>"
                            <?php echo ($edit_data['id_praktikum'] == $praktikum['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($praktikum['nama_praktikum']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="nama_modul" class="block text-gray-700 text-sm font-bold mb-2">Nama Modul:</label>
                <input type="text" id="nama_modul" name="nama_modul" value="<?php echo htmlspecialchars($edit_data['nama_modul']); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi:</label>
                <textarea id="deskripsi" name="deskripsi" rows="3"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_data['deskripsi']); ?></textarea>
            </div>
            <div class="mb-6">
                <label for="file_materi" class="block text-gray-700 text-sm font-bold mb-2">File Materi (PDF/DOCX):</label>
                <input type="file" id="file_materi" name="file_materi"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <?php if (!empty($edit_data['file_materi'])): ?>
                    <p class="text-sm text-gray-600 mt-2">File saat ini: <a href="../uploads/materi_modul/<?php echo htmlspecialchars($edit_data['file_materi']); ?>" target="_blank" class="text-blue-500 hover:underline"><?php echo htmlspecialchars($edit_data['file_materi']); ?></a></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" name="submit_modul"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Perbarui Modul' : 'Tambah Modul'; ?>
                </button>
                <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                    <a href="kelola_modul.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Modul -->
    <?php if (empty($modul_list)): ?>
        <p class="text-gray-600">Belum ada modul yang ditambahkan.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Mata Praktikum</th>
                        <th class="py-3 px-6 text-left">Nama Modul</th>
                        <th class="py-3 px-6 text-left">Deskripsi</th>
                        <th class="py-3 px-6 text-center">Materi</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($modul_list as $modul): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($modul['nama_praktikum']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($modul['nama_modul']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars(substr($modul['deskripsi'], 0, 70)) . (strlen($modul['deskripsi']) > 70 ? '...' : ''); ?></td>
                            <td class="py-3 px-6 text-center">
                                <?php if (!empty($modul['file_materi'])): ?>
                                    <a href="../uploads/materi_modul/<?php echo htmlspecialchars($modul['file_materi']); ?>" target="_blank" class="text-blue-500 hover:underline">Unduh</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-2">
                                    <a href="kelola_modul.php?action=edit&id=<?php echo $modul['id']; ?>"
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-xs">Edit</a>
                                    <a href="kelola_modul.php?action=delete&id=<?php echo $modul['id']; ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus modul ini? Semua laporan terkait juga akan terhapus.');"
                                       class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-xs">Hapus</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// Panggil footer
require_once 'templates/footer.php';
$conn->close();
?>
