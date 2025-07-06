<?php
// mahasiswa/praktikum_saya.php
// Halaman personal yang hanya menampilkan daftar mata praktikum yang sudah diikuti oleh mahasiswa tersebut.

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

$pageTitle = 'Praktikum Saya';
$activePage = 'praktikum_saya'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

$user_id = $_SESSION['user_id'];

// Ambil daftar praktikum yang diikuti oleh mahasiswa ini
$praktikum_diikuti_list = [];
$sql_select = "SELECT 
                    mp.id AS praktikum_id, 
                    mp.nama_praktikum, 
                    mp.deskripsi,
                    pp.tanggal_daftar
                FROM pendaftaran_praktikum pp
                JOIN mata_praktikum mp ON pp.id_praktikum = mp.id
                WHERE pp.id_mahasiswa = ? AND pp.status_pendaftaran = 'terdaftar'
                ORDER BY pp.tanggal_daftar DESC";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $user_id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();

if ($result_select) {
    while ($row = $result_select->fetch_assoc()) {
        $praktikum_diikuti_list[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data praktikum Anda: ' . $conn->error . '</p>';
}
$stmt_select->close();

// Panggil header
require_once 'templates/header.php'; // Baris ini yang penting untuk diperiksa!
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Praktikum yang Saya Ikuti</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($praktikum_diikuti_list)): ?>
        <p class="text-gray-600">Anda belum mengikuti praktikum apapun. Silakan <a href="mata_praktikum.php" class="text-blue-500 hover:underline">cari mata praktikum</a> untuk mendaftar.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($praktikum_diikuti_list as $praktikum): ?>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h3>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($praktikum['deskripsi']); ?></p>
                        <p class="text-xs text-gray-500">Terdaftar sejak: <?php echo date('d M Y', strtotime($praktikum['tanggal_daftar'])); ?></p>
                    </div>
                    <div class="mt-4">
                        <a href="detail_praktikum.php?id=<?php echo htmlspecialchars($praktikum['praktikum_id']); ?>"
                           class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md text-sm transition-colors duration-200">
                            Lihat Detail & Tugas
                        </a>
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
