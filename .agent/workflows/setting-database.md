# 🗄️ Workflow: Penambahan Aplikasi ke Ekosistem Warehouse

Dokumen ini menjelaskan cara menambah aplikasi baru ke dalam ekosistem Warehouse agar dapat berbagi (sharing) database secara aman dan skalabel.

## 🏗️ Arsitektur Shared Database
Semua aplikasi di bawah domain Warehouse menggunakan **satu container MySQL terpusat** yang dikelola di:
`/srv/docker/apps/warehouse-infra`

Struktur folder infrastruktur:
- `docker-compose.yml`: Mendefinisikan kontainer `warehouse-db`.
- `mysql/init.sql`: Tempat mengatur pembuatan database, user, dan hak akses.

---

## 🚀 Cara Menambah Aplikasi Baru (Misal: Gudang Finish Good)

Jangan membuat container database baru untuk aplikasi baru. Ikuti langkah ini:

### 1. Konsultasi AI (PENTING)
Sebelum mengetik password atau membuat database di `.env` baru, **minta AI Agent untuk membacakan konfigurasi yang ada**.
- **Tujuan**: Agar penamaan user, database, dan hak akses tetap konsisten dan rapi.
- **Interkoneksi**: Dengan sistem terpusat ini, aplikasi lain (misal: *Aplikasi Jadwal Maintenance*) bisa membaca data stock (misal: *Sparepart*) secara real-time karena berada di satu "rumah" (`warehouse-db`).

### 2. Update `init.sql` di `/warehouse-infra`
Tambahkan database dan user baru di file `init.sql`. Contoh penambahan:
```sql
-- Buat database baru
CREATE DATABASE IF NOT EXISTS warehouse_finish_good;

-- Buat user baru dengan password yang kuat
CREATE USER IF NOT EXISTS 'warehouse_fg_user'@'%' IDENTIFIED BY 'PASSWORD_KUAT_GENERATED';

-- Berikan hak akses terbatas (hanya ke DB sendiri)
GRANT ALL PRIVILEGES ON warehouse_finish_good.* TO 'warehouse_fg_user'@'%';
```

### 3. Konfigurasi `.env` Aplikasi Baru
Pastikan `.env` aplikasi baru merujuk ke database pusat:
```env
DB_CONNECTION=mysql
DB_HOST=warehouse-db
DB_PORT=3306
DB_DATABASE=warehouse_finish_good
DB_USERNAME=warehouse_fg_user
DB_PASSWORD=PASSWORD_KUAT_GENERATED
```

### 4. Jaringan (Network)
Semua aplikasi baru **harus bergabung** ke dalam jaringan eksternal `warehouse-net`:
```yaml
networks:
  warehouse-net:
    external: true
```

---

## 🔒 Aturan Keamanan
1. **No Root Access**: Aplikasi dilarang keras menggunakan user `root`.
2. **Dedicated User**: Satu aplikasi = satu user DB khusus.
3. **Internal network**: Database tidak dibuka ke port host (3306), komunikasi antar container hanya via `warehouse-net`.
4. **Least Privilege**: User hanya boleh melihat database miliknya sendiri kecuali ada kebutuhan integrasi lintas-app.
