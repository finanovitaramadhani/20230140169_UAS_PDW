<?php
// mahasiswa/mata_praktikum.php
// Halaman katalog yang menampilkan semua mata praktikum yang tersedia.
// Mahasiswa dapat mendaftar ke praktikum dari halaman ini.

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file konfigurasi database
require_once '../config.php';

// Cek jika pengguna belum login atau bukan mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Katalog Mata Praktikum';
$activePage = 'katalog'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

$user_id = $_SESSION['user_id'];

// --- Logika Pendaftaran Praktikum ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['daftar_praktikum'])) {
    $id_praktikum_to_register = intval($_POST['id_praktikum']);

    // Cek apakah mahasiswa sudah terdaftar di praktikum ini
    $sql_check = "SELECT id FROM pendaftaran_praktikum WHERE id_mahasiswa = ? AND id_praktikum = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $user_id, $id_praktikum_to_register);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $message = '<p class="text-red-500">Anda sudah terdaftar di praktikum ini!</p>';
    } else {
        // Tambah pendaftaran baru
        $sql_insert = "INSERT INTO pendaftaran_praktikum (id_mahasiswa, id_praktikum, status_pendaftaran) VALUES (?, ?, 'terdaftar')";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ii", $user_id, $id_praktikum_to_register);
        if ($stmt_insert->execute()) {
            $message = '<p class="text-green-500">Berhasil mendaftar ke praktikum!</p>';
        } else {
            $message = '<p class="text-red-500">Gagal mendaftar ke praktikum: ' . $conn->error . '</p>';
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

// Ambil semua mata praktikum yang tersedia
$mata_praktikum_list = [];
$sql_select = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum ORDER BY nama_praktikum ASC";
$result_select = $conn->query($sql_select);
if ($result_select) {
    while ($row = $result_select->fetch_assoc()) {
        $mata_praktikum_list[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data mata praktikum: ' . $conn->error . '</p>';
}

// Ambil daftar praktikum yang sudah diikuti mahasiswa untuk menandai
$praktikum_diikuti = [];
$sql_diikuti = "SELECT id_praktikum FROM pendaftaran_praktikum WHERE id_mahasiswa = ?";
$stmt_diikuti = $conn->prepare($sql_diikuti);
$stmt_diikuti->bind_param("i", $user_id);
$stmt_diikuti->execute();
$result_diikuti = $stmt_diikuti->get_result();
if ($result_diikuti) {
    while ($row = $result_diikuti->fetch_assoc()) {
        $praktikum_diikuti[] = $row['id_praktikum'];
    }
}
$stmt_diikuti->close();

// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Katalog Mata Praktikum</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($mata_praktikum_list)): ?>
        <p class="text-gray-600">Belum ada mata praktikum yang tersedia saat ini.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($mata_praktikum_list as $praktikum): ?>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h3>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($praktikum['deskripsi']); ?></p>
                    </div>
                    <div class="mt-4">
                        <?php if (in_array($praktikum['id'], $praktikum_diikuti)): ?>
                            <span class="inline-block bg-green-200 text-green-800 text-xs px-3 py-1 rounded-full font-semibold">Sudah Terdaftar</span>
                            <a href="praktikum_saya.php" class="ml-2 bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md text-sm transition-colors duration-200">Lihat Praktikum Saya</a>
                        <?php else: ?>
                            <form action="mata_praktikum.php" method="POST">
                                <input type="hidden" name="id_praktikum" value="<?php echo htmlspecialchars($praktikum['id']); ?>">
                                <button type="submit" name="daftar_praktikum"
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md text-sm transition-colors duration-200">
                                    Daftar Praktikum
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Panggil footer
require_once 'templates/footer.php';
$conn->close();
?>
