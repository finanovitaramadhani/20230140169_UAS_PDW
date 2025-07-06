<?php
// asisten/kelola_pengguna.php
// Halaman ini memungkinkan asisten untuk mengelola (CRUD) akun pengguna.

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

$pageTitle = 'Kelola Akun Pengguna';
$activePage = 'pengguna'; // Untuk menandai navigasi aktif

$message = ''; // Untuk menampilkan pesan sukses atau error

// --- Logika CRUD ---

// 1. Tambah/Edit Pengguna
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_user'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (empty($nama) || empty($email) || empty($role)) {
        $message = '<p class="text-red-500">Nama, Email, dan Peran tidak boleh kosong!</p>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<p class="text-red-500">Format email tidak valid!</p>';
    } elseif (!in_array($role, ['mahasiswa', 'asisten'])) {
        $message = '<p class="text-red-500">Peran tidak valid!</p>';
    } else {
        // Cek apakah email sudah terdaftar (kecuali untuk user yang sedang diedit)
        $sql_check_email = "SELECT id FROM users WHERE email = ?";
        if ($user_id > 0) {
            $sql_check_email .= " AND id != ?";
        }
        $stmt_check_email = $conn->prepare($sql_check_email);
        if ($user_id > 0) {
            $stmt_check_email->bind_param("si", $email, $user_id);
        } else {
            $stmt_check_email->bind_param("s", $email);
        }
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_email->num_rows > 0) {
            $message = '<p class="text-red-500">Email sudah terdaftar. Silakan gunakan email lain.</p>';
        } else {
            $hashed_password = '';
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            }

            if ($user_id > 0) {
                // Update data
                if (!empty($hashed_password)) {
                    $sql = "UPDATE users SET nama = ?, email = ?, password = ?, role = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $nama, $email, $hashed_password, $role, $user_id);
                } else {
                    $sql = "UPDATE users SET nama = ?, email = ?, role = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $nama, $email, $role, $user_id);
                }
                
                if ($stmt->execute()) {
                    $message = '<p class="text-green-500">Pengguna berhasil diperbarui!</p>';
                } else {
                    $message = '<p class="text-red-500">Gagal memperbarui pengguna: ' . $conn->error . '</p>';
                }
                $stmt->close();
            } else {
                // Tambah data baru
                if (empty($password)) {
                    $message = '<p class="text-red-500">Password harus diisi untuk pengguna baru!</p>';
                } else {
                    $sql_insert = "INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssss", $nama, $email, $hashed_password, $role);
                    if ($stmt_insert->execute()) {
                        $message = '<p class="text-green-500">Pengguna baru berhasil ditambahkan!</p>';
                    } else {
                        $message = '<p class="text-red-500">Gagal menambahkan pengguna: ' . $conn->error . '</p>';
                    }
                    $stmt_insert->close();
                }
            }
        }
        $stmt_check_email->close();
    }
}

// 2. Hapus Pengguna
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);

    // Pastikan asisten tidak menghapus dirinya sendiri
    if ($id_to_delete == $_SESSION['user_id']) {
        $message = '<p class="text-red-500">Anda tidak bisa menghapus akun Anda sendiri!</p>';
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_to_delete);
        if ($stmt->execute()) {
            $message = '<p class="text-green-500">Pengguna berhasil dihapus!</p>';
        } else {
            $message = '<p class="text-red-500">Gagal menghapus pengguna: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
    // Redirect untuk menghindari resubmission form
    header("Location: kelola_pengguna.php?message=" . urlencode(strip_tags($message)));
    exit();
}

// Ambil data pengguna untuk ditampilkan
$users_list = [];
$sql_select = "SELECT id, nama, email, role FROM users ORDER BY role DESC, nama ASC";
$result_select = $conn->query($sql_select);
if ($result_select) {
    while ($row = $result_select->fetch_assoc()) {
        $users_list[] = $row;
    }
} else {
    $message = '<p class="text-red-500">Gagal mengambil data pengguna: ' . $conn->error . '</p>';
}

// Ambil pesan dari redirect (setelah delete)
if (isset($_GET['message'])) {
    $message = '<p class="text-green-500">' . htmlspecialchars($_GET['message']) . '</p>';
}

// Panggil header
require_once 'templates/header.php';
?>

<div class="container mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Akun Pengguna</h2>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-3 rounded-md bg-opacity-20 <?php echo strpos($message, 'red') !== false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah/Edit Pengguna -->
    <div class="mb-8 p-6 border border-gray-200 rounded-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">
            <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna Baru'; ?>
        </h3>
        <?php
        $edit_data = ['id' => '', 'nama' => '', 'email' => '', 'role' => 'mahasiswa'];
        if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
            $id_to_edit = intval($_GET['id']);
            $sql_edit = "SELECT id, nama, email, role FROM users WHERE id = ?";
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
        <form action="kelola_pengguna.php" method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_data['id']); ?>">
            <div class="mb-4">
                <label for="nama" class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap:</label>
                <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($edit_data['nama']); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_data['email']); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password (kosongkan jika tidak diubah):</label>
                <input type="password" id="password" name="password"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       <?php echo ($edit_data['id'] == '') ? 'required' : ''; // Password wajib untuk user baru ?>>
            </div>
            <div class="mb-6">
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Peran:</label>
                <select id="role" name="role"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    <option value="mahasiswa" <?php echo ($edit_data['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                    <option value="asisten" <?php echo ($edit_data['role'] == 'asisten') ? 'selected' : ''; ?>>Asisten</option>
                </select>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" name="submit_user"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Perbarui Pengguna' : 'Tambah Pengguna'; ?>
                </button>
                <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                    <a href="kelola_pengguna.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Pengguna -->
    <?php if (empty($users_list)): ?>
        <p class="text-gray-600">Belum ada pengguna yang terdaftar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Nama</th>
                        <th class="py-3 px-6 text-left">Email</th>
                        <th class="py-3 px-6 text-left">Peran</th>
                        <th class="py-3 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php foreach ($users_list as $user): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-3 px-6 text-left whitespace-nowrap"><?php echo htmlspecialchars($user['nama']); ?></td>
                            <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-3 px-6 text-left capitalize"><?php echo htmlspecialchars($user['role']); ?></td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-2">
                                    <a href="kelola_pengguna.php?action=edit&id=<?php echo $user['id']; ?>"
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded-md text-xs">Edit</a>
                                    <a href="kelola_pengguna.php?action=delete&id=<?php echo $user['id']; ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');"
                                       class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-xs
                                       <?php echo ($user['id'] == $_SESSION['user_id']) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                       <?php echo ($user['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>Hapus</a>
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
