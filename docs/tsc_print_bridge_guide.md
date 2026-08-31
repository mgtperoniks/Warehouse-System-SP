# Panduan Implementasi Jembatan Cetak Thermal TSC (Laravel & Python Agent)

Dokumen ini menjelaskan arsitektur, cara kerja, serta kode lengkap untuk membangun sistem pencetakan barcode direct thermal menggunakan printer **TSC TE200 / TE244** (atau printer TSPL lainnya) dari aplikasi **Laravel** melalui **Windows Print Agent (Python)**.

---

## 1. Arsitektur & Alur Kerja (Workflow)

Printer thermal seperti TSC TE200 yang terhubung via USB ke PC Windows tidak bisa diakses secara langsung oleh server Laravel (terutama jika server berada di Linux/Cloud). 

Oleh karena itu, kita menggunakan **Pull-based Queue Architecture**:

```text
┌────────────────────────┐         HTTP API Queue          ┌────────────────────────┐
│ Laravel Server (Linux) │ ──────────────────────────────> │  Windows Print Agent   │
│   (Queue Authority)    │ <────────────────────────────── │ (Python + win32print)  │
└────────────────────────┘                                 └────────────────────────┘
            │                                                           │
            │ 1. Kirim Print Job                                        │ 2. Kirim RAW TSPL
            ▼                                                           ▼
┌────────────────────────┐                                 ┌────────────────────────┐
│    User di Browser     │                                 │   Printer TSC TE200    │
└────────────────────────┘                                 └────────────────────────┘
```

### Alur Detail:
1. **User** melakukan klik tombol "Print" di browser (frontend Laravel).
2. **Laravel Server** menerima request, merender data label menjadi kode **TSPL** (bahasa pemrograman printer TSC), lalu menyimpan job tersebut di database dengan status `pending`.
3. **Windows Print Agent** (script Python yang berjalan di PC lokal Windows tempat printer terpasang) melakukan polling berkala ke endpoint API Laravel (`POST /api/print-jobs/claim`).
4. **Laravel Server** melakukan klaim secara atomik (mengunci baris data di database) dan memberikan payload TSPL ke agent.
5. **Agent** menerima payload, memverifikasi hash (SHA256) untuk memastikan integritas data, lalu mengirimkan perintah RAW tersebut langsung ke Windows Print Spooler menggunakan library `pywin32`.
6. **Agent** mengupdate status job ke Laravel (`complete` atau `failed`).

---

## 2. Bagian 1: Implementasi Backend Laravel

### A. Database Migration (`print_jobs`)
Buat file migration untuk tabel antrean cetak:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->string('printer_name'); // Nama printer target di Windows (e.g. 'TSC TE200')
            $table->longText('payload_tspl'); // Perintah RAW TSPL
            $table->string('payload_hash', 64); // SHA256 dari payload_tspl untuk integritas data
            $table->integer('copies')->default(1);
            $table->enum('status', ['pending', 'processing', 'printed', 'failed'])->default('pending');
            
            // Tracking
            $table->string('claimed_by_machine')->nullable(); // Identitas PC yang mencetak
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            
            // Metadata
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('template_type')->nullable(); // e.g. ITEM_LABEL, BIN_LABEL
            
            $table->timestamps();

            // Index untuk optimasi performa polling
            $table->index('status');
            $table->index('printer_name');
            $table->index('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
```

### B. Eloquent Model (`App\Models\PrintJob.php`)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrintJob extends Model
{
    protected $fillable = [
        'job_uuid',
        'printer_name',
        'payload_tspl',
        'payload_hash',
        'copies',
        'status',
        'claimed_by_machine',
        'claimed_at',
        'printed_at',
        'failed_at',
        'error_message',
        'retry_count',
        'created_by',
        'template_type',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->job_uuid)) {
                $model->job_uuid = (string) Str::uuid();
            }
        });
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isPrinted(): bool { return $this->status === 'printed'; }
    public function isFailed(): bool { return $this->status === 'failed'; }
}
```

### C. Barcode Service (`App\Services\Barcode\PrintJobService.php`)
Service ini mengelola pembuatan antrean, pengklaiman secara atomik (`lockForUpdate`), dan pemulihan jika ada job yang macet (`processing` lebih dari 5 menit).

```php
<?php

namespace App\Services\Barcode;

use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PrintJobService
{
    /**
     * Membuat print job baru untuk TSC.
     */
    public function createTscJob(string $payload, string $printerName, string $templateType, int $copies = 1): PrintJob
    {
        return PrintJob::create([
            'printer_name' => $printerName,
            'payload_tspl' => $payload,
            'payload_hash' => hash('sha256', $payload),
            'copies' => $copies,
            'status' => 'pending',
            'template_type' => $templateType,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Klaim atomik untuk job antrean berikutnya.
     * Hanya memperbolehkan job kurang dari 10 menit untuk mencegah re-print job usang.
     */
    public function claimNextJob(string $machineId, string $printerName): ?PrintJob
    {
        $tenMinutesAgo = now()->subMinutes(10);

        return DB::transaction(function () use ($machineId, $printerName, $tenMinutesAgo) {
            $job = PrintJob::where('status', 'pending')
                ->where('printer_name', $printerName)
                ->where('created_at', '>=', $tenMinutesAgo)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($job) {
                $job->update([
                    'status' => 'processing',
                    'claimed_by_machine' => $machineId,
                    'claimed_at' => now(),
                ]);
            }
            
            return $job;
        });
    }

    /**
     * Mengembalikan status job yang stuck di 'processing' lebih dari 5 menit kembali ke 'pending'.
     */
    public function recoverStaleJobs(): int
    {
        return PrintJob::where('status', 'processing')
            ->where('claimed_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'pending',
                'claimed_by_machine' => null,
                'claimed_at' => null,
            ]);
    }

    public function markPrinted(int $id): bool
    {
        return PrintJob::where('id', $id)->update([
            'status' => 'printed',
            'printed_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $error): bool
    {
        return PrintJob::where('id', $id)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }

    public function getStats(): array
    {
        return [
            'pending' => PrintJob::where('status', 'pending')->count(),
            'processing' => PrintJob::where('status', 'processing')->count(),
            'printed_today' => PrintJob::where('status', 'printed')->whereDate('printed_at', today())->count(),
            'failed' => PrintJob::where('status', 'failed')->count(),
        ];
    }
}
```

### D. Render Kode TSPL (`App\Services\Barcode\Renderers\TsplRenderer.php`)
Ini adalah renderer yang menerjemahkan data database menjadi raw command **TSPL** untuk TSC TE200 (ukuran standar label barang: 50x30 mm).

```php
<?php

namespace App\Services\Barcode\Renderers;

class TsplRenderer
{
    private const FONT_TITLE = '2'; // Font bawaan TSC sedang
    private const FONT_NORMAL = '1'; // Font bawaan TSC kecil

    public function render(array $data, string $labelVariant): string
    {
        if ($labelVariant === 'ITEM_LABEL') {
            return $this->renderItemLabel($data);
        }
        throw new \InvalidArgumentException("Format label tidak didukung: {$labelVariant}");
    }

    private function renderItemLabel(array $data): string
    {
        // Validasi ketersediaan data wajib
        foreach (['item_name', 'erp_code', 'barcode', 'last_stock_in_date'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field wajib kurang: {$field}");
            }
        }

        // Sanitasi teks agar tidak merusak command TSPL
        $nameLines = $this->formatItemName($data['item_name'], 24); // Maksimal 24 karakter per baris
        $erp = $this->sanitize($data['erp_code']);
        $barcode = $this->sanitize($data['barcode']);
        $lastIn = $this->sanitize($data['last_stock_in_date']);
        $bin = $this->sanitize($data['bin_code'] ?? '-');

        $cmds = [];
        $cmds[] = "SIZE 50 mm, 30 mm";     // Ukuran label thermal
        $cmds[] = "GAP 3 mm, 0";           // Jarak gap antar label
        $cmds[] = "DIRECTION 1,0";         // Orientasi cetak
        $cmds[] = "REFERENCE 0,0";         // Titik acuan koordinat
        $cmds[] = "OFFSET 0 mm";
        $cmds[] = "CLS";                   // Clear screen buffer printer
        $cmds[] = "SET TEAR ON";           // Aktifkan tear-bar cutter
        
        // Render Nama Item (Maksimal 2 baris)
        $cmds[] = "TEXT 10,16,\"" . self::FONT_TITLE . "\",0,1,1,\"" . $nameLines['line1'] . "\"";
        
        $erpY = 68;
        if (!empty($nameLines['line2'])) {
            $cmds[] = "TEXT 10,40,\"" . self::FONT_TITLE . "\",0,1,1,\"" . $nameLines['line2'] . "\"";
            $erpY = 92; // Turunkan posisi teks ERP jika ada baris kedua nama item
        }

        $cmds[] = "TEXT 10,$erpY,\"" . self::FONT_TITLE . "\",0,1,1,\"ERP: $erp\"";
        
        // Render Barcode Code128 (Lebar garis 3, tinggi barcode 50 dots)
        $cmds[] = "BARCODE 35,128,\"128\",50,0,0,3,3,\"$barcode\"";
        
        // Teks human-readable di bawah barcode
        $barcodeTextX = max(120, 220 - (mb_strlen($barcode) * 8));
        $cmds[] = "TEXT $barcodeTextX,182,\"" . self::FONT_TITLE . "\",0,1,1,2,\"$barcode\"";
        
        // Footer info (Tanggal Masuk & Kode Bin)
        $cmds[] = "TEXT 10,225,\"" . self::FONT_NORMAL . "\",0,1,1,\"Last In: $lastIn\"";
        $cmds[] = "TEXT 390,225,\"" . self::FONT_NORMAL . "\",0,1,1,3,\"Bin: $bin\"";
        
        $cmds[] = "PRINT 1,1"; // Cetak 1 lembar (jumlah salinan akan di-replace di service utama)

        return implode("\r\n", $cmds) . "\r\n";
    }

    private function formatItemName(string $name, int $limitPerLine = 24): array
    {
        $name = strtoupper($this->sanitize($name));
        $words = explode(' ', $name);
        
        $lines = ['line1' => '', 'line2' => ''];
        $currentLine = 'line1';

        foreach ($words as $word) {
            if (empty($word)) continue;
            $space = $lines[$currentLine] === '' ? '' : ' ';
            $proposed = $lines[$currentLine] . $space . $word;
            
            if (mb_strlen($proposed) <= $limitPerLine) {
                $lines[$currentLine] = $proposed;
            } else {
                if ($currentLine === 'line1') {
                    $currentLine = 'line2';
                    if (mb_strlen($word) > $limitPerLine) {
                        $lines[$currentLine] = mb_substr($word, 0, $limitPerLine - 3) . '...';
                        break;
                    }
                    $lines[$currentLine] = $word;
                } else {
                    $lines['line2'] = mb_substr($lines['line2'], 0, $limitPerLine - 3) . '...';
                    break;
                }
            }
        }

        return $lines;
    }

    private function sanitize(string $value): string
    {
        $value = str_replace(['"', "\r", "\n"], '', $value);
        return preg_replace('/[^\x20-\x7E]/', '', $value); // Hanya karakter ASCII readable
    }
}
```

### E. API Controller (`App\Http\Controllers\Api\PrintJobApiController.php`)
Menyediakan route jembatan yang stateless dan cepat untuk diakses Windows Agent di LAN (Lokal).

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Services\Barcode\PrintJobService;
use Illuminate\Http\Request;

class PrintJobApiController extends Controller
{
    public function __construct(
        private readonly PrintJobService $printJobService
    ) {}

    public function stats()
    {
        return response()->json($this->printJobService->getStats());
    }

    public function recover()
    {
        $count = $this->printJobService->recoverStaleJobs();
        return response()->json([
            'message' => 'Recovery completed',
            'recovered_count' => $count
        ]);
    }

    public function claim(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|string',
            'printer_name' => 'required|string',
        ]);

        $job = $this->printJobService->claimNextJob(
            $request->machine_id,
            $request->printer_name
        );

        if (!$job) {
            return response()->noContent(); // Status 204 jika tidak ada antrean
        }

        return response()->json($job);
    }

    public function complete(int $id)
    {
        $this->printJobService->markPrinted($id);
        return response()->json(['message' => 'Job marked as completed']);
    }

    public function failed(Request $request, int $id)
    {
        $request->validate(['error' => 'required|string']);
        $this->printJobService->markFailed($id, $request->error);
        return response()->json(['message' => 'Job marked as failed']);
    }
}
```

### F. Routing API (`routes/api.php`)
Definisikan endpoint tanpa middleware auth berlebih (agar cepat), namun batasi hanya untuk network lokal (LAN) jika diperlukan:

```php
use App\Http\Controllers\Api\PrintJobApiController;

// Agent Print Bridge API (Stateless & Unprotected untuk LAN)
Route::prefix('print-jobs')->group(function () {
    Route::post('/recover', [PrintJobApiController::class, 'recover']);
    Route::post('/claim', [PrintJobApiController::class, 'claim']);
    Route::post('/{id}/complete', [PrintJobApiController::class, 'complete']);
    Route::post('/{id}/failed', [PrintJobApiController::class, 'failed']);
    Route::get('/stats', [PrintJobApiController::class, 'stats']);
});
```

---

## 3. Bagian 2: Implementasi Windows Print Agent (Python Client)

Buat satu folder terpisah bernama `print-agent` di PC Windows client Anda.

### A. file `requirements.txt`
```text
requests
pywin32
```

### B. file `config.json`
Konfigurasikan sesuai alamat IP Laravel server Anda, identitas PC ini, nama printer Windows yang terdeteksi, dan waktu interval polling.

```json
{
  "server_url": "http://10.88.8.46:6031/api",
  "machine_id": "WAREHOUSE-PC-01",
  "printer_name": "TSC TE200",
  "poll_interval": 2
}
```

### C. file `agent.py`
Logika inti client untuk melakukan pooling antrean, menerima payload TSPL, mengirimkannya ke printer lokal Windows via API Win32, serta melaporkan status balik ke server.

```python
import time
import json
import requests
import win32print
import hashlib
import os

# Membaca konfigurasi lokal
config_path = os.path.join(os.path.dirname(__file__), 'config.json')
with open(config_path, 'r') as f:
    CONFIG = json.load(f)

SERVER_URL = CONFIG['server_url']
MACHINE_ID = CONFIG['machine_id']
PRINTER_NAME = CONFIG['printer_name']
POLL_INTERVAL = CONFIG['poll_interval']
LOG_FILE = "agent.log"

def log(msg):
    """Mencatat aktivitas log ke konsol dan file agent.log."""
    timestamp = time.strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{timestamp}] {msg}"
    print(line)
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception as e:
        print(f"Gagal menulis ke file log: {e}")

def run_recovery():
    """Memicu pemulihan tugas-tugas stuck (stale) di server saat agent pertama kali menyala."""
    try:
        log("[QUEUE] Menjalankan startup recovery...")
        resp = requests.post(f"{SERVER_URL}/print-jobs/recover", timeout=10)
        if resp.status_code == 200:
            count = resp.json().get('recovered_count', 0)
            log(f"[QUEUE] Startup recovery selesai: {count} job di-reset ke pending.")
        else:
            log(f"[QUEUE] Startup recovery gagal: HTTP {resp.status_code}")
    except Exception as e:
        log(f"[QUEUE] Startup recovery error: {e}")

def print_raw(data):
    """Mengirim byte perintah TSPL mentah langsung ke Windows Print Spooler."""
    try:
        log(f"Membuka printer: {PRINTER_NAME}")
        hPrinter = win32print.OpenPrinter(PRINTER_NAME)
        try:
            log(f"Mengirim RAW TSPL ke printer...")
            # 'RAW' menginstruksikan Windows Spooler untuk tidak memproses payload sebagai gambar/dokumen, 
            # melainkan mengirimkannya langsung apa adanya ke printer.
            hJob = win32print.StartDocPrinter(hPrinter, 1, ("TSC Industrial Print", None, "RAW"))
            win32print.StartPagePrinter(hPrinter)
            
            # Gunakan encoding cp437 (Code Page 437) untuk kompatibilitas printer thermal maksimal
            win32print.WritePrinter(hPrinter, data.encode('cp437'))
            
            win32print.EndPagePrinter(hPrinter)
            win32print.EndDocPrinter(hPrinter)
            log(f"Spooling RAW berhasil diselesaikan.")
        finally:
            win32print.ClosePrinter(hPrinter)
        return True
    except Exception as e:
        return str(e)

def process_jobs():
    log(f"Agent Aktif. Machine: {MACHINE_ID}, Printer Target: {PRINTER_NAME}")
    log(f"URL Polling Server: {SERVER_URL}")

    # Validasi eksistensi printer di sistem operasi Windows sebelum mulai loop
    try:
        printers = [p[2] for p in win32print.EnumPrinters(2)]
        if PRINTER_NAME not in printers:
            log(f"ERROR CRITICAL: Printer '{PRINTER_NAME}' tidak ditemukan di Windows ini.")
            log(f"Daftar printer terdeteksi: {', '.join(printers)}")
            exit(1)
    except Exception as e:
        log(f"Gagal mendeteksi printer terpasang: {e}")
        exit(1)

    # Jalankan recovery job macet saat startup
    run_recovery()
    
    while True:
        try:
            # 1. Claim job antrean dari server
            resp = requests.post(f"{SERVER_URL}/print-jobs/claim", json={
                "machine_id": MACHINE_ID,
                "printer_name": PRINTER_NAME
            }, timeout=10)

            if resp.status_code == 200:
                job = resp.json()
                job_id = job['id']
                payload = job['payload_tspl']
                expected_hash = job['payload_hash']

                log(f"Job berhasil diklaim: ID {job_id}")

                # 2. Verifikasi Integritas Data (SHA256)
                actual_hash = hashlib.sha256(payload.encode('utf-8')).hexdigest()
                if actual_hash != expected_hash:
                    error_msg = f"Integritas gagal (Hash mismatch). Harap cek jaringan. ID: {job_id}"
                    requests.post(f"{SERVER_URL}/print-jobs/{job_id}/failed", json={"error": error_msg})
                    log(error_msg)
                    continue

                # 3. Kirim ke printer
                result = print_raw(payload)

                if result is True:
                    # 4. Laporkan sukses
                    requests.post(f"{SERVER_URL}/print-jobs/{job_id}/complete")
                    log(f"Job {job_id} berhasil dicetak.")
                else:
                    # Laporkan gagal beserta log errornya
                    requests.post(f"{SERVER_URL}/print-jobs/{job_id}/failed", json={"error": result})
                    log(f"Job {job_id} GAGAL dicetak: {result}")

            elif resp.status_code == 204:
                # HTTP 204: Tidak ada antrean pending
                pass
            else:
                log(f"Error Server: HTTP {resp.status_code}. Response: {resp.text[:100]}")

        except Exception as e:
            log(f"Koneksi/Polling Error: {e}")

        time.sleep(POLL_INTERVAL)

if __name__ == "__main__":
    process_jobs()
```

---

## 4. Bagian 3: Kompilasi Executable (`.exe`) dan Deployment Windows

Agar operator tidak terganggu dengan tampilan CMD Python yang terbuka saat bekerja, kita perlu mengompilasi file Python tersebut menjadi file executable Windows background (`.exe`).

### A. Langkah Build dengan PyInstaller
1. Masuk ke folder `print-agent` di PC target lewat PowerShell/CMD.
2. Install library pembantu build:
   ```bash
   pip install pyinstaller
   ```
3. Lakukan build menggunakan flag `--noconsole` agar berjalan tanpa memunculkan window konsol (CMD) dan `--onefile` untuk menjadikannya satu berkas exe portabel:
   ```bash
   pyinstaller --onefile --noconsole agent.py
   ```
4. Setelah selesai, file exe akan terbentuk di folder `dist\agent.exe`.
5. Buat folder produksi di PC target, contoh: `C:\TSC-Agent\` dan salin file berikut ke dalamnya:
   - `agent.exe`
   - `config.json` (konfigurasi yang benar)
   - *Catatan: file `agent.log` akan dibuat otomatis di folder tersebut saat dijalankan.*

---

## 5. Bagian 4: Menjalankan Otomatis di Windows (Auto Start on Boot/Login)

Sangat direkomendasikan menggunakan **Windows Task Scheduler** daripada folder startup manual, karena Windows Task Scheduler memiliki ketahanan auto-restart jika program crash.

### Langkah Setup Task Scheduler:
1. Buka **Task Scheduler** di Windows.
2. Klik **Create Task...** di menu Actions sebelah kanan.
3. Tab **General**:
   - Name: `TSC Print Agent`
   - Pilih opsi: **Run whether user is logged on or not**
   - Centang: **Run with highest privileges** (penting agar bisa mengakses port USB printer tanpa kendala hak akses).
4. Tab **Triggers**:
   - Klik **New...**
   - Begin the task: **At log on**
5. Tab **Actions**:
   - Klik **New...**
   - Action: **Start a program**
   - Program/script: Browse dan pilih `C:\TSC-Agent\agent.exe`
   - **PENTING**: Isi kolom **Start in (optional)** dengan path foldernya: `C:\TSC-Agent` (tanpa tanda petik). Jika kolom ini dikosongkan, agent tidak akan bisa membaca file `config.json` di sebelahnya!
6. Tab **Settings**:
   - Centang: **If the task fails, restart every:** `1 minute`
   - Set **Attempt to restart up to:** `999` times.
7. Klik **OK** dan masukkan password Administrator PC tersebut untuk mengonfirmasi penyimpanan task.
8. Jalankan task pertama kali secara manual dengan menekan tombol **Run**. Cek apakah `agent.log` terbentuk di folder `C:\TSC-Agent`.

---

## 6. Penanganan Masalah & FAQ (Troubleshooting)

1. **Printer Tidak Merespon Tapi Log Berhasil Cetak**:
   - Pastikan nama printer di file `config.json` (`printer_name`) sama persis dengan nama printer yang terdaftar di sistem Windows Anda (cek di Control Panel -> Devices and Printers).
   - Pastikan printer menyala, kabel USB terhubung dengan baik, dan kertas thermal tidak dalam kondisi macet (led indikator printer berwarna hijau stabil, tidak merah).

2. **Klaim Antrean Terlalu Lambat**:
   - Interval default polling adalah `2` detik (`poll_interval` di config). Jika printer butuh lebih responsif, Anda bisa menurunkannya ke `1` detik. Namun hindari di bawah 1 detik untuk menghindari overload request pada server Laravel.

3. **Status Antrean Macet (Stuck) di Processing**:
   - Hal ini biasanya terjadi jika agent mati mendadak saat sedang memproses cetak. Server memiliki mekanisme **Recovery**. Saat agent dihidupkan ulang, ia memanggil route `/api/print-jobs/recover` yang secara otomatis mengembalikan semua pekerjaan yang macet lebih dari 5 menit kembali menjadi status `pending` agar bisa diklaim ulang.

4. **Pencetakan Ganda (Duplicate Print)**:
   - Pastikan tidak ada lebih dari 1 proses `agent.exe` yang aktif di PC yang sama. Cek di Windows Task Manager dan matikan proses agent yang menggantung sebelum menjalankan Task Scheduler.
