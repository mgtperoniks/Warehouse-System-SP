# ============================================================
# WAREHOUSE SYSTEM - PRODUCTION DATABASE SYNC
# ============================================================

$REMOTE_USER = "peroniks"
$REMOTE_HOST = "10.88.8.46"

$REMOTE_PATH = "/srv/docker/apps/Warehouse-System-SP"

# Docker database container name
$REMOTE_DB_CONTAINER = "warehouse-db"

# Production database credentials
$DB_NAME = "warehouse_system"
$DB_USER = "warehouse_system_user"
$DB_PASS = "wh_sys_k8q2pL9zX_prod"

# Local Laragon database credentials
$LOCAL_DB_NAME = "warehouse_system-sp"
$LOCAL_DB_USER = "root"
$LOCAL_DB_PASS = "123456788"

# Temporary dump file
$LOCAL_SQL = "prod_dump.sql"

Write-Host ""
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "   WAREHOUSE SYSTEM PRODUCTION DATABASE SYNC" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

# ============================================================
# FIND MYSQL BINARIES
# ============================================================

$mysqlPath = "mysql"
$mysqldumpPath = "mysqldump"

if (-not (Get-Command "mysql" -ErrorAction SilentlyContinue)) {

    Write-Host ""
    Write-Host "Searching Laragon MySQL..." -ForegroundColor Gray

    $mysqlExe = Get-ChildItem `
        "C:\laragon\bin\mysql" `
        -Filter "mysql.exe" `
        -Recurse `
        -ErrorAction SilentlyContinue |
    Select-Object -First 1

    if ($mysqlExe) {

        $mysqlPath = $mysqlExe.FullName

        Write-Host ("Found mysql.exe -> {0}" -f $mysqlPath) `
            -ForegroundColor Green
    }
    else {

        Write-Host ""
        Write-Host "ERROR: mysql.exe not found." -ForegroundColor Red
        exit
    }
}

if (-not (Get-Command "mysqldump" -ErrorAction SilentlyContinue)) {

    $mysqldumpExe = Get-ChildItem `
        "C:\laragon\bin\mysql" `
        -Filter "mysqldump.exe" `
        -Recurse `
        -ErrorAction SilentlyContinue |
    Select-Object -First 1

    if ($mysqldumpExe) {
        $mysqldumpPath = $mysqldumpExe.FullName
    }
}

# ============================================================
# LOCAL BACKUP
# ============================================================

Write-Host ""
Write-Host "[0/4] Creating local safeguard backup..." `
    -ForegroundColor DarkCyan

if (-not (Test-Path "backups")) {
    New-Item -ItemType Directory -Path "backups" | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

$backupFile = "backups/local_backup_$timestamp.sql"

& $mysqldumpPath `
    -u $LOCAL_DB_USER `
    -h 127.0.0.1 `
    "--password=$LOCAL_DB_PASS" `
    --no-tablespaces `
    $LOCAL_DB_NAME `
    > $backupFile

if ($LASTEXITCODE -eq 0) {

    Write-Host ("Backup saved -> {0}" -f $backupFile) `
        -ForegroundColor Green
}
else {

    Write-Host "WARNING: Local backup failed." `
        -ForegroundColor Yellow
}

# ============================================================
# CREATE REMOTE DUMP
# ============================================================

Write-Host ""
Write-Host "[1/4] Creating dump on production server..." `
    -ForegroundColor Yellow

ssh $REMOTE_USER@$REMOTE_HOST `
    "docker exec -i $REMOTE_DB_CONTAINER mysqldump --no-tablespaces -u$DB_USER -p$DB_PASS $DB_NAME > $REMOTE_PATH/$LOCAL_SQL"

if ($LASTEXITCODE -ne 0) {

    Write-Host ""
    Write-Host "ERROR: Failed creating production dump." `
        -ForegroundColor Red

    exit
}

Write-Host "Production dump created." -ForegroundColor Green

# ============================================================
# DOWNLOAD DUMP
# ============================================================

Write-Host ""
Write-Host "[2/4] Downloading production dump..." `
    -ForegroundColor Yellow

$scpSource = "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/${LOCAL_SQL}"

scp $scpSource .

if (-not (Test-Path $LOCAL_SQL)) {

    Write-Host ""
    Write-Host "ERROR: Download failed." `
        -ForegroundColor Red

    exit
}

Write-Host "Download completed." -ForegroundColor Green

# ============================================================
# IMPORT LOCAL DATABASE
# ============================================================

Write-Host ""
Write-Host "[3/4] Importing into Laragon..." `
    -ForegroundColor Yellow

Get-Content $LOCAL_SQL | `
    & $mysqlPath `
    -u $LOCAL_DB_USER `
    -h 127.0.0.1 `
    "--password=$LOCAL_DB_PASS" `
    $LOCAL_DB_NAME

if ($LASTEXITCODE -eq 0) {

    Write-Host ""
    Write-Host "SUCCESS: Database synchronized!" `
        -ForegroundColor Green
}
else {

    Write-Host ""
    Write-Host "ERROR: Import failed." `
        -ForegroundColor Red

    exit
}

# ============================================================
# CLEAR LARAVEL CACHE
# ============================================================

Write-Host ""
Write-Host "[4/4] Clearing Laravel caches..." `
    -ForegroundColor Yellow

php artisan optimize:clear

Write-Host "Laravel cache cleared." `
    -ForegroundColor Green

# ============================================================
# CLEANUP
# ============================================================

Write-Host ""
Write-Host "Cleaning temporary files..." `
    -ForegroundColor Gray

ssh $REMOTE_USER@$REMOTE_HOST `
    "rm -f $REMOTE_PATH/$LOCAL_SQL"

Remove-Item $LOCAL_SQL -ErrorAction SilentlyContinue

# ============================================================
# FINISHED
# ============================================================

Write-Host ""
Write-Host "==================================================" `
    -ForegroundColor Green

Write-Host " PRODUCTION DATABASE SYNC COMPLETE" `
    -ForegroundColor Green

Write-Host "==================================================" `
    -ForegroundColor Green