<?php
// mahasiswa/detail_praktikum.php
// Halaman ini menampilkan detail mata praktikum, daftar modul,
// link unduh materi, form pengumpulan laporan, dan nilai laporan.

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

$pageTitle = 'Detail Praktikum';
$activePage = 'praktikum_saya'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

$user_id = $_SESSION['user_id'];
$praktikum_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Direktori tempat menyimpan file laporan
$upload_dir = '../uploads/laporan_tugas/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
}

// Pastikan praktikum_id valid dan mahasiswa terdaftar di praktikum ini
if ($praktikum_id === 0) {
    header("Location: praktikum_saya.php");
    exit();
}

$sql_check_enrollment = "SELECT COUNT(*) AS count FROM pendaftaran_praktikum WHERE id_mahasiswa = ? AND id_praktikum = ? AND status_pendaftaran = 'terdaftar'";
$stmt_check_enrollment = $conn->prepare($sql_check_enrollment);
$stmt_check_enrollment->bind_param("ii", $user_id, $praktikum_id);
$stmt_check_enrollment->execute();
$result_check_enrollment = $stmt_check_enrollment->get_result();
$row_check_enrollment = $result_check_enrollment->fetch_assoc();
if ($row_check_enrollment['count'] == 0) {
    $message = '<p class="text-red-500">Anda tidak terdaftar di praktikum ini atau praktikum tidak ditemukan.</p>';
    // Atau redirect ke halaman praktikum saya jika tidak terdaftar
    // header("Location: praktikum_saya.php?message=" . urlencode(strip_tags($message)));
    // exit();
}
$stmt_check_enrollment->close();

// Ambil detail mata praktikum
$praktikum_detail = null;
$sql_praktikum = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum WHERE id = ?";
$stmt_praktikum = $conn->prepare($sql_praktikum);
$stmt_praktikum->bind_param("i", $praktikum_id);
$stmt_praktikum->execute();
$result_praktikum = $stmt_praktikum->get_result();
if ($result_praktikum->num_rows > 0) {
    $praktikum_detail = $result_praktikum->fetch_assoc();
}
$stmt_praktikum->close();

// Jika praktikum tidak ditemukan, redirect
if (!$praktikum_detail) {
    header("Location: praktikum_saya.php");
    exit();
}

// --- Logika Pengumpulan Laporan ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_laporan'])) {
    $id_modul_laporan = intval($_POST['id_modul']);

    // Cek apakah file diunggah
    if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['file_laporan']['tmp_name'];
        $file_name = basename($_FILES['file_laporan']['name']); // Keep original file name for display
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'docx', 'zip']; // Izinkan PDF, DOCX, ZIP

        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('laporan_') . '_' . $user_id . '_' . $id_modul_laporan . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            // Cek apakah sudah ada laporan untuk modul ini dari mahasiswa ini
            $sql_check_laporan = "SELECT id, file_laporan FROM laporan WHERE id_modul = ? AND id_mahasiswa = ?";
            $stmt_check_laporan = $conn->prepare($sql_check_laporan);
            $stmt_check_laporan->bind_param("ii", $id_modul_laporan, $user_id);
            $stmt_check_laporan->execute();
            $result_check_laporan = $stmt_check_laporan->get_result();

            if ($result_check_laporan->num_rows > 0) {
                // Update laporan yang sudah ada
                $existing_laporan = $result_check_laporan->fetch_assoc();
                $old_file = $existing_laporan['file_laporan'];

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    // Hapus file lama jika ada
                    if (!empty($old_file) && file_exists($upload_dir . $old_file)) {
                        unlink($upload_dir . $old_file);
                    }
                    $sql_update = "UPDATE laporan SET file_laporan = ?, status_laporan = 'belum_dinilai', nilai = NULL, feedback = NULL, tanggal_pengumpulan = NOW(), tanggal_penilaian = NULL WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $new_file_name, $existing_laporan['id']);
                    if ($stmt_update->execute()) {
                        $message = '<p class="text-green-500">Laporan berhasil diperbarui!</p>';
                    } else {
                        $message = '<p class="text-red-500">Gagal memperbarui laporan: ' . $conn->error . '</p>';
                    }
                    $stmt_update->close();
                } else {
                    $message = '<p class="text-red-500">Gagal mengunggah file laporan.</p>';
                }
            } else {
                // Tambah laporan baru
                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $sql_insert = "INSERT INTO laporan (id_modul, id_mahasiswa, file_laporan, status_laporan) VALUES (?, ?, ?, 'belum_dinilai')";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("iis", $id_modul_laporan, $user_id, $new_file_name);
                    if ($stmt_insert->execute()) {
                        $message = '<p class="text-green-500">Laporan berhasil dikumpulkan!</p>';
                    } else {
                        $message = '<p class="text-red-500">Gagal mengumpulkan laporan: ' . $conn->error . '</p>';
                    }
                    $stmt_insert->close();
                } else {
                    $message = '<p class="text-red-500">Gagal mengunggah file laporan.</p>';
                }
            }
            $stmt_check_laporan->close();
        } else {
            $message = '<p class="text-red-500">Format file tidak diizinkan. Hanya PDF, DOCX, atau ZIP.</p>';
        }
    } else {
        $message = '<p class="text-red-500">Pilih file laporan untuk diunggah.</p>';
    }
}

// Ambil daftar modul dan laporan untuk praktikum ini
$modul_dan_laporan = [];
$sql_modul_laporan = "SELECT 
                        m.id AS modul_id, 
                        m.nama_modul, 
                        m.deskripsi AS modul_deskripsi, 
                        m.file_materi,
                        l.id AS laporan_id,
                        l.file_laporan, 
                        l.nilai, 
                        l.feedback, 
                        l.status_laporan, 
                        l.tanggal_pengumpulan
                    FROM modul m
                    LEFT JOIN laporan l ON m.id = l.id_modul AND l.id_mahasiswa = ?
                    WHERE m.id_praktikum = ?
                    ORDER BY m.nama_modul ASC";
$stmt_modul_laporan = $conn->prepare($sql_modul_laporan);
$stmt_modul_laporan->bind_param("ii", $user_id, $praktikum_id);
$stmt_modul_laporan->execute();
$result_modul_laporan = $stmt_modul_laporan->get_result();

if ($result_modul_laporan) {
    while ($row = $result_modul_laporan->fetch_assoc()) {
        $modul_dan_laporan[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data modul dan laporan: ' . $conn->error . '</p>';
}
$stmt_modul_laporan->close();


// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Detail Praktikum: <?php echo htmlspecialchars($praktikum_detail['nama_praktikum']); ?></h2>
    <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($praktikum_detail['deskripsi']); ?></p>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <h3 class="text-xl font-bold text-gray-800 mb-4">Daftar Modul & Pengumpulan Laporan</h3>

    <?php if (empty($modul_dan_laporan)): ?>
        <p class="text-gray-600">Belum ada modul yang tersedia untuk praktikum ini.</p>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($modul_dan_laporan as $item): ?>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Modul: <?php echo htmlspecialchars($item['nama_modul']); ?></h4>
                    <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($item['modul_deskripsi']); ?></p>

                    <!-- Unduh Materi -->
                    <div class="mb-3">
                        <span class="font-medium text-gray-700">Materi Modul:</span>
                        <?php if (!empty($item['file_materi'])): ?>
                            <a href="../uploads/materi_modul/<?php echo htmlspecialchars($item['file_materi']); ?>" target="_blank" class="text-blue-500 hover:underline ml-2">Unduh Materi</a>
                        <?php else: ?>
                            <span class="text-gray-500 ml-2">Tidak ada materi.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Status Laporan & Nilai -->
                    <div class="mb-4">
                        <span class="font-medium text-gray-700">Status Laporan Anda:</span>
                        <?php if (!empty($item['laporan_id'])): ?>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold ml-2
                                <?php echo ($item['status_laporan'] == 'belum_dinilai') ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800'; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($item['status_laporan'])); ?>
                            </span>
                            <?php if ($item['status_laporan'] == 'sudah_dinilai'): ?>
                                <span class="ml-4 font-medium text-gray-700">Nilai:</span>
                                <span class="text-lg font-bold text-indigo-700 ml-2"><?php echo htmlspecialchars($item['nilai']); ?></span>
                                <p class="text-sm text-gray-600 mt-1">Feedback: <?php echo !empty($item['feedback']) ? htmlspecialchars($item['feedback']) : '-'; ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1">Terakhir dikumpulkan: <?php echo date('d M Y H:i', strtotime($item['tanggal_pengumpulan'])); ?></p>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-800 ml-2">Belum Dikumpulkan</span>
                        <?php endif; ?>
                    </div>

                    <!-- Form Pengumpulan Laporan -->
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h5 class="text-md font-semibold text-gray-700 mb-3">Kumpulkan Laporan untuk Modul Ini:</h5>
                        <form action="detail_praktikum.php?id=<?php echo htmlspecialchars($praktikum_id); ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_modul" value="<?php echo htmlspecialchars($item['modul_id']); ?>">
                            <div class="mb-4">
                                <label for="file_laporan_<?php echo $item['modul_id']; ?>" class="block text-gray-700 text-sm font-bold mb-2">Pilih File Laporan (PDF/DOCX/ZIP):</label>
                                <input type="file" id="file_laporan_<?php echo $item['modul_id']; ?>" name="file_laporan" required
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            </div>
                            <button type="submit" name="submit_laporan"
                                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm">
                                <?php echo (!empty($item['laporan_id'])) ? 'Perbarui Laporan' : 'Kumpulkan Laporan'; ?>
                            </button>
                        </form>
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
