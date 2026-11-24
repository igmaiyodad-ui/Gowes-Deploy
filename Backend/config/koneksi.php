<?php
$host   = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Sesuaikan dengan Host di panel Connect
$port   = '5432';
$dbname = 'postgres';
$user   = 'postgres.tksthsjgupcsdepftqab';            // Harus pakai .tksthsjgupcsdepftqab
$pass   = 'Gowes133122';             // Password database project Supabase

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Koneksi ke Supabase Berhasil!"; // Hapus atau beri // lagi setelah dites berhasil
} catch (\PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>