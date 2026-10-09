<?php
// Mencegah error header jika ada output sebelumnya
if (ob_get_level() == 0) {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BengkelPro</title>
    <!-- Menggunakan Tailwind CSS via CDN untuk styling cepat dan responsif -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <!-- Wrapper Utama -->
    <div class="min-h-screen flex flex-col">
        
        <!-- Navbar -->
        <header class="bg-blue-600 text-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-xl font-bold">BengkelPro</h1>
                <span class="text-sm bg-blue-700 px-3 py-1 rounded-full">PHP <?= phpversion(); ?></span>
            </div>
        </header>

        <!-- Konten Utama -->
        <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Dashboard Manajemen Bengkel</h2>
                <p class="text-gray-600">Selamat datang di sistem pengelolaan BengkelPro.</p>
            </div>

            <!-- Grid Statistik Ringkas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-gray-500 text-sm font-medium">Servis Masuk</h3>
                    <p class="text-3xl font-bold text-gray-800 mt-2">0</p>
                    <span class="text-xs text-green-600 mt-1 inline-block">Status: Siap beroperasi</span>
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

            <!-- Panel Navigasi Fitur Mendatang -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Menu Utama</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button class="p-4 bg-gray-50 hover:bg-blue-50 border rounded-lg text-center transition">
                        <span class="block font-medium text-gray-700">Data Servis</span>
                    </button>
                    <button class="p-4 bg-gray-50 hover:bg-blue-50 border rounded-lg text-center transition">
                        <span class="block font-medium text-gray-700">Pelanggan</span>
                    </button>
                    <button class="p-4 bg-gray-50 hover:bg-blue-50 border rounded-lg text-center transition">
                        <span class="block font-medium text-gray-700">Inventaris</span>
                    </button>
                    <button class="p-4 bg-gray-50 hover:bg-blue-50 border rounded-lg text-center transition">
                        <span class="block font-medium text-gray-700">Laporan</span>
                    </button>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-4 text-center text-sm text-gray-500">
            BengkelPro &copy; 2026 - Powered by Vercel & PHP
        </footer>
    </div>

</body>
</html>
