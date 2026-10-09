<?php
// Menyembunyikan warning deprecated untuk PHP 8.5
error_reporting(E_ALL & ~E_DEPRECATED);

// Konfigurasi Supabase
$supabase_url = "https://fuoohcvwkeyticivjffl.supabase.co";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ1b29oY3Z3a2V5dGljaXZqZmZsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTE1NTczOTMsImV4cCI6MjEwNzEzMzM5M30.EYy1QbiOhXuFuiDxfRh8l_otFZYRyZP7_kc6q7YP9rw";

// Fungsi untuk mengambil data dari Supabase
function getServices($url, $key) {
    $ch = curl_init("$url/rest/v1/service?select=*");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key"
    ]);
    
    $response = curl_exec($ch);
    // curl_close tidak lagi wajib di PHP 8.5+
    return json_decode($response, true);
}

$services = getServices($supabase_url, $supabase_key);
$total_servis = is_array($services) ? count($services) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BengkelPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <div class="min-h-screen flex flex-col">
        <!-- Navbar -->
        <header class="bg-blue-600 text-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-xl font-bold">BengkelPro</h1>
                <span class="text-sm bg-blue-700 px-3 py-1 rounded-full">PHP <?= phpversion(); ?> &bull; Supabase Live</span>
            </div>
        </header>

        <!-- Konten Utama -->
        <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Dashboard Manajemen Bengkel</h2>
                <p class="text-gray-600">Sistem terintegrasi dengan database awan.</p>
            </div>

            <!-- Grid Statistik -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-gray-500 text-sm font-medium">Total Servis Masuk</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?= $total_servis; ?></p>
                    <span class="text-xs text-green-600 mt-1 inline-block">Status: Terhubung</span>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-gray-500 text-sm font-medium">Suku Cadang</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2">0</p>
                    <span class="text-xs text-blue-600 mt-1 inline-block">Inventaris kosong</span>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-gray-500 text-sm font-medium">Pendapatan Hari Ini</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2">Rp 0</p>
                    <span class="text-xs text-gray-500 mt-1 inline-block">Belum ada transaksi</span>
                </div>
            </div>

            <!-- Tabel Data Servis -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Servis Terbaru</h3>
                <?php if (empty($services)): ?>
                    <p class="text-gray-500 text-sm">Belum ada data di tabel Supabase.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-gray-500 font-medium">
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">Data / Kolom Tersedia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($services as $row): ?>
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-gray-700">#<?= htmlspecialchars($row['id'] ?? ''); ?></td>
                                    <td class="px-4 py-2 text-gray-600">
                                        <?php 
                                            // Menampilkan seluruh kolom dinamis yang ada di Supabase Anda
                                            $details = [];
                                            foreach($row as $key => $val) {
                                                if($key !== 'id') {
                                                    $details[] = "<b>$key:</b> " . htmlspecialchars($val ?? '-');
                                                }
                                            }
                                            echo implode(' | ', $details);
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer class="bg-white border-t border-gray-200 py-4 text-center text-sm text-gray-500">
            BengkelPro &copy; 2026 - Vercel PHP & Supabase
        </footer>
    </div>

</body>
</html>
