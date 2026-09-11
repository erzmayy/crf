<?php
/**
 * Seeder akun development (staff & admin).
 * Jalankan lewat browser: http://localhost/crf/database/seed_users.php
 * HANYA SEKALI, lalu sebaiknya hapus atau kunci file ini.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

$users = [
    [
        'nama' => 'Damayanti Diah P',
        'email' => 'staff@ppu.co.id',
        'password' => 'password123',
        'role' => 'staff',
        'departemen' => 'Information Technology',
        'jabatan' => 'Magang',
        'nomor_hp' => '083882126458',
    ],
    [
        'nama' => 'Admin CAB',
        'email' => 'admin@ppu.co.id',
        'password' => 'password123',
        'role' => 'admin',
        'departemen' => 'IT Change Advisory Board',
        'jabatan' => 'Admin CAB',
        'nomor_hp' => '081200000000',
    ],
];

$insert = $pdo->prepare(
    'INSERT INTO users (nama, email, password, role, departemen, jabatan, nomor_hp) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');

foreach ($users as $u) {
    $check->execute([$u['email']]);
    if ($check->fetch()) {
        echo "Lewati: {$u['email']} sudah ada di database.\n";
        continue;
    }

    $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
    $insert->execute([$u['nama'], $u['email'], $hashed, $u['role'], $u['departemen'], $u['jabatan'], $u['nomor_hp']]);
    echo "Berhasil membuat user: {$u['email']} (role: {$u['role']})\n";
}

echo "\nSelesai. Silakan login di /auth/login.php\n";
echo "PENTING: hapus atau amankan file seed_users.php ini setelah dipakai.\n";
