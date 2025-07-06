<?php
// asisten/kelola_mata_praktikum.php
// Halaman ini memungkinkan asisten untuk mengelola (CRUD) data mata praktikum.

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

$pageTitle = 'Kelola Mata Praktikum';
$activePage = 'mata_praktikum'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

// --- Logika CRUD ---

// 1. Tambah/Edit Mata Praktikum
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_praktikum'])) {
    $nama_praktikum = trim($_POST['nama_praktikum']);
    $deskripsi = trim($_POST['deskripsi']);
    $praktikum_id = isset($_POST['praktikum_id']) ? intval($_POST['praktikum_id']) : 0;

    if (empty($nama_praktikum)) {
        $message = '<p class="text-red-500">Nama praktikum tidak boleh kosong!</p>';
    } else {
        if ($praktikum_id > 0) {
            // Update data
            $sql = "UPDATE mata_praktikum SET nama_praktikum = ?, deskripsi = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nama_praktikum, $deskripsi, $praktikum_id);
            if ($stmt->execute()) {
                $message = '<p class="text-green-500">Mata praktikum berhasil diperbarui!</p>';
            } else {
                $message = '<p class="text-red-500">Gagal memperbarui mata praktikum: ' . $conn->error . '</p>';
            }
            $stmt->close();
        } else {
            // Tambah data baru
            $sql = "INSERT INTO mata_praktikum (nama_praktikum, deskripsi) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $nama_praktikum, $deskripsi);
            if ($stmt->execute()) {
                $message = '<p class="text-green-500">Mata praktikum baru berhasil ditambahkan!</p>';
            } else {
                $message = '<p class="text-red-500">Gagal menambahkan mata praktikum: ' . $conn->error . '</p>';
            }
            $stmt->close();
        }
    }
}

// 2. Hapus Mata Praktikum
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    $sql = "DELETE FROM mata_praktikum WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $message = '<p class="text-green-500">Mata praktikum berhasil dihapus!</p>';
    } else {
        $message = '<p class="text-red-500">Gagal menghapus mata praktikum: ' . $conn->error . '</p>';
    }
    $stmt->close();
    // Redirect untuk menghindari resubmission form
    header("Location: kelola_mata_praktikum.php?message=" . urlencode(strip_tags($message)));
    exit();
}

// Ambil data mata praktikum untuk ditampilkan
$mata_praktikum = [];
$sql_select = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum ORDER BY nama_praktikum ASC";
$result_select = $conn->query($sql_select);
if ($result_select) {
    while ($row = $result_select->fetch_assoc()) {
        $mata_praktikum[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data mata praktikum: ' . $conn->error . '</p>';
}

// Ambil pesan dari redirect (setelah delete)
if (isset($_GET['message'])) {
    $message = '<p class="text-green-500">' . htmlspecialchars($_GET['message']) . '</p>';
}

// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Mata Praktikum</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah/Edit Mata Praktikum -->
    <div class="mb-8 p-6 border border-gray-200 rounded-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">
            <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Edit Mata Praktikum' : 'Tambah Mata Praktikum Baru'; ?>
        </h3>
        <?php
        $edit_data = ['id' => '', 'nama_praktikum' => '', 'deskripsi' => ''];
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $id_to_edit = intval($_GET['id']);
            $sql_edit = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum WHERE id = ?";
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
        <form action="kelola_mata_praktikum.php" method="POST">
            <input type="hidden" name="praktikum_id" value="<?php echo htmlspecialchars($edit_data['id']); ?>">
            <div class="mb-4">
                <label for="nama_praktikum" class="block text-gray-700 text-sm font-bold mb-2">Nama Mata Praktikum:</label>
                <input type="text" id="nama_praktikum" name="nama_praktikum" value="<?php echo htmlspecialchars($edit_data['nama_praktikum']); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-6">
                <label for="deskripsi" class="block text-gray-700 text-sm font-bold mb-2">Deskripsi:</label>
                <textarea id="deskripsi" name="deskripsi" rows="4"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($edit_data['deskripsi']); ?></textarea>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" name="submit_praktikum"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Perbarui Praktikum' : 'Tambah Praktikum'; ?>
                </button>
                <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                    <a href="kelola_mata_praktikum.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Mata Praktikum -->
    <?php if (empty($mata_praktikum)): ?>
        <p class="text-gray-600">Belum ada mata praktikum yang ditambahkan.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Nama Praktikum</th>
                        <th class="py-3 px-6 text-left">Deskripsi</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($mata_praktikum as $praktikum): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars(substr($praktikum['deskripsi'], 0, 100)) . (strlen($praktikum['deskripsi']) > 100 ? '...' : ''); ?></td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-2">
                                    <a href="kelola_mata_praktikum.php?action=edit&id=<?php echo $praktikum['id']; ?>"
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-xs">Edit</a>
                                    <a href="kelola_mata_praktikum.php?action=delete&id=<?php echo $praktikum['id']; ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus mata praktikum ini? Semua modul dan laporan terkait juga akan terhapus.');"
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
