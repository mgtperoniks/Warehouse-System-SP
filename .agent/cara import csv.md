# Panduan Import Data Master & Manajemen Stok (CSV)

Dokumen ini berisi rangkuman diskusi mengenai standar operasional prosedur (SOP) pengisian data barang dan manajemen stok di Warehouse System.

## 1. Spesifikasi File CSV
*   **Ekstensi:** `.csv` (Comma Separated Values).
*   **Pemisah:** Koma (`,`).
*   **Encoding:** UTF-8 (Rekomendasi) agar karakter tidak berantakan.

## 2. Struktur Kolom (Header)
Sistem menggunakan nama header untuk identifikasi. Pastikan nama kolom di baris pertama CSV sesuai dengan daftar di bawah (huruf kecil semua):

| Kolom | Keterangan | Kepentingan |
| :--- | :--- | :--- |
| **`name`** | Nama Master Barang (contoh: `BEARING 6204 ZZ`) | **Wajib** |
| **`erp_code`** | Kode Produk dari ERP Lama (untuk sinkronisasi) | **Sangat Penting** |
| **`brand`** | Merk Barang (contoh: `KOYO`, `NSK`, `SKF`) | Opsional |
| **`barcode`** | Kode barcode fisik pada kemasan | Opsional |
| **`sku`** | Kode unik internal gudang (bisa disamakan dengan ERP Code) | Opsional |
| **`unit`** | Satuan (contoh: `PCS`, `SET`, `LTR`) | Opsional |
| **`description`** | Deskripsi tambahan mengenai barang | Opsional |
| **`supplier_name`** | Nama supplier penyedia barang | Opsional |
| **`initial_stock`** | Jumlah stok awal (angka saja) | Opsional |
| **`bin_code`** | Kode Rak/Lokasi (contoh: `A-01-B`) | Opsional* |

*> Catatan: `initial_stock` hanya akan masuk ke sistem jika `bin_code` juga diisi (sistem wajib memiliki lokasi rak untuk tiap barang).*

---

## 3. Contoh Baris CSV
Berikut adalah contoh struktur data yang benar:
```csv
name,erp_code,brand,barcode,sku,unit,description,supplier_name,initial_stock,bin_code
BEARING 6204 ZZ,5.01.KOYO.6204,KOYO,8881234567,5.01.6204,PCS,Bearing for Motor,PT JAYA ABADI,10,A-01-01
BEARING 6204 ZZ,5.01.NSK.6204,NSK,8887654321,5.01.6204,PCS,Bearing for Motor,PT MAJU TERUS,5,A-01-02
```

---

## 4. Konsep Master Barang & Variant (Parent-Child)
Satu jenis fungsi barang bisa memiliki banyak merk. Untuk menjaga kerapian data:
*   **Gunakan `name` yang sama** untuk barang yang fungsinya identik.
*   **Gunakan `erp_code` yang berbeda** untuk membedakan identitas merk (sesuai ERP lama).

**Contoh Kasus:**
Jika Anda punya 3 merk untuk Bearing 6204 ZZ:
1. `name`: `BEARING 6204 ZZ`, `brand`: `KOYO`, `erp_code`: `5.01.KOYO.6204`
2. `name`: `BEARING 6204 ZZ`, `brand`: `NSK`, `erp_code`: `5.01.NSK.6204`

**Hasil di Sistem:** Saat admin mencari "6204 ZZ", hanya muncul satu baris barang, namun saat diklik akan muncul pilihan stok per Merk (KOYO/NSK).

---

## 5. Prosedur Scan & Pengurangan Stok
Untuk menjaga akurasi data harian yang akan ditransfer kembali ke ERP lama:
1.  **Berdasarkan Barcode:** Sistem melacak pengeluaran berdasarkan barcode/merk, bukan hanya nama barang.
2.  **Scan per Merk:** Jika admin mengeluarkan 6 pcs barang yang terdiri dari 5 pcs merk KOYO dan 1 pcs merk NSK, admin **wajib scan kedua merk tersebut**.
3.  **Akurasi Keuangan:** Jangan pernah menggabungkan stok berbeda merk menjadi satu kode di sistem ini jika di ERP lama mereka memiliki kode yang berbeda. Hal ini krusial agar nilai aset di ERP lama tetap akurat.

---

## 6. Tips Operasional
*   Jika merk berbeda memiliki barcode yang sama dari pabrik, tempelkan **Label QR Code Internal** untuk membedakan tiap merk saat scan.
*   Pastikan admin selalu melakukan "Hard Refresh" (**CTRL + F5**) jika mengakses sistem dari komputer Windows 7 guna memastikan tampilan warna (indikator stok) muncul dengan benar.

---
*Terakhir diupdate: 20 April 2026*
