<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

// Fail closed: the workshop dashboard is private and must never expose customer data.
$admin_user = getenv('BENGKELPRO_ADMIN_USER') ?: '';
$admin_password = getenv('BENGKELPRO_ADMIN_PASSWORD') ?: '';
$supabase_url = rtrim(getenv('SUPABASE_URL') ?: '', '/');
$supabase_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';

if ($admin_user === '' || $admin_password === '' || $supabase_url === '' || $supabase_key === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Dashboard belum dikonfigurasi dengan aman.');
}

$provided_user = $_SERVER['PHP_AUTH_USER'] ?? '';
$provided_password = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!hash_equals($admin_user, $provided_user) || !hash_equals($admin_password, $provided_password)) {
    header('WWW-Authenticate: Basic realm="BengkelPro Admin", charset="UTF-8"');
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Autentikasi diperlukan.');
}

$services = [];
$service_error = null;

if (function_exists('curl_init')) {
    $ch = curl_init($supabase_url . '/rest/v1/service?select=id&order=id.desc&limit=8');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $service_error = 'Database belum dapat dihubungi.';
    } elseif ($http_code >= 200 && $http_code < 300) {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $services = $decoded;
        } else {
            $service_error = 'Respons database tidak dapat dibaca.';
        }
    } else {
        $service_error = 'Data servis belum tersedia. Periksa konfigurasi Supabase.';
    }
    curl_close($ch);
} else {
    $service_error = 'Ekstensi koneksi database tidak tersedia.';
}

$total_servis = count($services);
function e(mixed $value): string {
    if (is_scalar($value) || $value === null) {
        return htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f7fb">
  <title>BengkelPro · Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root{--ink:#24243b;--muted:#8a8aa3;--line:#eeedf5;--purple:#7866e8;--purple-soft:#f0edff;--canvas:#f8f8fc}
    *{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font-family:'DM Sans',sans-serif;font-size:14px}
    h1,h2,h3,h4,.brand{font-family:'Manrope',sans-serif;letter-spacing:-.035em}
    .app{min-height:100vh}.sidebar{width:236px;background:#fff;border-right:1px solid var(--line);position:fixed;inset:0 auto 0 0;padding:25px 16px;display:flex;flex-direction:column;z-index:10}
    .brand{font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px;padding:0 12px 32px}.brand-icon{width:34px;height:34px;border-radius:12px;background:var(--purple);color:white;display:grid;place-items:center}
    .nav-caption{font-size:10px;text-transform:uppercase;letter-spacing:.13em;color:#aaa8bd;font-weight:700;padding:0 12px;margin:12px 0 9px}
    .side-link{display:flex;align-items:center;gap:12px;padding:11px 12px;margin:2px 0;border-radius:11px;color:#77778f;text-decoration:none;font-weight:600}
    .side-link i{font-size:17px;width:20px}.side-link.active{background:var(--purple-soft);color:var(--purple)}.side-link .count{margin-left:auto;background:#fff;border:1px solid #e9e5ff;padding:2px 7px;border-radius:8px;font-size:11px}
    .sidebar-bottom{margin-top:auto;background:#f7f5ff;border-radius:15px;padding:14px}.sidebar-bottom .small{font-size:12px;color:#77738f;line-height:1.5}
    .main{margin-left:236px;padding:26px 30px 36px;max-width:1700px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:30px}
    .crumb{color:var(--muted);font-size:12px;margin-bottom:5px}.page-title{font-size:25px;font-weight:800;margin:0}.top-actions{display:flex;align-items:center;gap:10px}.icon-btn{height:39px;width:39px;border:1px solid var(--line);background:#fff;border-radius:12px;color:#77778f;display:grid;place-items:center}.avatar{width:38px;height:38px;border-radius:13px;background:#e9e3ff;color:#6552d5;display:grid;place-items:center;font-weight:800}
    .welcome{background:linear-gradient(112deg,#7764e6,#9586f2);border-radius:19px;color:#fff;padding:24px 27px;position:relative;overflow:hidden;margin-bottom:22px}.welcome:after{content:'';position:absolute;width:230px;height:230px;border:38px solid #ffffff12;border-radius:50%;right:5%;top:-100px}.welcome h2{font-size:21px;font-weight:800;margin-bottom:8px}.welcome p{color:#e9e5ff;margin:0;max-width:600px}.welcome .welcome-icon{position:absolute;right:9%;bottom:-18px;font-size:105px;color:#ffffff24;transform:rotate(-15deg)}
    .btn-light-purple{background:#fff;color:var(--purple);border:0;border-radius:10px;padding:10px 14px;font-weight:700;position:relative;z-index:1}.section-head{display:flex;justify-content:space-between;align-items:center;margin:25px 0 14px}.section-head h3{font-size:16px;font-weight:800;margin:0}.muted{color:var(--muted)}.stat-card,.panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:19px;height:100%}.stat-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.stat-icon{width:39px;height:39px;display:grid;place-items:center;border-radius:12px;background:var(--purple-soft);color:var(--purple);font-size:18px}.stat-label{font-size:12px;color:var(--muted);font-weight:600}.stat-value{font-family:'Manrope',sans-serif;font-size:27px;font-weight:800;letter-spacing:-.05em}.stat-foot{font-size:11px;color:var(--muted);margin-top:5px}.pill{display:inline-flex;align-items:center;gap:5px;border-radius:8px;padding:5px 8px;font-size:11px;font-weight:700;background:#f1efff;color:#7866e8}.pill.green{background:#e9f8f0;color:#269667}.pill.orange{background:#fff4e5;color:#c27a20}.pill.gray{background:#f1f1f6;color:#77778f}
    .panel-title{font-weight:800;font-size:15px;margin:0}.panel-sub{font-size:12px;color:var(--muted);margin-top:5px}.service-row{display:flex;align-items:center;gap:11px;padding:13px 0;border-bottom:1px solid #f2f1f7}.service-row:last-child{border-bottom:0}.service-avatar{width:38px;height:38px;border-radius:12px;background:#f1efff;color:#7764e6;display:grid;place-items:center;font-size:17px;flex-shrink:0}.service-name{font-weight:700;font-size:12px;max-width:210px;overflow-wrap:anywhere}.service-meta{font-size:11px;color:var(--muted);margin-top:4px}.service-data{font-size:11px;color:#77778f;overflow-wrap:anywhere}
    .channel-card{display:flex;align-items:center;gap:13px;border:1px solid var(--line);border-radius:13px;padding:14px;margin-top:11px}.channel-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:20px}.channel-icon.telegram{background:#e8f5ff;color:#229ed9}.channel-icon.whatsapp{background:#e6f8ed;color:#1d9d62}.channel-title{font-size:12px;font-weight:800}.channel-note{font-size:11px;color:var(--muted);margin-top:3px}.channel-status{margin-left:auto;font-size:10px;color:#8a8aa3;background:#f3f3f7;padding:5px 7px;border-radius:7px;white-space:nowrap}
    .table{--bs-table-bg:transparent;margin:12px 0 0}.table th{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#a09fb3;font-weight:700;border-bottom-color:var(--line);padding:12px 10px}.table td{font-size:12px;padding:14px 10px;border-bottom-color:#f3f2f7;vertical-align:middle}.empty-state{text-align:center;padding:28px 10px;color:var(--muted);font-size:12px}.footer-note{font-size:11px;color:#aaa8bd;margin-top:22px}
    .mobile-menu-btn{display:none}
    @media(max-width:991px) and (min-width:768px){.sidebar{width:70px;padding:22px 9px}.brand{padding:0 9px 28px}.brand-name,.nav-caption,.side-link span,.sidebar-bottom{display:none}.side-link{justify-content:center;padding:12px 0}.side-link i{width:auto}.main{margin-left:70px;padding:22px 18px}}
    @media(max-width:767px){.sidebar{width:100%;max-width:none;height:min(78dvh,680px);inset:auto 0 0 0;padding:24px 18px calc(24px + env(safe-area-inset-bottom));border-right:0;border-top:1px solid var(--line);border-radius:22px 22px 0 0;transform:translateY(105%);transition:transform .28s ease;box-shadow:0 -12px 35px #24243b18;overflow-y:auto}.sidebar.mobile-open{transform:translateY(0)}.sidebar:before{content:'';display:block;width:38px;height:4px;border-radius:9px;background:#d9d7e5;margin:-10px auto 22px}.brand{padding:0 12px 24px}.brand-name,.nav-caption,.side-link span,.sidebar-bottom{display:revert}.side-link{justify-content:flex-start;padding:11px 12px}.side-link i{width:20px}.main{margin-left:0;padding:18px 13px 80px}.mobile-menu-btn{display:grid;position:fixed;right:20px;bottom:calc(20px + env(safe-area-inset-bottom));width:54px;height:54px;border:0;background:var(--purple);border-radius:18px;color:#fff;place-items:center;font-size:24px;box-shadow:0 8px 24px #7866e84d;z-index:8}.topbar{position:relative;min-height:42px;align-items:center;margin-bottom:22px}.topbar-heading{padding-right:8px}.page-title{font-size:21px}.top-actions{margin-left:auto}.top-actions .hide-mobile{display:none}.welcome{padding:21px}.welcome .welcome-icon{right:-12px}.stat-value{font-size:24px}.sidebar-backdrop{display:none;position:fixed;inset:0;background:#17162d66;z-index:9}.sidebar-backdrop.show{display:block}}
    @media(prefers-reduced-motion:reduce){.sidebar{transition:none}}
  </style>
</head>
<body>
<div class="app">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="sidebar" id="appSidebar">
    <div class="brand"><span class="brand-icon"><i class="bi bi-wrench-adjustable-circle"></i></span><span class="brand-name">Bengkel<span style="color:var(--purple)">Pro</span></span></div>
    <div class="nav-caption">Workspace</div>
    <a class="side-link active" href="#dashboard"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
    <a class="side-link" href="#booking"><i class="bi bi-calendar2-check"></i><span>Booking Servis</span><span class="count"><?= $total_servis ?></span></a>
    <a class="side-link" href="#services"><i class="bi bi-tools"></i><span>Data Servis</span></a>
    <a class="side-link" href="#customers"><i class="bi bi-people"></i><span>Pelanggan</span></a>
    <a class="side-link" href="/admin/profile"><i class="bi bi-shop"></i><span>Profil Bengkel</span></a>
    <div class="nav-caption">Pengelolaan</div>
    <a class="side-link" href="#reports"><i class="bi bi-bar-chart-line"></i><span>Laporan</span></a>
    <a class="side-link" href="#integrations"><i class="bi bi-chat-dots"></i><span>Integrasi Chat</span></a>
    <div class="sidebar-bottom"><div class="fw-bold mb-1">Booking lebih rapi</div><div class="small">Kelola permintaan servis pelanggan dari satu dashboard.</div></div>
  </aside>
  <main class="main" id="dashboard">
    <header class="topbar">
      <div class="topbar-heading"><div class="crumb">Workspace / Overview</div><h1 class="page-title">Dashboard</h1></div>
      <button class="mobile-menu-btn" id="mobileMenuToggle" type="button" aria-label="Buka menu navigasi" aria-controls="appSidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
      <div class="top-actions"><button class="icon-btn hide-mobile" aria-label="Notifikasi"><i class="bi bi-bell"></i></button><button class="icon-btn hide-mobile" aria-label="Bantuan"><i class="bi bi-question-circle"></i></button><div class="avatar">BP</div></div>
    </header>

    <section class="welcome">
      <div class="position-relative" style="z-index:1">
        <div class="small mb-2" style="color:#e9e5ff">WORKSHOP OVERVIEW</div>
        <h2>Halo, selamat datang di BengkelPro 👋</h2>
        <p>Pantau data servis dan siapkan pengalaman booking yang lebih mudah untuk pelanggan.</p>
        <a class="btn btn-light-purple btn-sm mt-3" href="#integrations">Lihat kanal booking <i class="bi bi-arrow-up-right ms-1"></i></a>
      </div><i class="bi bi-wrench-adjustable-circle welcome-icon"></i>
    </section>

    <div class="section-head"><h3>Ringkasan bengkel</h3><span class="muted small">Data yang tersedia saat ini</span></div>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Data servis terbaca</span><span class="stat-icon"><i class="bi bi-clipboard2-check"></i></span></div><div class="stat-value"><?= $total_servis ?></div><div class="stat-foot">Baris yang berhasil dimuat</div></div></div>
      <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Booking menunggu</span><span class="stat-icon" style="background:#fff4e5;color:#c27a20"><i class="bi bi-calendar2-week"></i></span></div><div class="stat-value">—</div><div class="stat-foot">Belum ada status booking terpetakan</div></div></div>
      <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Dalam pengerjaan</span><span class="stat-icon" style="background:#e8f6ff;color:#2796c9"><i class="bi bi-gear"></i></span></div><div class="stat-value">—</div><div class="stat-foot">Menunggu struktur status servis</div></div></div>
      <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Pendapatan hari ini</span><span class="stat-icon" style="background:#e9f8f0;color:#269667"><i class="bi bi-wallet2"></i></span></div><div class="stat-value" style="font-size:22px">Belum ada</div><div class="stat-foot">Belum terhubung ke data transaksi</div></div></div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-xl-7" id="services"><section class="panel">
        <div class="d-flex align-items-start justify-content-between gap-2"><div><h3 class="panel-title">Servis terbaru</h3><div class="panel-sub">Data dari tabel <code>service</code> di Supabase</div></div><span class="pill"><i class="bi bi-cloud-check"></i> Database</span></div>
        <?php if ($service_error !== null): ?><div class="alert alert-warning mt-3 mb-0 py-2 small"><?= e($service_error) ?></div>
        <?php elseif ($services === []): ?><div class="empty-state"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada data servis yang tercatat.</div>
        <?php else: ?>
          <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Referensi</th><th>Informasi</th><th>Status</th></tr></thead><tbody>
          <?php foreach ($services as $index => $row): ?>
            <tr><td><div class="d-flex align-items-center gap-2"><span class="service-avatar"><i class="bi bi-wrench"></i></span><div><div class="service-name">Servis #<?= e(is_array($row) ? ($row['id'] ?? ($index + 1)) : ($index + 1)) ?></div><div class="service-meta">Record database</div></div></div></td>
            <td><div class="service-data">Detail pelanggan dan servis tidak ditampilkan pada ringkasan.</div></td>
            <td><span class="pill gray">Tercatat</span></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </section></div>
      <div class="col-12 col-xl-5" id="integrations"><section class="panel">
        <h3 class="panel-title">Kanal booking pelanggan</h3><div class="panel-sub">Rencana integrasi chat untuk menerima permintaan servis.</div>
        <div class="channel-card"><span class="channel-icon telegram"><i class="bi bi-telegram"></i></span><div><div class="channel-title">Telegram Bot</div><div class="channel-note">Bot API · webhook ke backend PHP</div></div><span class="channel-status">Belum dihubungkan</span></div>
        <div class="channel-card"><span class="channel-icon whatsapp"><i class="bi bi-whatsapp"></i></span><div><div class="channel-title">WhatsApp Business</div><div class="channel-note">Business Platform atau penyedia resmi</div></div><span class="channel-status">Belum dihubungkan</span></div>
        <div class="mt-4 p-3 rounded-3" style="background:#f8f7ff"><div class="d-flex gap-2"><i class="bi bi-info-circle" style="color:var(--purple);font-size:17px"></i><div><div class="fw-bold small mb-1">Tahap berikutnya</div><div class="small muted" style="line-height:1.6">Petakan kolom pelanggan, kendaraan, jadwal, dan status booking sebelum mengaktifkan bot. Token bot dan kredensial tetap disimpan sebagai environment variables.</div></div></div></div>
      </section></div>
    </div>
    <div class="footer-note">BengkelPro · PHP + Supabase · UI dashboard awal</div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const sidebar = document.getElementById('appSidebar');
  const toggle = document.getElementById('mobileMenuToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (!sidebar || !toggle || !backdrop) return;
  const closeMenu = () => {
    sidebar.classList.remove('mobile-open');
    backdrop.classList.remove('show');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Buka menu navigasi');
    toggle.innerHTML = '<i class="bi bi-list"></i>';
    document.body.style.overflow = '';
  };
  const openMenu = () => {
    sidebar.classList.add('mobile-open');
    backdrop.classList.add('show');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Tutup menu navigasi');
    toggle.innerHTML = '<i class="bi bi-x-lg"></i>';
    document.body.style.overflow = 'hidden';
  };
  toggle.addEventListener('click', () => sidebar.classList.contains('mobile-open') ? closeMenu() : openMenu());
  backdrop.addEventListener('click', closeMenu);
  sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 767) closeMenu(); });
})();
</script>
</body>
</html>
