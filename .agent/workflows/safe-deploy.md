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
cd c:\laragon\www\Warehouse-System-Sparepart

# Cek perubahan
git status

# Add semua perubahan
git add -A

# Commit dengan pesan deskriptif
git commit -m "feat: deskripsi singkat perubahan"

# Push ke production remote (biasanya prod)
git push prod main
```

### STEP 2: Backup Database di Server (WAJIB jika ada migration baru)

SSH ke server, lalu jalankan:

```bash
cd /srv/docker/apps/Warehouse-System-SP

# Backup database sebelum pull
# Password database: wh_sys_k8q2pL9zX_prod
sudo docker compose exec warehouse-db mysqldump -u warehouse_system_user -p[PASSWORD] warehouse_system > /home/peroniks/backups/warehouse_backup_$(date +%Y%m%d_%H%M%S).sql
```

### STEP 3: Pull & Update di Server

```bash
cd /srv/docker/apps/Warehouse-System-SP

# Pull kode terbaru
sudo git pull origin main

# Build & Restart (agar .env dan environment sinkron)
sudo docker compose build --no-cache
sudo docker compose up -d

# Hubungkan kembali symlink storage
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

1. Buka aplikasi di browser (http://10.88.8.46:6031)
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
- [ ] Push ke remote `prod`?

---

## Recovery Jika Terjadi Masalah

```bash
# Restore database dari backup
sudo docker compose exec -T warehouse-db mysql -u warehouse_system_user -p[PASSWORD] warehouse_system < /home/peroniks/backups/warehouse_backup_YYYYMMDD_HHMMSS.sql

# Rollback ke commit sebelumnya
sudo git reset --hard HEAD~1
```
