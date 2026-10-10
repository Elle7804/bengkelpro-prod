<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function supabase_request(string $method, string $table, array $payload, string $base, string $key): array {
    $ch = curl_init($base . '/rest/v1/' . $table);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = is_string($body) ? json_decode($body, true) : null;
    return ['ok' => $body !== false && $code >= 200 && $code < 300 && is_array($decoded), 'code' => $code, 'data' => $decoded];
}

$profile = ['workshop_name'=>'BengkelPro','tagline'=>'Perawatan kendaraan, lebih praktis','description'=>'Ajukan booking servis kendaraan secara online.','logo_url'=>'','hero_image_url'=>'','gallery_image_1_url'=>'','gallery_image_2_url'=>'','gallery_image_3_url'=>'','address'=>'','whatsapp_number'=>'','phone_number'=>'','email'=>'','google_maps_url'=>'','instagram_url'=>'','opening_hours'=>'','service_area'=>''];
$profileBase = rtrim(getenv('SUPABASE_URL') ?: '', '/'); $profileKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
if ($profileBase !== '' && $profileKey !== '' && function_exists('curl_init')) {
 $ch = curl_init($profileBase.'/rest/v1/workshop_profile?id=eq.1&select=*');
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_HTTPHEADER=>['apikey: '.$profileKey,'Authorization: Bearer '.$profileKey,'Accept: application/json']]);
 $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $rows=is_string($body)?json_decode($body,true):null;
 if ($body!==false && $code>=200 && $code<300 && is_array($rows) && isset($rows[0])) $profile=array_merge($profile,$rows[0]);
}
$successCode = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quiet honeypot to deter basic automated form spam.
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $successCode = 'Permintaan diterima. Jika data valid, bengkel akan menghubungi Anda.';
    } else {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $plate = strtoupper(trim((string)($_POST['plate_number'] ?? '')));
        $brand = trim((string)($_POST['brand'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $vehicleType = (string)($_POST['vehicle_type'] ?? 'motor');
        $requestedService = trim((string)($_POST['requested_service'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $schedule = trim((string)($_POST['scheduled_at'] ?? ''));

        if (strlen($name) < 2 || strlen($name) > 120) {
            $errorMessage = 'Nama harus terdiri dari 2 sampai 120 karakter.';
        } elseif ($phone === '' || strlen($phone) > 30 || !preg_match('/^[0-9+() .-]{7,30}$/', $phone)) {
            $errorMessage = 'Masukkan nomor HP yang valid.';
        } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) {
            $errorMessage = 'Format email tidak valid.';
        } elseif ($plate === '' || strlen($plate) > 20) {
            $errorMessage = 'Nomor polisi wajib diisi.';
        } elseif (!in_array($vehicleType, ['motor', 'mobil'], true)) {
            $errorMessage = 'Jenis kendaraan tidak valid.';
        } elseif ($requestedService === '' || strlen($requestedService) > 120) {
            $errorMessage = 'Pilih atau tulis layanan yang dibutuhkan.';
        } elseif (strlen($notes) > 1000) {
            $errorMessage = 'Catatan maksimal 1.000 karakter.';
        } else {
            $scheduledAt = null;
            if ($schedule !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $schedule, new DateTimeZone('Asia/Jakarta'));
                $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
                if (!$date || $date <= $now) {
                    $errorMessage = 'Jadwal harus berupa waktu mendatang yang valid.';
                } else {
                    $scheduledAt = $date->format(DateTimeInterface::ATOM);
                }
            }
            $base = rtrim(getenv('SUPABASE_URL') ?: '', '/');
            $key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
            if ($errorMessage === '' && ($base === '' || $key === '' || !function_exists('curl_init'))) {
                $errorMessage = 'Booking online belum dikonfigurasi. Silakan hubungi bengkel melalui WhatsApp.';
                http_response_code(503);
            }
            if ($errorMessage === '') {
                $customerPayload = ['full_name' => $name, 'phone' => $phone];
                if ($email !== '') $customerPayload['email'] = $email;
                $customer = supabase_request('POST', 'customers', $customerPayload, $base, $key);
                if (!$customer['ok'] || empty($customer['data'][0]['id'])) {
                    $errorMessage = 'Booking belum dapat disimpan. Silakan coba lagi atau hubungi bengkel.';
                    error_log('BengkelPro booking: customer insert failed, HTTP ' . $customer['code']);
                } else {
                    $customerId = $customer['data'][0]['id'];
                    $vehicle = supabase_request('POST', 'vehicles', [
                        'customer_id' => $customerId,
                        'plate_number' => $plate,
                        'brand' => $brand !== '' ? substr($brand, 0, 80) : null,
                        'model' => $model !== '' ? substr($model, 0, 80) : null,
                        'vehicle_type' => $vehicleType,
                    ], $base, $key);
                    if (!$vehicle['ok'] || empty($vehicle['data'][0]['id'])) {
                        error_log('BengkelPro booking: vehicle insert failed, HTTP ' . $vehicle['code']);
                        supabase_request('DELETE', 'customers?id=eq.' . rawurlencode($customerId), [], $base, $key);
                        $errorMessage = 'Booking belum dapat disimpan. Silakan coba lagi atau hubungi bengkel.';
                    } else {
                        $bookingPayload = [
                            'customer_id' => $customerId,
                            'vehicle_id' => $vehicle['data'][0]['id'],
                            'requested_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DateTimeInterface::ATOM),
                            'source_channel' => 'website',
                            'customer_complaint' => 'Layanan diminta: ' . $requestedService . ($notes !== '' ? "\nCatatan: " . $notes : ''),
                        ];
                        if ($scheduledAt !== null) $bookingPayload['scheduled_at'] = $scheduledAt;
                        $booking = supabase_request('POST', 'bookings', $bookingPayload, $base, $key);
                        if (!$booking['ok'] || empty($booking['data'][0]['booking_code'])) {
                            error_log('BengkelPro booking: booking insert failed, HTTP ' . $booking['code']);
                            supabase_request('DELETE', 'vehicles?id=eq.' . rawurlencode($vehicle['data'][0]['id']), [], $base, $key);
                            supabase_request('DELETE', 'customers?id=eq.' . rawurlencode($customerId), [], $base, $key);
                            $errorMessage = 'Booking belum dapat disimpan. Silakan coba lagi atau hubungi bengkel.';
                        } else {
                            $successCode = (string)$booking['data'][0]['booking_code'];
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#f8f8fc">
<meta name="description" content="<?= h($profile['description'] ?? 'Profil bengkel dan booking servis kendaraan online.') ?>">
<title><?= h($profile["workshop_name"] ?? "BengkelPro") ?> | Profil Bengkel</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--ink:#24243b;--muted:#85859d;--line:#eeedf5;--purple:#7866e8;--purple-soft:#f0edff;--canvas:#f8f8fc;--green:#249866}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:90px}body{margin:0;background:var(--canvas);color:var(--ink);font-family:'DM Sans',sans-serif;font-size:14px}h1,h2,h3,.brand{font-family:'Manrope',sans-serif;letter-spacing:-.035em}.navbar{background:#fff;border-bottom:1px solid var(--line)}.brand{font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px}.brand-icon{width:34px;height:34px;border-radius:12px;background:var(--purple);color:#fff;display:grid;place-items:center}.nav-link{font-weight:600;color:#77778f}.nav-link:hover,.nav-link:focus{color:var(--purple)}.btn-primary{background:var(--purple);border-color:var(--purple);border-radius:10px;padding:10px 15px;font-weight:700}.btn-primary:hover{background:#6552d5;border-color:#6552d5}.page{max-width:1440px}.crumb{color:var(--muted);font-size:12px;margin-bottom:5px}.page-title{font-size:26px;font-weight:800;margin:0}.eyebrow,.section-kicker{color:var(--purple);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.welcome{background:linear-gradient(112deg,#7764e6,#9586f2);border-radius:19px;color:#fff;padding:clamp(24px,4vw,36px);position:relative;overflow:hidden;margin-bottom:22px}.welcome:after{content:'';position:absolute;width:260px;height:260px;border:42px solid #ffffff12;border-radius:50%;right:5%;top:-120px}.welcome h1{font-size:clamp(28px,4vw,42px);font-weight:800;max-width:720px;line-height:1.12;margin:10px 0}.welcome p{color:#e9e5ff;margin:0;max-width:620px;font-size:15px;line-height:1.7}.welcome-content{position:relative;z-index:1}.welcome-icon{position:absolute;right:8%;bottom:-25px;font-size:125px;color:#ffffff24;transform:rotate(-15deg)}.trust-line{display:flex;gap:9px;align-items:center;font-size:12px;font-weight:700;color:#e9e5ff}.trust-dot{width:7px;height:7px;border-radius:50%;background:#a9f2ce}.hero-image{height:245px;border:1px solid #ffffff38;border-radius:16px;overflow:hidden;background:linear-gradient(145deg,#342d68,#8a78f1);display:grid;place-items:center;position:relative}.hero-image img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.hero-image i{font-size:92px;color:#ffffffcc}.stat-card,.panel,.about-panel,.service-card,.gallery-card,.booking-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;height:100%}.stat-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.stat-icon,.feature-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:var(--purple-soft);color:var(--purple);font-size:18px;flex-shrink:0}.stat-label{font-size:12px;color:var(--muted);font-weight:600}.stat-value{font-family:'Manrope',sans-serif;font-size:18px;font-weight:800;letter-spacing:-.03em}.stat-foot{font-size:11px;color:var(--muted);margin-top:6px;line-height:1.5}.section-head{display:flex;justify-content:space-between;align-items:end;gap:15px;margin:30px 0 14px}.section-head h2{font-size:21px;font-weight:800;margin:5px 0 0}.muted{color:var(--muted)}.about-panel{padding:clamp(24px,4vw,38px)}.about-copy{font-size:14px;line-height:1.9;color:#77778f}.service-card{transition:transform .2s,box-shadow .2s;padding:22px}.service-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px #26214d0c}.service-card h3{font-size:15px;font-weight:800;margin:16px 0 8px}.service-card p{font-size:12px;color:var(--muted);line-height:1.7;margin:0}.gallery-card{padding:0;overflow:hidden;min-height:160px;background:#f0edff;position:relative}.gallery-card img{width:100%;height:200px;object-fit:cover;display:block}.gallery-empty{height:170px;display:grid;place-items:center;color:#7866e8;font-size:38px;background:linear-gradient(135deg,#f0edff,#e2ddff)}.contact-item{display:flex;gap:13px;align-items:flex-start;padding:15px 0;border-bottom:1px solid #f1f0f7}.contact-item:last-child{border:0}.contact-item strong{display:block;font-size:12px;margin-bottom:4px}.contact-item span,.contact-item a{font-size:12px;color:var(--muted);overflow-wrap:anywhere}.booking-card{padding:clamp(20px,4vw,32px);box-shadow:0 12px 45px #26214d08}.form-label{font-size:12px;font-weight:700}.form-control,.form-select{padding:11px 12px;border-radius:10px;border-color:#dfe1ec;font-size:13px}.form-control:focus,.form-select:focus{border-color:var(--purple);box-shadow:0 0 0 .2rem #7866e822}.hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}.footer{color:var(--muted);font-size:12px}.pill{display:inline-flex;align-items:center;gap:6px;border-radius:8px;padding:6px 9px;font-size:11px;font-weight:700;background:#f1efff;color:#7866e8}
@media(max-width:767px){.welcome-icon{right:-10px;font-size:90px}.hero-image{height:190px}.section-head{align-items:flex-start;flex-direction:column}.page-title{font-size:22px}}@media(max-width:575px){.welcome{border-radius:15px}.welcome p{font-size:13px}.stat-card{padding:15px}.stat-value{font-size:15px}.navbar .container{padding-left:16px;padding-right:16px}}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top py-3"><div class="container page">
<?php if (!empty($profile["logo_url"])): ?><a class="navbar-brand" href="/"><img src="<?= h($profile["logo_url"]) ?>" alt="<?= h($profile["workshop_name"]) ?>" style="max-height:38px;max-width:175px;object-fit:contain"></a><?php else: ?><a class="navbar-brand brand" href="/"><span class="brand-icon"><i class="bi bi-wrench-adjustable"></i></span><?= h($profile["workshop_name"] ?? "BengkelPro") ?></a><?php endif; ?>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="publicNav"><div class="navbar-nav ms-auto align-items-lg-center gap-lg-2"><a class="nav-link" href="#tentang">Tentang Kami</a><a class="nav-link" href="#layanan">Layanan</a><a class="nav-link" href="#galeri">Galeri</a><a class="nav-link" href="#kontak">Kontak</a><a class="btn btn-primary btn-sm ms-lg-2 mt-2 mt-lg-0" href="#booking">Booking Servis <i class="bi bi-arrow-up-right ms-1"></i></a></div></div>
</div></nav>
<main class="container page py-4 py-lg-5">
<div class="mb-4"><div class="crumb">Beranda / Profil Bengkel</div><h2 class="page-title">Profil Bengkel</h2></div>
<section class="welcome">
<div class="row align-items-center g-4"><div class="col-lg-7 welcome-content"><div class="eyebrow" style="color:#e0dbff">Selamat datang di <?= h($profile["workshop_name"] ?? "BengkelPro") ?></div><h1><?= h($profile["tagline"] ?? "Perawatan kendaraan, lebih praktis") ?></h1><p><?= h($profile["description"] ?? "Servis kendaraan yang praktis, transparan, dan mudah dijadwalkan.") ?></p><div class="d-flex flex-wrap gap-2 my-4"><a class="btn btn-light fw-bold rounded-3 px-3 py-2" href="#booking">Booking Servis <i class="bi bi-arrow-up-right ms-1"></i></a><a class="btn btn-outline-light fw-bold rounded-3 px-3 py-2" href="#layanan">Lihat Layanan</a></div><div class="trust-line"><span class="trust-dot"></span> Pelanggan dapat mengajukan booking tanpa login</div></div>
<div class="col-lg-5 welcome-content"><div class="hero-image"><?php if (!empty($profile["hero_image_url"])): ?><img src="<?= h($profile["hero_image_url"]) ?>" alt="Foto <?= h($profile["workshop_name"]) ?>"><?php else: ?><i class="bi bi-tools" aria-hidden="true"></i><?php endif; ?></div></div></div><i class="bi bi-wrench-adjustable-circle welcome-icon" aria-hidden="true"></i>
</section>
<section class="row g-3 mb-4" aria-label="Informasi bengkel">
<div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Layanan</span><span class="stat-icon"><i class="bi bi-tools"></i></span></div><div class="stat-value">Servis kendaraan</div><div class="stat-foot">Perawatan dan pemeriksaan kendaraan</div></div></div>
<div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Booking online</span><span class="stat-icon" style="background:#e9f8f0;color:#249866"><i class="bi bi-calendar2-check"></i></span></div><div class="stat-value">Tanpa login</div><div class="stat-foot">Ajukan permintaan dari website</div></div></div>
<div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Konfirmasi</span><span class="stat-icon" style="background:#fff4e5;color:#c27a20"><i class="bi bi-chat-dots"></i></span></div><div class="stat-value">Dari bengkel</div><div class="stat-foot">Jadwal menunggu konfirmasi</div></div></div>
<div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span class="stat-label">Informasi</span><span class="stat-icon" style="background:#e8f5ff;color:#229ed9"><i class="bi bi-geo-alt"></i></span></div><div class="stat-value"><?= !empty($profile["address"]) ? "Lokasi tersedia" : "Hubungi kami" ?></div><div class="stat-foot"><?= !empty($profile["opening_hours"]) ? h($profile["opening_hours"]) : "Alamat dan jam operasional bengkel" ?></div></div></div>
</section>
<section id="tentang" class="section-head"><div><div class="section-kicker">Tentang Kami</div><h2>Kenali bengkel kami</h2></div></section>
<section class="about-panel mb-4"><div class="row align-items-center g-4"><div class="col-lg-5"><div class="feature-icon mb-3"><i class="bi bi-building-gear"></i></div><h2 class="fw-bold" style="font-size:25px"><?= h($profile["workshop_name"] ?? "BengkelPro") ?></h2><p class="muted mb-0"><?= h($profile["tagline"] ?? "Perawatan kendaraan, lebih praktis") ?></p></div><div class="col-lg-7"><p class="about-copy mb-3"><?= nl2br(h($profile["description"] ?? "Kami membantu pemilik kendaraan merencanakan servis dengan lebih mudah.")) ?></p><div class="d-flex flex-wrap gap-2"><span class="pill"><i class="bi bi-check-circle"></i> Booking praktis</span><span class="pill"><i class="bi bi-chat-square-text"></i> Menunggu konfirmasi</span></div></div></div></section>
<section id="layanan"><div class="section-head"><div><div class="section-kicker">Layanan Bengkel</div><h2>Layanan untuk kendaraan Anda</h2></div><p class="muted mb-0">Tentukan kebutuhan servis saat booking.</p></div><div class="row g-3">
<div class="col-md-6 col-xl-3"><article class="service-card"><div class="feature-icon"><i class="bi bi-gear-wide-connected"></i></div><h3>Servis Berkala</h3><p>Pemeriksaan rutin untuk membantu menjaga performa dan kenyamanan berkendara.</p></article></div>
<div class="col-md-6 col-xl-3"><article class="service-card"><div class="feature-icon"><i class="bi bi-droplet"></i></div><h3>Oli & Pelumasan</h3><p>Perawatan pelumas sesuai kebutuhan dan rekomendasi kendaraan.</p></article></div>
<div class="col-md-6 col-xl-3"><article class="service-card"><div class="feature-icon"><i class="bi bi-shield-check"></i></div><h3>Pemeriksaan Rem</h3><p>Pengecekan komponen pengereman untuk mendukung keselamatan.</p></article></div>
<div class="col-md-6 col-xl-3"><article class="service-card"><div class="feature-icon"><i class="bi bi-search"></i></div><h3>Diagnostik Kendaraan</h3><p>Pemeriksaan awal untuk membantu menemukan sumber masalah kendaraan.</p></article></div>
</div></section>
<section id="galeri"><div class="section-head"><div><div class="section-kicker">Galeri</div><h2>Suasana bengkel</h2></div><span class="muted small">Foto bengkel</span></div><div class="row g-3">
<?php foreach (["gallery_image_1_url","gallery_image_2_url","gallery_image_3_url"] as $galleryKey): ?><div class="col-md-4"><div class="gallery-card"><?php if (!empty($profile[$galleryKey])): ?><img src="<?= h($profile[$galleryKey]) ?>" alt="Galeri <?= h($profile["workshop_name"]) ?>" loading="lazy"><?php else: ?><div class="gallery-empty"><i class="bi bi-image"></i></div><?php endif; ?></div></div><?php endforeach; ?>
</div></section>
<section id="kontak"><div class="section-head"><div><div class="section-kicker">Kontak</div><h2>Hubungi atau kunjungi kami</h2></div></div><div class="row g-3 mb-4"><div class="col-lg-7"><div class="panel">
<div class="contact-item"><span class="stat-icon"><i class="bi bi-geo-alt"></i></span><div><strong>Alamat bengkel</strong><span><?= !empty($profile["address"]) ? nl2br(h($profile["address"])) : "Alamat belum ditambahkan." ?></span><?php if (!empty($profile["google_maps_url"])): ?><div><a href="<?= h($profile["google_maps_url"]) ?>" target="_blank" rel="noopener noreferrer">Buka Google Maps <i class="bi bi-arrow-up-right"></i></a></div><?php endif; ?></div></div>
<div class="contact-item"><span class="stat-icon"><i class="bi bi-clock"></i></span><div><strong>Jam operasional</strong><span><?= !empty($profile["opening_hours"]) ? nl2br(h($profile["opening_hours"])) : "Informasi jam operasional belum ditambahkan." ?></span></div></div>
<div class="contact-item"><span class="stat-icon"><i class="bi bi-telephone"></i></span><div><strong>Telepon / WhatsApp</strong><span><?= !empty($profile["phone_number"]) ? h($profile["phone_number"]) : (!empty($profile["whatsapp_number"]) ? h($profile["whatsapp_number"]) : "Nomor kontak belum ditambahkan.") ?></span><?php if (!empty($profile["whatsapp_number"])): ?><div><a href="https://wa.me/<?= h(preg_replace('/\D+/', '', (string)$profile["whatsapp_number"])) ?>" target="_blank" rel="noopener noreferrer">Chat WhatsApp <i class="bi bi-arrow-up-right"></i></a></div><?php endif; ?></div></div>
<?php if (!empty($profile["email"])): ?><div class="contact-item"><span class="stat-icon"><i class="bi bi-envelope"></i></span><div><strong>Email</strong><span><?= h($profile["email"]) ?></span></div></div><?php endif; ?>
<?php if (!empty($profile["instagram_url"])): ?><div class="contact-item"><span class="stat-icon"><i class="bi bi-instagram"></i></span><div><strong>Instagram</strong><a href="<?= h($profile["instagram_url"]) ?>" target="_blank" rel="noopener noreferrer">Lihat profil Instagram</a></div></div><?php endif; ?>
</div></div><div class="col-lg-5"><div class="panel h-100" style="background:linear-gradient(145deg,#fff,#f5f2ff)"><div class="feature-icon mb-3"><i class="bi bi-calendar2-check"></i></div><h3 class="fw-bold fs-5">Rencanakan servis Anda</h3><p class="muted" style="line-height:1.8">Kirim permintaan booking online. Tim bengkel akan meninjau permintaan dan mengonfirmasi jadwal.</p><a href="#booking" class="btn btn-primary w-100">Booking Servis <i class="bi bi-arrow-up-right ms-1"></i></a></div></div></div></section>
<div id="booking" class="section-head"><div><div class="section-kicker">Booking Online</div><h2>Ajukan permintaan servis</h2></div><span class="pill"><i class="bi bi-person-check"></i> Tanpa login</span></div>
<section class="row justify-content-center"><div class="col-lg-9 col-xl-8"><div class="booking-card">
<div class="eyebrow mb-2" style="color:var(--purple)">Formulir pelanggan</div><h2 class="h3 fw-bold mb-2">Booking servis</h2><p class="muted small mb-4">Kolom bertanda * wajib diisi. Jangan masukkan informasi sensitif yang tidak diperlukan.</p>
<?php if ($successCode !== ''): ?><div class="alert alert-success"><h3 class="h5 fw-bold">Permintaan berhasil dikirim</h3><p class="mb-1">Kode booking Anda:</p><div class="fs-4 fw-bold"><?= h($successCode) ?></div><p class="small mb-0 mt-2">Simpan kode ini. Jadwal masih menunggu konfirmasi bengkel.</p></div>
<?php elseif ($errorMessage !== ''): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<form method="post" action="/#booking" autocomplete="on">
<div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="full_name">Nama lengkap *</label><input class="form-control" id="full_name" name="full_name" maxlength="120" required value="<?= h($_POST['full_name'] ?? '') ?>"></div>
<div class="col-md-6"><label class="form-label" for="phone">Nomor HP/WhatsApp *</label><input class="form-control" id="phone" name="phone" type="tel" maxlength="30" required value="<?= h($_POST['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx"></div>
<div class="col-md-6"><label class="form-label" for="email">Email (opsional)</label><input class="form-control" id="email" name="email" type="email" maxlength="254" value="<?= h($_POST['email'] ?? '') ?>"></div>
<div class="col-md-6"><label class="form-label" for="vehicle_type">Jenis kendaraan *</label><select class="form-select" id="vehicle_type" name="vehicle_type" required><option value="motor">Sepeda motor</option><option value="mobil">Mobil</option></select></div>
<div class="col-md-6"><label class="form-label" for="plate_number">Nomor polisi *</label><input class="form-control" id="plate_number" name="plate_number" maxlength="20" required value="<?= h($_POST['plate_number'] ?? '') ?>" placeholder="Contoh: D 1234 ABC"></div>
<div class="col-md-6"><label class="form-label" for="brand">Merek kendaraan</label><input class="form-control" id="brand" name="brand" maxlength="80" value="<?= h($_POST['brand'] ?? '') ?>" placeholder="Contoh: Honda"></div>
<div class="col-md-6"><label class="form-label" for="model">Model kendaraan</label><input class="form-control" id="model" name="model" maxlength="80" value="<?= h($_POST['model'] ?? '') ?>" placeholder="Contoh: Vario 160"></div>
<div class="col-md-6"><label class="form-label" for="scheduled_at">Tanggal dan waktu yang diinginkan</label><input class="form-control" id="scheduled_at" name="scheduled_at" type="datetime-local" value="<?= h($_POST['scheduled_at'] ?? '') ?>"><div class="form-text">Waktu Indonesia Barat (WIB), menunggu konfirmasi.</div></div>
<div class="col-12"><label class="form-label" for="requested_service">Layanan yang dibutuhkan *</label><input class="form-control" id="requested_service" name="requested_service" maxlength="120" required value="<?= h($_POST['requested_service'] ?? '') ?>" placeholder="Contoh: ganti oli, servis berkala, cek rem"></div>
<div class="col-12"><label class="form-label" for="notes">Keluhan atau catatan tambahan</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Ceritakan keluhan kendaraan secara singkat"><?= h($_POST['notes'] ?? '') ?></textarea></div>
<div class="col-12"><button class="btn btn-primary w-100" type="submit">Kirim permintaan booking</button><p class="muted small mt-3 mb-0 text-center">Dengan mengirim formulir, Anda menyetujui data ini digunakan untuk memproses permintaan servis.</p></div>
</div></form>
</div></div></section>

<footer class="footer text-center py-4 mt-4 border-top"><div class="fw-bold mb-1"><?= h($profile["workshop_name"] ?? "BengkelPro") ?></div><div>Profil bengkel dan booking servis online</div></footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
