-- Tabel untuk pengguna (Mahasiswa dan Asisten)
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('mahasiswa','asisten') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk Mata Praktikum
CREATE TABLE `mata_praktikum` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nama_praktikum` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk Modul/Pertemuan dalam Mata Praktikum
CREATE TABLE `modul` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_praktikum` INT(11) NOT NULL,
  `nama_modul` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT,
  `file_materi` VARCHAR(255) NULL, -- Path ke file materi (PDF/DOCX)
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_praktikum`) REFERENCES `mata_praktikum`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk Pendaftaran Mahasiswa ke Mata Praktikum
CREATE TABLE `pendaftaran_praktikum` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` INT(11) NOT NULL,
  `id_praktikum` INT(11) NOT NULL,
  `status_pendaftaran` ENUM('terdaftar', 'dibatalkan') NOT NULL DEFAULT 'terdaftar',
  `tanggal_daftar` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pendaftaran` (`id_mahasiswa`, `id_praktikum`), -- Mencegah pendaftaran ganda
  FOREIGN KEY (`id_mahasiswa`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_praktikum`) REFERENCES `mata_praktikum`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk Laporan/Tugas yang Dikumpulkan Mahasiswa
CREATE TABLE `laporan` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_modul` INT(11) NOT NULL,
  `id_mahasiswa` INT(11) NOT NULL,
  `file_laporan` VARCHAR(255) NOT NULL, -- Path ke file laporan yang diunggah
  `nilai` INT(3) NULL, -- Nilai laporan (0-100)
  `feedback` TEXT NULL, -- Feedback dari asisten
  `status_laporan` ENUM('belum_dinilai', 'sudah_dinilai') NOT NULL DEFAULT 'belum_dinilai',
  `tanggal_pengumpulan` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `tanggal_penilaian` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_laporan_modul_mahasiswa` (`id_modul`, `id_mahasiswa`), -- Mencegah pengumpulan ganda per modul
  FOREIGN KEY (`id_modul`) REFERENCES `modul`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_mahasiswa`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
