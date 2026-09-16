# Esoftdream Royalty IT Library

Library PHP / CodeIgniter 4 untuk pengelolaan perhitungan **Royalty IT**, pelunasan tagihan bulanan (top-up deposit), akumulasi fee, serta penyesuaian otomatis batas minimal royalty bulanan (cron adjustment).

![Tests](https://github.com/esoftdream/royalty/workflows/Tests/badge.svg)

---


## 📦 Instalasi

Tambahkan package ini ke dalam `composer.json` project Anda:

```json
{
    "require": {
        "esoftdream/royalty": "dev-main"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../modules/royalty"
        }
    ]
}
```

Jalankan composer update:

```bash
composer update esoftdream/royalty
```

---

## 🗄️ Database Migrations

Jalankan perintah migration CodeIgniter 4 untuk membuat tabel database `report_royalty_fee`, `report_royalty_fee_log`, dan `report_royalty_fee_log_monthly`:

```bash
php spark migrate --all
```

Tabel yang dibuat:
- `report_royalty_fee`: Menyiapkan ringkasan akumulasi fee (`royalty_fee_acc`) dan total terbayar (`royalty_fee_paid`).
- `report_royalty_fee_log`: Log transaksi masuk/keluar fee (`in`, `out`, `outmin`).
- `report_royalty_fee_log_monthly`: Log rekap bulanan (saldo deposit, tagihan, nominal terbayar, sisa tagihan, dan status lunas/belum lunas).

---

## ⚙️ Konfigurasi

Anda dapat mengubah batas minimal royalty bulanan (Default: `500000`) melalui:

1. **Konstanta Global** (opsional):
   ```php
   define('MINIMUM_ROYALTY', 500000);
   ```

2. **File Config Project** (opsional):
   Buat file `app/Config/Royalty.php` yang meng-extend `Esoftdream\Royalty\Config\Royalty`.

3. **Minimal Royalty Dinamis (Per Bulan / Berbasis Kriteria Omset)**:
   ```php
   // Cara A: Mengubah nominal minimal royalty secara eksplisit
   $royalty->setMinimumRoyalty(5000000);

   // Cara B: Menggunakan Callback Resolver (Otomatis menyesuaikan kriteria omset)
   $royalty->setMinimumRoyaltyResolver(function (string $date, BaseConnection $db) {
       $month = date('m', strtotime($date));
       // Contoh: Jika bulan September, minimal royalty dinaikkan jadi 5.000.000
       if ($month === '09') {
           return 5000000;
       }
       return 4000000; // Default Rp 4.000.000
   });

   // Cara C: Override langsung saat pemanggilan method
   $royalty->updateRoyalty($nominalFee, 5000000);
   $royalty->processAdjustment($db, 5000000);
   ```

---

## 🚀 Penggunaan Library

### 1. Inisialisasi Service

```php
use Esoftdream\Royalty\Royalty;

// Menggunakan koneksi database default dan konfigurasi default
$royalty = new Royalty();

// Atau dengan koneksi khusus / minimal royalty kustom
$royalty = new Royalty($customDbConnection, 500000);
```

---

### 2. Menambah / Mengakumulasi Royalty Fee (`updateRoyalty`)

Setiap ada transaksi yang menghasilkan nilai royalty IT, panggil method `updateRoyalty`:

```php
$nominalFee = 15000; // Contoh nilai royalty fee

// Mengakumulasi fee ke tabel summary dan rekap bulanan
$royalty->updateRoyalty($nominalFee);

// Alias snake_case juga didukung untuk kompatibilitas:
// $royalty->update_royalty($nominalFee);
```

---

### 3. Top Up Deposit & Pelunasan Tagihan (`topUp`)

Digunakan ketika administrator atau sistem menginput pembayaran deposit/top-up royalty. Deposit ini secara otomatis akan melunasi sisa tagihan bulanan terdahulu yang berstatus `unpaid` secara kronologis.

```php
$topUpAmount = 500000;
$date        = date('Y-m-d H:i:s');
$logId       = 1; // ID dari report_royalty_fee_log_monthly
$note        = 'Pembayaran Top Up Deposit Royalty IT';
$adminId     = session('administrator_id');

$royalty->topUp($topUpAmount, $date, $logId, $note, $adminId);

// Alias snake_case:
// $royalty->top_up($topUpAmount, $date, $logId, $note, $adminId);
```

---

### 4. Penyesuaian Minimal Royalty Bulanan (`processAdjustment`)

Digunakan pada tanggal 1 setiap bulan (via Cron Job) untuk mengecek apakah akumulasi royalty bulan lalu mencapai batas minimal. Jika belum mencapai minimal, selisihnya akan ditagihkan sebagai kekurangan royalty.

#### via PHP Service:
```php
$message = $royalty->processAdjustment();
echo $message; // "Penyesuaian royalty IT berhasil."
```

#### via CLI Spark Command:
```bash
php spark royalty:adjustment
```

---

### 5. Mengakses Data & Laporan (`RoyaltyModel`)

Library menyediakan `RoyaltyModel` untuk memudahkah pembuatan laporan atau tampilan admin/dashboard:

```php
use Esoftdream\Royalty\Models\RoyaltyModel;

$model = new RoyaltyModel();

// Get summary akumulasi dan total terbayar
$summary = $model->getSummary();

// Get rekap bulanan spesifik (contoh: September 2026)
$monthlyLog = $model->getMonthlyLog(9, 2026);

// Get daftar log transaksi dalam bulan tertentu
$logs = $model->getLogsByMonthYear(9, 2026);

// Get rekap tahunan
$annualSummary = $model->getAnnualSummary(2026);

// Get daftar tahun yang memiliki data log
$years = $model->getAvailableYears();
```

---

## 🧪 Testing

Jalankan pengujian unit (PHPUnit) dengan perintah:

```bash
composer test
```

atau langsung menggunakan binary PHPUnit:

```bash
vendor/bin/phpunit
```

---

## 📋 Lisensi & Hak Cipta

Proprietary Code - Esoftdream

