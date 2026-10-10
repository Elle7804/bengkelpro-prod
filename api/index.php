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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#171a2b">
<meta name="description" content="Booking servis kendaraan secara online dengan mudah. Pilih layanan dan jadwal, lalu bengkel akan mengonfirmasi permintaan Anda.">
<title><?= h($profile["workshop_name"] ?? "BengkelPro") ?> | Booking Servis Kendaraan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--ink:#202239;--muted:#777b91;--purple:#7866e8;--line:#e9eaf2;--soft:#f7f7fc}*{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif}.navbar{background:#fff;border-bottom:1px solid var(--line)}.brand{font-weight:850;letter-spacing:-.04em;font-size:21px}.brand span{color:var(--purple)}.hero{background:linear-gradient(120deg,#211d49,#5144a5);color:#fff;border-radius:24px;padding:clamp(28px,6vw,64px);position:relative;overflow:hidden}.hero-grid{display:grid;grid-template-columns:1.15fr .85fr;align-items:center;gap:28px}.hero-visual{min-height:280px;border-radius:20px;background:linear-gradient(145deg,#342d68,#8a78f1);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}.hero-visual img{width:100%;height:100%;min-height:280px;object-fit:cover;position:absolute;inset:0}.hero-visual .visual-mark{font-size:100px;color:#ffffffd9;filter:drop-shadow(0 14px 24px #17133355)}.hero-visual:before,.hero-visual:after{content:'';position:absolute;border:1px solid #ffffff30;border-radius:50%;width:230px;height:230px}.hero-visual:after{width:310px;height:310px}.section-kicker{color:var(--purple);font-size:11px;font-weight:850;letter-spacing:.16em;text-transform:uppercase}.section-title{font-size:clamp(27px,3vw,38px);font-weight:850;letter-spacing:-.05em;line-height:1.12}.about-panel{background:#fff;border:1px solid var(--line);border-radius:20px;padding:clamp(24px,5vw,48px)}.service-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:23px;height:100%;transition:transform .2s,box-shadow .2s}.service-card:hover{transform:translateY(-4px);box-shadow:0 14px 34px #26214d0d}.service-card .feature-icon{font-size:22px}.trust-line{display:flex;gap:10px;align-items:center;font-size:13px;font-weight:700;color:#e9e5ff}.trust-dot{width:7px;height:7px;border-radius:50%;background:#a9f2ce}.hero:after{content:"";position:absolute;width:290px;height:290px;border:50px solid #ffffff13;border-radius:50%;right:-70px;top:-95px}.hero>*{position:relative;z-index:1}.eyebrow{text-transform:uppercase;letter-spacing:.15em;font-size:11px;font-weight:800;color:#c9c1ff}.hero h1{font-size:clamp(32px,5vw,52px);font-weight:850;letter-spacing:-.055em;max-width:720px;line-height:1.08}.hero p{color:#e1def9;max-width:590px}.btn-primary{background:var(--purple);border-color:var(--purple);border-radius:11px;padding:12px 18px;font-weight:750}.feature,.booking-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;height:100%}.feature-icon{width:43px;height:43px;border-radius:13px;background:#f0edff;color:var(--purple);display:grid;place-items:center;font-size:20px;margin-bottom:15px}.booking-card{padding:clamp(20px,4vw,32px);box-shadow:0 12px 45px #26214d08}.form-label{font-size:13px;font-weight:700}.form-control,.form-select{padding:11px 12px;border-radius:10px;border-color:#dfe1ec}.form-control:focus,.form-select:focus{border-color:var(--purple);box-shadow:0 0 0 .2rem #7866e822}.muted{color:var(--muted)}.footer{color:var(--muted);font-size:12px}.hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}@media(max-width:767px){.hero-grid{grid-template-columns:1fr}.hero-visual{min-height:190px;max-height:230px}.hero-visual img{min-height:190px}.hero-visual .visual-mark{font-size:76px}}@media(max-width:576px){.hero{border-radius:18px}.feature{padding:17px}}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg py-3 sticky-top"><div class="container"><?php if (!empty($profile["logo_url"])): ?><a class="navbar-brand" href="/"><img src="<?= h($profile["logo_url"]) ?>" alt="<?= h($profile["workshop_name"]) ?>" style="max-height:42px;max-width:180px;object-fit:contain"></a><?php else: ?><a class="navbar-brand brand" href="/"><?= h($profile["workshop_name"]) ?></a><?php endif; ?><button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="publicNav"><div class="navbar-nav ms-auto align-items-lg-center gap-lg-3"><a class="nav-link" href="#tentang">Tentang Kami</a><a class="nav-link" href="#layanan">Layanan</a><a class="nav-link" href="#galeri">Galeri</a><a class="nav-link" href="#kontak">Kontak</a><a class="btn btn-primary btn-sm ms-lg-2" href="#booking">Booking Servis <span aria-hidden="true">→</span></a></div></div></div></nav>
<main class="container py-4 py-lg-5">
<section class="hero mb-5">
<div class="hero-grid">
<div><div class="eyebrow mb-3">BENGKEL ANDA, KENDARAAN LEBIH TERAWAT</div>
<h1><?= h($profile["tagline"] ?? "Perawatan kendaraan, lebih praktis") ?></h1>
<p class="mt-3 mb-4"><?= h($profile["description"] ?? "Perawatan kendaraan yang praktis, transparan, dan bisa dimulai dari booking online.") ?></p>
<div class="d-flex flex-wrap gap-2 mb-4"><a class="btn btn-light fw-bold rounded-3 px-4 py-3" href="#booking">Booking Servis <span aria-hidden="true">→</span></a><a class="btn btn-outline-light fw-bold rounded-3 px-4 py-3" href="#tentang">Kenali Bengkel Kami</a></div>
<div class="trust-line"><span class="trust-dot"></span> Tanpa akun, tanpa login, langsung ajukan booking</div>
</div>
<div class="hero-visual"><?php if (!empty($profile["hero_image_url"])): ?><img src="<?= h($profile["hero_image_url"]) ?>" alt="Foto <?= h($profile["workshop_name"]) ?>" loading="eager"><?php else: ?><span class="visual-mark" aria-hidden="true">⚙</span><?php endif; ?></div>
</div></section>
<section id="tentang" class="about-panel mb-5"><div class="row align-items-center g-4"><div class="col-lg-5"><div class="section-kicker mb-3">Tentang Kami</div><h2 class="section-title"><?= h($profile["workshop_name"] ?? "BengkelPro") ?>, partner perawatan kendaraan Anda.</h2></div><div class="col-lg-7"><p class="muted mb-3" style="font-size:16px;line-height:1.9"><?= nl2br(h($profile["description"] ?? "Kami membantu pemilik kendaraan merencanakan servis dengan lebih mudah.")) ?></p><div class="row g-3"><div class="col-sm-6"><div class="d-flex gap-2"><span class="feature-icon flex-shrink-0">✓</span><div><div class="fw-bold">Proses praktis</div><div class="muted small">Mulai dari booking tanpa harus membuat akun.</div></div></div></div><div class="col-sm-6"><div class="d-flex gap-2"><span class="feature-icon flex-shrink-0">⌁</span><div><div class="fw-bold">Komunikasi jelas</div><div class="muted small">Permintaan Anda menunggu konfirmasi dari bengkel.</div></div></div></div></div></div></div></section>
<section id="layanan" class="mb-5"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3"><div><div class="section-kicker mb-2">Layanan Bengkel</div><h2 class="section-title mb-0">Perawatan yang dibutuhkan kendaraan Anda.</h2></div><p class="muted mb-1" style="max-width:340px">Pilih kebutuhan servis saat mengisi formulir booking.</p></div><div class="row g-3">
<div class="col-md-6 col-lg-3"><article class="service-card"><div class="feature-icon">⚙</div><h3 class="h5 fw-bold">Servis Berkala</h3><p class="muted small mb-0">Pemeriksaan rutin untuk membantu menjaga performa dan kenyamanan berkendara.</p></article></div>
<div class="col-md-6 col-lg-3"><article class="service-card"><div class="feature-icon">◉</div><h3 class="h5 fw-bold">Oli & Pelumasan</h3><p class="muted small mb-0">Perawatan pelumas sesuai kebutuhan dan rekomendasi kendaraan.</p></article></div>
<div class="col-md-6 col-lg-3"><article class="service-card"><div class="feature-icon">⛭</div><h3 class="h5 fw-bold">Pemeriksaan Rem</h3><p class="muted small mb-0">Pengecekan komponen pengereman untuk mendukung keselamatan.</p></article></div>
<div class="col-md-6 col-lg-3"><article class="service-card"><div class="feature-icon">⌕</div><h3 class="h5 fw-bold">Diagnostik Kendaraan</h3><p class="muted small mb-0">Pemeriksaan awal untuk membantu menemukan sumber masalah.</p></article></div>
</div></section>
<section class="row g-3 mb-5">
<div class="col-md-4"><div class="feature"><div class="feature-icon">⌁</div><h2 class="h5 fw-bold">Mudah diajukan</h2><p class="muted mb-0 small">Isi formulir tanpa perlu membuat akun atau login.</p></div></div>
<div class="col-md-4"><div class="feature"><div class="feature-icon">◷</div><h2 class="h5 fw-bold">Pilih jadwal</h2><p class="muted mb-0 small">Ajukan waktu servis yang sesuai, lalu tunggu konfirmasi bengkel.</p></div></div>
<div class="col-md-4"><div class="feature"><div class="feature-icon">✓</div><h2 class="h5 fw-bold">Kode booking</h2><p class="muted mb-0 small">Simpan kode booking setelah permintaan berhasil tercatat.</p></div></div>
</section>
<section id="kontak" class="row g-3 mb-5">
<?php if (!empty($profile['address'])): ?><div class="col-md-6"><div class="feature"><h2 class="h5 fw-bold">Alamat bengkel</h2><p class="muted mb-2"><?= h($profile['address']) ?></p><?php if (!empty($profile['google_maps_url'])): ?><a href="<?= h($profile['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer">Petunjuk arah Google Maps</a><?php endif; ?></div></div><?php endif; ?>
<?php if (!empty($profile['whatsapp_number']) || !empty($profile['phone_number'])): ?><div class="col-md-6"><div class="feature"><h2 class="h5 fw-bold">Hubungi bengkel</h2><?php if (!empty($profile['whatsapp_number'])): $wa = preg_replace('/[^0-9]/', '', (string)$profile['whatsapp_number']); ?><p class="mb-2"><a href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener noreferrer">Chat WhatsApp</a></p><?php endif; ?><?php if (!empty($profile['phone_number'])): ?><p class="mb-0">Telepon: <a href="tel:<?= h($profile['phone_number']) ?>"><?= h($profile['phone_number']) ?></a></p><?php endif; ?></div></div><?php endif; ?>
<?php if (!empty($profile['opening_hours'])): ?><div class="col-md-6"><div class="feature"><h2 class="h5 fw-bold">Jam operasional</h2><p class="muted mb-0"><?= nl2br(h($profile['opening_hours'])) ?></p></div></div><?php endif; ?>
<?php if (!empty($profile['email'])): ?><div class="col-md-6"><div class="feature"><h2 class="h5 fw-bold">Email</h2><a href="mailto:<?= h($profile['email']) ?>"><?= h($profile['email']) ?></a></div></div><?php endif; ?>
<?php $gallery = array_filter([$profile['gallery_image_1_url'] ?? '', $profile['gallery_image_2_url'] ?? '', $profile['gallery_image_3_url'] ?? '']); if ($gallery): ?><div class="col-12" id="galeri"><div class="section-kicker mb-2">Suasana Bengkel</div><h2 class="section-title mb-3">Galeri bengkel</h2><div class="row g-3"><?php foreach ($gallery as $imageUrl): ?><div class="col-6 col-md-4"><img src="<?= h($imageUrl) ?>" alt="Foto galeri <?= h($profile['workshop_name']) ?>" loading="lazy" style="width:100%;height:200px;object-fit:cover;border-radius:14px"></div><?php endforeach; ?></div></div><?php endif; ?>
</section>
<section id="booking" class="row justify-content-center"><div class="col-lg-9 col-xl-8"><div class="booking-card">
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
<footer class="footer text-center py-4 mt-4 border-top"><div class="fw-bold mb-1"><?= h($profile["workshop_name"] ?? "BengkelPro") ?></div><div>Partner perawatan kendaraan Anda · Booking servis online tanpa login</div><?php if (!empty($profile['instagram_url'])): ?><a class="d-inline-block mt-2" href="<?= h($profile['instagram_url']) ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?></footer>
</main>
</body></html>
