<?php
// asisten/laporan_masuk.php
// Halaman ini menampilkan daftar laporan yang telah dikumpulkan mahasiswa
// dan memungkinkan asisten untuk melihat detail serta memberi nilai.

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

$pageTitle = 'Laporan Masuk';
$activePage = 'laporan'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

// Direktori tempat menyimpan file laporan
$upload_dir = '../uploads/laporan_tugas/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
}

// --- Logika Memberi Nilai Laporan ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai'])) {
    $laporan_id = intval($_POST['laporan_id']);
    $nilai = trim($_POST['nilai']);
    $feedback = trim($_POST['feedback']);

    // Validasi nilai
    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) {
        $message = '<p class="text-red-500">Nilai harus angka antara 0-100!</p>';
    } else {
        $sql = "UPDATE laporan SET nilai = ?, feedback = ?, status_laporan = 'sudah_dinilai', tanggal_penilaian = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $nilai, $feedback, $laporan_id);
        if ($stmt->execute()) {
            $message = '<p class="text-green-500">Nilai laporan berhasil disimpan!</p>';
        } else {
            $message = '<p class="text-red-500">Gagal menyimpan nilai laporan: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
}

// --- Logika Filter ---
$filter_modul_id = isset($_GET['filter_modul']) ? intval($_GET['filter_modul']) : '';
$filter_mahasiswa_id = isset($_GET['filter_mahasiswa']) ? intval($_GET['filter_mahasiswa']) : '';
$filter_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_modul_id)) {
    $where_clauses[] = "l.id_modul = ?";
    $params[] = $filter_modul_id;
    $param_types .= 'i';
}
if (!empty($filter_mahasiswa_id)) {
    $where_clauses[] = "l.id_mahasiswa = ?";
    $params[] = $filter_mahasiswa_id;
    $param_types .= 'i';
}
if (!empty($filter_status)) {
    $where_clauses[] = "l.status_laporan = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$sql_where = '';
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// Ambil data laporan
$laporan_list = [];
$sql_laporan = "SELECT 
                    l.id, l.file_laporan, l.nilai, l.feedback, l.status_laporan, l.tanggal_pengumpulan,
                    u.nama AS nama_mahasiswa, u.email AS email_mahasiswa,
                    m.nama_modul,
                    mp.nama_praktikum
                FROM laporan l
                JOIN users u ON l.id_mahasiswa = u.id
                JOIN modul m ON l.id_modul = m.id
                JOIN mata_praktikum mp ON m.id_praktikum = mp.id" . $sql_where . "
                ORDER BY l.tanggal_pengumpulan DESC";

$stmt_laporan = $conn->prepare($sql_laporan);
if (!empty($params)) {
    $stmt_laporan->bind_param($param_types, ...$params);
}
$stmt_laporan->execute();
$result_laporan = $stmt_laporan->get_result();

if ($result_laporan) {
    while ($row = $result_laporan->fetch_assoc()) {
        $laporan_list[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data laporan: ' . $conn->error . '</p>';
}
$stmt_laporan->close();

// Ambil daftar modul untuk filter
$modul_filter_list = [];
$sql_modul_filter = "SELECT id, nama_modul FROM modul ORDER BY nama_modul ASC";
$result_modul_filter = $conn->query($sql_modul_filter);
if ($result_modul_filter) {
    while ($row = $result_modul_filter->fetch_assoc()) {
        $modul_filter_list[] = $row;
    }
}

// Ambil daftar mahasiswa untuk filter
$mahasiswa_filter_list = [];
$sql_mahasiswa_filter = "SELECT id, nama FROM users WHERE role = 'mahasiswa' ORDER BY nama ASC";
$result_mahasiswa_filter = $conn->query($sql_mahasiswa_filter);
if ($result_mahasiswa_filter) {
    while ($row = $result_mahasiswa_filter->fetch_assoc()) {
        $mahasiswa_filter_list[] = $row;
    }
}

// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Laporan Masuk</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-700 mb-3">Filter Laporan</h3>
        <form action="laporan_masuk.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="filter_modul" class="block text-gray-700 text-sm font-bold mb-2">Modul:</label>
                <select id="filter_modul" name="filter_modul" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modul_filter_list as $modul): ?>
                        <option value="<?php echo htmlspecialchars($modul['id']); ?>" <?php echo ($filter_modul_id == $modul['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($modul['nama_modul']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_mahasiswa" class="block text-gray-700 text-sm font-bold mb-2">Mahasiswa:</label>
                <select id="filter_mahasiswa" name="filter_mahasiswa" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Mahasiswa</option>
                    <?php foreach ($mahasiswa_filter_list as $mahasiswa): ?>
                        <option value="<?php echo htmlspecialchars($mahasiswa['id']); ?>" <?php echo ($filter_mahasiswa_id == $mahasiswa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mahasiswa['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status" class="block text-gray-700 text-sm font-bold mb-2">Status:</label>
                <select id="filter_status" name="filter_status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Semua Status</option>
                    <option value="belum_dinilai" <?php echo ($filter_status == 'belum_dinilai') ? 'selected' : ''; ?>>Belum Dinilai</option>
                    <option value="sudah_dinilai" <?php echo ($filter_status == 'sudah_dinilai') ? 'selected' : ''; ?>>Sudah Dinilai</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end mt-4">
                <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Terapkan Filter
                </button>
                <a href="laporan_masuk.php" class="ml-2 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Reset Filter</a>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Laporan -->
    <?php if (empty($laporan_list)): ?>
        <p class="text-gray-600">Belum ada laporan yang masuk.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Mahasiswa</th>
                        <th class="py-3 px-6 text-left">Mata Praktikum</th>
                        <th class="py-3 px-6 text-left">Modul</th>
                        <th class="py-3 px-6 text-center">Tanggal Pengumpulan</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-center">Nilai</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($laporan_list as $laporan): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 text-left">
                                <?php echo htmlspecialchars($laporan['nama_mahasiswa']); ?><br>
                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($laporan['email_mahasiswa']); ?></span>
                            </td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($laporan['nama_praktikum']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($laporan['nama_modul']); ?></td>
                            <td class="py-3 px-6 text-center"><?php echo date('d M Y H:i', strtotime($laporan['tanggal_pengumpulan'])); ?></td>
                            <td class="py-3 px-6 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                    <?php echo ($laporan['status_laporan'] == 'belum_dinilai') ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800'; ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($laporan['status_laporan'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <?php echo !is_null($laporan['nilai']) ? htmlspecialchars($laporan['nilai']) : '-'; ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-2">
                                    <a href="../uploads/laporan_tugas/<?php echo htmlspecialchars($laporan['file_laporan']); ?>" target="_blank"
                                       class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md text-xs">Unduh Laporan</a>
                                    
                                    <!-- Tombol untuk membuka modal penilaian -->
                                    <button onclick="openModal(<?php echo htmlspecialchars(json_encode($laporan)); ?>)"
                                            class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-xs">
                                        <?php echo ($laporan['status_laporan'] == 'belum_dinilai') ? 'Beri Nilai' : 'Lihat/Edit Nilai'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Modal untuk Memberi Nilai Laporan -->
    <div id="nilaiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Beri Nilai Laporan</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
            </div>
            <form id="nilaiForm" action="laporan_masuk.php" method="POST">
                <input type="hidden" name="laporan_id" id="modal_laporan_id">
                <div class="mb-4">
                    <label for="modal_nama_mahasiswa" class="block text-gray-700 text-sm font-bold mb-2">Mahasiswa:</label>
                    <input type="text" id="modal_nama_mahasiswa" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" readonly>
                </div>
                <div class="mb-4">
                    <label for="modal_nama_modul" class="block text-gray-700 text-sm font-bold mb-2">Modul:</label>
                    <input type="text" id="modal_nama_modul" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" readonly>
                </div>
                <div class="mb-4">
                    <label for="nilai" class="block text-gray-700 text-sm font-bold mb-2">Nilai (0-100):</label>
                    <input type="number" id="nilai" name="nilai" min="0" max="100" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-6">
                    <label for="feedback" class="block text-gray-700 text-sm font-bold mb-2">Feedback:</label>
                    <textarea id="feedback" name="feedback" rows="4"
                              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" name="submit_nilai"
                            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Simpan Nilai
                    </button>
                    <button type="button" onclick="closeModal()"
                            class="ml-2 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function openModal(laporanData) {
        document.getElementById('modal_laporan_id').value = laporanData.id;
        document.getElementById('modal_nama_mahasiswa').value = laporanData.nama_mahasiswa;
        document.getElementById('modal_nama_modul').value = laporanData.nama_modul;
        document.getElementById('nilai').value = laporanData.nilai || ''; // Set nilai jika sudah ada
        document.getElementById('feedback').value = laporanData.feedback || ''; // Set feedback jika sudah ada
        document.getElementById('nilaiModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('nilaiModal').classList.add('hidden');
        document.getElementById('nilaiForm').reset(); // Reset form saat ditutup
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('nilaiModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php
// Panggil footer
require_once 'templates/footer.php';
$conn->close();
?>
