---
description: Safe deployment workflow - push code to production server without breaking database
---

# 🔒 Aturan Emas: Safe Production Deployment

// turbo-all

## Prinsip Utama
1. **JANGAN PERNAH** jalankan `migrate:fresh` atau `migrate:rollback` di production
2. **SELALU** backup database sebelum deploy jika ada migration baru
3. **HANYA** jalankan `migrate` (tanpa flag) untuk migration baru di production
4. Perubahan PHP/Blade biasa **TIDAK PERLU** migration

---

## Langkah Deployment

### STEP 1: Commit & Push dari Local (Laragon)

```powershell
cd c:\laragon\www\qc-db

# Cek perubahan
git status

# Add semua perubahan
git add -A

# Commit dengan pesan deskriptif
git commit -m "feat: deskripsi singkat perubahan"

# Push ke production remote
git push prod main
```

### STEP 2: Backup Database di Server (WAJIB jika ada migration baru)

SSH ke server, lalu jalankan:

```bash
cd /srv/docker/apps/qc-db

# Backup database sebelum pull
sudo docker compose exec db mysqldump -u root -p[PASSWORD] qcdb > /home/peroniks/backups/qcdb_backup_$(date +%Y%m%d_%H%M%S).sql
```

### STEP 3: Pull & Update di Server

```bash
cd /srv/docker/apps/qc-db

# Pull kode terbaru
sudo git pull origin main

# Build & Restart (agar environment sinkron)
sudo docker compose build --no-cache
sudo docker compose up -d

# Hubungkan kembali symlink storage (penting untuk stamp/signature)
sudo docker compose exec app php artisan storage:link

# Clear semua cache
sudo docker compose exec app php artisan config:clear
sudo docker compose exec app php artisan view:clear
sudo docker compose exec app php artisan route:clear
sudo docker compose exec app php artisan cache:clear

# HANYA jika ada migration baru (BUKAN migrate:fresh!)
sudo docker compose exec app php artisan migrate

# Re-cache untuk production
sudo docker compose exec app php artisan config:cache
sudo docker compose exec app php artisan route:cache
```

### STEP 4: Verifikasi

1. Buka aplikasi di browser
2. Cek apakah fitur baru berfungsi
3. Cek apakah data lama masih ada

---

## ⚠️ PERINTAH BERBAHAYA - JANGAN GUNAKAN DI PRODUCTION

```bash
# ❌ JANGAN! Ini menghapus SEMUA data!
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan migrate:rollback
php artisan db:wipe
```

---

## Checklist Sebelum Deploy

- [ ] Sudah test di local (Laragon)?
- [ ] Ada migration baru? Jika ya, backup database dulu!
- [ ] Commit message sudah jelas?
- [ ] Push ke remote `prod` (bukan `origin`)?

---

## Recovery Jika Terjadi Masalah

```bash
# Restore database dari backup
sudo docker compose exec -T db mysql -u root -p[PASSWORD] qcdb < /home/peroniks/backups/qcdb_backup_YYYYMMDD_HHMMSS.sql

# Rollback ke commit sebelumnya (gunakan dengan hati-hati)
sudo git reset --hard HEAD~1
```
