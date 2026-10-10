<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
$user = getenv('BENGKELPRO_ADMIN_USER') ?: '';
$pass = getenv('BENGKELPRO_ADMIN_PASSWORD') ?: '';
$url = rtrim(getenv('SUPABASE_URL') ?: '', '/');
$key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
if ($user === '' || $pass === '' || $url === '' || $key === '') { http_response_code(503); exit('Konfigurasi admin belum lengkap.'); }
if (!hash_equals($user, $_SERVER['PHP_AUTH_USER'] ?? '') || !hash_equals($pass, $_SERVER['PHP_AUTH_PW'] ?? '')) {
 header('WWW-Authenticate: Basic realm="BengkelPro Admin", charset="UTF-8"'); http_response_code(401); exit('Autentikasi diperlukan.');
}
function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$fields = [
 'workshop_name'=>'Nama bengkel','tagline'=>'Tagline','description'=>'Deskripsi','logo_url'=>'URL logo','hero_image_url'=>'URL foto utama',
 'gallery_image_1_url'=>'URL foto galeri 1','gallery_image_2_url'=>'URL foto galeri 2','gallery_image_3_url'=>'URL foto galeri 3',
 'address'=>'Alamat lengkap','whatsapp_number'=>'Nomor WhatsApp','phone_number'=>'Nomor telepon','email'=>'Email publik',
 'google_maps_url'=>'Link Google Maps','instagram_url'=>'Link Instagram','opening_hours'=>'Jam operasional','service_area'=>'Area layanan'
];
$profile = array_fill_keys(array_keys($fields), '');
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $payload = [];
 foreach ($fields as $field=>$label) {
  $v = trim((string)($_POST[$field] ?? ''));
  if (str_ends_with($field, '_url') && $v !== '') {
   if (!filter_var($v, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($v, PHP_URL_SCHEME)), ['http','https'], true)) { $notice = 'URL tidak valid: '.$label; break; }
  }
  if ($field === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $notice = 'Format email tidak valid.'; break; }
  $payload[$field] = $v === '' ? null : $v;
 }
 if ($notice === '') {
  $ch = curl_init($url.'/rest/v1/workshop_profile?id=eq.1');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['apikey: '.$key,'Authorization: Bearer '.$key,'Content-Type: application/json','Prefer: return=representation']]);
  $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $rows=is_string($body)?json_decode($body,true):null;
  if ($body!==false && $code>=200 && $code<300 && is_array($rows) && count($rows)>0) { $profile=array_merge($profile,$rows[0]); $notice='Profil berhasil disimpan.'; }
  else { $notice='Gagal menyimpan profil. Pastikan migration workshop_profile sudah diterapkan di Supabase.'; error_log('Workshop profile update HTTP '.$code); }
 }
}
if ($notice === '' || str_starts_with($notice,'Gagal')) {
 $ch=curl_init($url.'/rest/v1/workshop_profile?id=eq.1&select=*');
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['apikey: '.$key,'Authorization: Bearer '.$key,'Accept: application/json']]);
 $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $rows=is_string($body)?json_decode($body,true):null;
 if ($body!==false && $code>=200 && $code<300 && is_array($rows) && isset($rows[0])) $profile=array_merge($profile,$rows[0]);
 elseif ($notice==='') $notice='Profil belum tersedia. Migration database perlu diterapkan.';
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profil Bengkel · BengkelPro</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f7f7fc;color:#25243b}.wrap{max-width:1000px;margin:32px auto;padding:0 16px}.panel{background:#fff;border:1px solid #e8e8f1;border-radius:18px;padding:26px}.form-label{font-size:13px;font-weight:700}.form-control{padding:10px;border-radius:10px}</style></head><body><main class="wrap"><div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 fw-bold">Profil Bengkel</h1><p class="text-secondary mb-0">Kelola informasi yang tampil di website publik.</p></div><a class="btn btn-outline-secondary" href="/admin">Kembali ke dashboard</a></div><section class="panel"><?php if($notice!==''): ?><div class="alert alert-info"><?=e($notice)?></div><?php endif; ?><form method="post" action="/admin/profile"><div class="row g-3"><?php foreach($fields as $field=>$label): ?><div class="<?=in_array($field,['description','address','opening_hours'],true)?'col-12':'col-md-6'?>"><label class="form-label" for="<?=e($field)?>"><?=e($label)?></label><?php if(in_array($field,['description','address','opening_hours'],true)): ?><textarea class="form-control" id="<?=e($field)?>" name="<?=e($field)?>" rows="3"><?=e($profile[$field]??'')?></textarea><?php else: ?><input class="form-control" id="<?=e($field)?>" name="<?=e($field)?>" value="<?=e($profile[$field]??'')?>" <?=str_ends_with($field,'_url')?'type="url"':''?> <?= $field==='email'?'type="email"':'' ?> <?= $field==='workshop_name'?'required maxlength="120"':'' ?>><?php endif; ?></div><?php endforeach; ?><div class="col-12"><button class="btn btn-primary" type="submit">Simpan profil</button><p class="small text-secondary mt-2 mb-0">Foto pada versi ini memakai URL gambar. Upload dari perangkat membutuhkan konfigurasi Supabase Storage.</p></div></div></form></section></main></body></html>