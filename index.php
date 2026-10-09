
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BengkelPro | Dashboard</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f3f5f9;
      color: #172033;
    }

    header {
      background: #172554;
      color: white;
      padding: 22px;
    }

    header h1 {
      margin: 0 0 6px;
      font-size: 24px;
    }

    header p {
      margin: 0;
      color: #cbd5e1;
      font-size: 14px;
    }

    main {
      max-width: 1000px;
      margin: 28px auto;
      padding: 0 18px;
    }

    .welcome {
      margin-bottom: 24px;
    }

    .welcome h2 {
      margin-bottom: 8px;
    }

    .welcome p {
      color: #64748b;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 16px;
    }

    .card {
      background: white;
      border-radius: 12px;
      padding: 22px;
      border: 1px solid #e2e8f0;
    }

    .card h3 {
      margin-top: 0;
      font-size: 16px;
    }

    .number {
      font-size: 30px;
      font-weight: bold;
      color: #2563eb;
    }

    .note {
      margin-top: 24px;
      padding: 16px;
      border-radius: 10px;
      background: #dbeafe;
      color: #1e40af;
      font-size: 14px;
      line-height: 1.6;
    }

    footer {
      text-align: center;
      color: #64748b;
      padding: 24px;
      font-size: 13px;
    }
  </style>
</head>
<body>
  <header>
    <h1>BengkelPro</h1>
    <p>Sistem Manajemen Bengkel</p>
  </header>

  <main>
    <section class="welcome">
      <h2>Dashboard</h2>
      <p>Selamat datang di sistem manajemen bengkel Anda.</p>
    </section>

    <section class="cards">
      <div class="card">
        <h3>Total Kendaraan</h3>
        <div class="number">0</div>
        <p>Data kendaraan terdaftar</p>
      </div>

      <div class="card">
        <h3>Servis Hari Ini</h3>
        <div class="number">0</div>
        <p>Antrean servis hari ini</p>
      </div>

      <div class="card">
        <h3>Servis Selesai</h3>
        <div class="number">0</div>
        <p>Kendaraan selesai diservis</p>
      </div>
    </section>

    <div class="note">
      Dashboard awal berhasil dimuat. Angka di atas masih berupa
      data contoh dan belum terhubung ke database.
    </div>
  </main>

  <footer>
    BengkelPro &copy; 2026
  </footer>
</body>
</html>
