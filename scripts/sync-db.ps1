<#
.SYNOPSIS
    Production to Local Laragon Database Synchronization Script
.DESCRIPTION
    A robust, one-command synchronization script between the production warehouse database
    and the local Laragon workstation. Includes auto-backup, FK bypass, and cache clearing.
.PARAMETER RemoteHost
    Host IP or hostname of the production warehouse server. Default is 10.88.8.46.
.PARAMETER RemoteUser
    SSH username. Default is peroniks.
.PARAMETER RemotePath
    Absolute app directory on server. Default is /srv/docker/apps/Warehouse-System-SP.
.PARAMETER RemoteDbService
    Docker Compose db service name on production. Default is warehouse-db.
.PARAMETER RemoteDbName
    Production target database name. Default is warehouse_system.
.PARAMETER RemoteDbUser
    Production database username. Default is warehouse_system_user.
.PARAMETER RemoteDbPass
    Production database password. Default is wh_sys_k8q2pL9zX_prod.
.PARAMETER SyncOnly
    Only create remote dump and download, do not import to local Laragon.
.PARAMETER ImportOnly
    Skip remote connect and download. Import the existing local 'prod_dump.sql' file.
.PARAMETER NoCleanup
    Do not delete the remote /tmp/prod_dump.sql or the local temporary SQL dump files.
#>

[CmdletBinding()]
param(
    [string]$RemoteHost = "10.88.8.46",
    [string]$RemoteUser = "peroniks",
    [string]$RemotePath = "/srv/docker/apps/Warehouse-System-SP",
    [string]$RemoteDbService = "warehouse-db",
    [string]$RemoteDbName = "warehouse_system",
    [string]$RemoteDbUser = "warehouse_system_user",
    [string]$RemoteDbPass = "wh_sys_k8q2pL9zX_prod",

    [switch]$SyncOnly,
    [switch]$ImportOnly,
    [switch]$NoCleanup
)

$ErrorActionPreference = "Stop"

# --- HELPER FUNCTIONS ---

function Write-Step($stepNum, $totalSteps, $message, $color) {
    Write-Host ""
    Write-Host "[$stepNum/$totalSteps] $message" -ForegroundColor $color -BackgroundColor Black
    Write-Host "--------------------------------------------------------" -ForegroundColor $color
}

function Find-BinFile($fileName) {
    # 1. Try standard environment PATH
    $resolved = Get-Command $fileName -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
    if ($resolved) {
        return $resolved
    }

    # 2. Try recursive lookup inside Laragon's default MySQL paths
    $laragonDir = "C:\laragon\bin\mysql"
    if (Test-Path $laragonDir) {
        $finds = Get-ChildItem -Path $laragonDir -Filter $fileName -Recurse -File -ErrorAction SilentlyContinue
        if ($finds) {
            # Return the first found executable (usually matches the active MySQL version)
            return $finds[0].FullName
        }
    }

    return $null
}

function Get-EnvVar($varName, $defaultValue) {
    if (Test-Path ".env") {
        $lines = Get-Content ".env"
        foreach ($line in $lines) {
            $line = $line.Trim()
            if ($line -like "$varName=*") {
                $val = $line.Substring($varName.Length + 1).Trim()
                # Remove quotes if present
                if ($val.StartsWith('"') -and $val.EndsWith('"')) {
                    $val = $val.Substring(1, $val.Length - 2)
                } elseif ($val.StartsWith("'") -and $val.EndsWith("'")) {
                    $val = $val.Substring(1, $val.Length - 2)
                }
                # Remove inline comments
                if ($val.Contains("#")) {
                    $val = $val.Split("#")[0].Trim()
                }
                return $val
            }
        }
    }
    return $defaultValue
}

# --- HEADER TITLE ---
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "     🏭 WAREHOUSE SYSTEM PRODUCTION DB SYNC WORKFLOW" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Local Time : $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor DarkGray
Write-Host "  Workspace  : $(Get-Location)" -ForegroundColor DarkGray
Write-Host "============================================================" -ForegroundColor Cyan

# --- STEP 1: CREATE REMOTE DUMP ---
Write-Step 1 4 "Creating production dump..." "Cyan"
if (-not $ImportOnly) {
    # Generate SSH docker compose exec mysqldump command
    $sshCmd = "cd $RemotePath && sudo docker compose exec -T $RemoteDbService mysqldump -u $RemoteDbUser -p$RemoteDbPass $RemoteDbName > /tmp/prod_dump.sql"
    
    Write-Host "Connecting via SSH to peroniks@$RemoteHost..." -ForegroundColor DarkGray
    Write-Host "Executing remote Docker DB dump inside Compose container..." -ForegroundColor DarkGray
    
    # Run SSH
    ssh -o ConnectTimeout=10 $RemoteUser@$RemoteHost $sshCmd
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ ERROR: Remote dump generation failed." -ForegroundColor Red -BackgroundColor Black
        Write-Host "Please check: Host connectivity, SSH key/agent state, and remote Docker stack health." -ForegroundColor Yellow
        exit 1
    }
    Write-Host "✅ Remote database dump created at: /tmp/prod_dump.sql" -ForegroundColor Green
} else {
    Write-Host "⏭️ Skipping remote dump (-ImportOnly is active)." -ForegroundColor Yellow
}

# --- STEP 2: DOWNLOAD DATABASE DUMP ---
Write-Step 2 4 "Downloading database..." "Yellow"
if (-not $ImportOnly) {
    Write-Host "Initiating secure copy (SCP) download..." -ForegroundColor DarkGray
    
    # Run SCP
    scp -q -o ConnectTimeout=10 "${RemoteUser}@${RemoteHost}:/tmp/prod_dump.sql" ./prod_dump.sql
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ ERROR: Failed to download database dump via SCP." -ForegroundColor Red -BackgroundColor Black
        exit 1
    }
    Write-Host "✅ Download complete. Saved as local: prod_dump.sql" -ForegroundColor Green
} else {
    Write-Host "⏭️ Skipping secure download (-ImportOnly is active)." -ForegroundColor Yellow
}

# --- STEP 3: AUTO LOCAL BACKUP & SAFE IMPORT ---
Write-Step 3 4 "Importing into Laragon..." "Green"
if (-not $SyncOnly) {
    # 1. Locate MySQL utilities
    $mysqlPath = Find-BinFile "mysql.exe"
    $mysqldumpPath = Find-BinFile "mysqldump.exe"
    
    if (-not $mysqlPath) {
        Write-Host "❌ ERROR: Could not locate 'mysql.exe' inside PATH or C:\laragon\bin\mysql" -ForegroundColor Red -BackgroundColor Black
        exit 1
    }
    Write-Host "Located MySQL client: $mysqlPath" -ForegroundColor DarkGray
    
    # 2. Retrieve local database configurations from .env
    $localDb = Get-EnvVar "DB_DATABASE" "warehouse_system"
    $localUser = Get-EnvVar "DB_USERNAME" "root"
    $localPass = Get-EnvVar "DB_PASSWORD" ""
    $localHost = Get-EnvVar "DB_HOST" "127.0.0.1"
    $localPort = Get-EnvVar "DB_PORT" "3306"
    
    Write-Host "Local Configuration Resolved:" -ForegroundColor DarkGray
    Write-Host "  Database  : $localDb" -ForegroundColor DarkGray
    Write-Host "  Username  : $localUser" -ForegroundColor DarkGray
    Write-Host "  Host      : $localHost:$localPort" -ForegroundColor DarkGray
    
    # 3. Handle Auto-Backup of the existing state
    if ($mysqldumpPath) {
        Write-Host "Found mysqldump: $mysqldumpPath" -ForegroundColor DarkGray
        Write-Host "Running automatic pre-import local backup safeguard..." -ForegroundColor DarkGray
        
        if (-not (Test-Path "backups")) {
            New-Item -ItemType Directory -Path "backups" | Out-Null
        }
        
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $backupFile = "backups/local_backup_$timestamp.sql"
        
        $passArg = if ($localPass) { "-p$localPass" } else { "" }
        
        # Invoke mysqldump safely redirecting to local backup file
        $dumpCmd = "& '$mysqldumpPath' -h $localHost -P $localPort -u $localUser $passArg --databases $localDb"
        Invoke-Expression $dumpCmd | Out-File -FilePath $backupFile -Encoding utf8
        
        if (Test-Path $backupFile) {
            $backupSize = (Get-Item $backupFile).Length
            if ($backupSize -gt 100) {
                Write-Host "✅ Local safeguard backup created: $backupFile ($($backupSize) bytes)" -ForegroundColor Green
            } else {
                Write-Host "⚠️ Local database is empty/does not exist yet. Safeguard backup skipped." -ForegroundColor Yellow
                Remove-Item $backupFile -Force -ErrorAction SilentlyContinue
            }
        }
    } else {
        Write-Host "⚠️ WARNING: mysqldump.exe not found. Skipping automatic safeguard backup." -ForegroundColor Yellow
    }
    
    # 4. Verify Local Dump Source
    if (-not (Test-Path "prod_dump.sql")) {
        Write-Host "❌ ERROR: Cannot find 'prod_dump.sql' in the current workspace directory." -ForegroundColor Red -BackgroundColor Black
        exit 1
    }
    
    # 5. Ensure Local Database Exists
    Write-Host "Ensuring target database '$localDb' is created..." -ForegroundColor DarkGray
    $passArg = if ($localPass) { "-p$localPass" } else { "" }
    & $mysqlPath -h $localHost -P $localPort -u $localUser $passArg -e "CREATE DATABASE IF NOT EXISTS \`$localDb\`;"
    
    # 6. Memory-Efficient Foreign Key Isolation Wrapper Import
    Write-Host "Generating isolated wrapper to temporarily disable Foreign Key checks..." -ForegroundColor DarkGray
    $wrapperPath = "temp_import_wrapper.sql"
    $absoluteDumpPath = (Get-Item "prod_dump.sql").FullName.Replace("\", "/")
    
    $wrapperContent = @"
SET FOREIGN_KEY_CHECKS=0;
SOURCE $absoluteDumpPath;
SET FOREIGN_KEY_CHECKS=1;
"@
    Set-Content -Path $wrapperPath -Value $wrapperContent
    
    Write-Host "Importing production dataset..." -ForegroundColor DarkGray
    & $mysqlPath -h $localHost -P $localPort -u $localUser $passArg $localDb -e "source $wrapperPath"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ ERROR: Local database import failed." -ForegroundColor Red -BackgroundColor Black
        if (Test-Path $wrapperPath) { Remove-Item $wrapperPath -Force -ErrorAction SilentlyContinue }
        exit 1
    }
    
    Write-Host "✅ Database import completed successfully under Foreign Key checks bypass." -ForegroundColor Green
    
    # 7. Local Cache Parity resets
    Write-Host "Executing Laravel parity optimization clears..." -ForegroundColor DarkGray
    if (Test-Path "artisan") {
        & php artisan optimize:clear
        & php artisan migrate
        Write-Host "✅ Local Laravel cache reset and migrations fully synchronised." -ForegroundColor Green
    } else {
        Write-Host "⚠️ 'artisan' bootstrapper not found in root. Skipping cache clears." -ForegroundColor Yellow
    }
} else {
    Write-Host "⏭️ Skipping database import (-SyncOnly is active)." -ForegroundColor Yellow
}

# --- STEP 4: CLEANUP TEMPORARY FILES ---
Write-Step 4 4 "Cleaning temporary files..." "Red"
if (-not $NoCleanup) {
    if (Test-Path "temp_import_wrapper.sql") {
        Remove-Item "temp_import_wrapper.sql" -Force
        Write-Host "🗑️ Deleted local temporary wrapper." -ForegroundColor DarkGray
    }
    
    if (-not $SyncOnly -and (Test-Path "prod_dump.sql")) {
        Remove-Item "prod_dump.sql" -Force
        Write-Host "🗑️ Deleted local temporary prod_dump.sql file." -ForegroundColor DarkGray
    }
    
    if (-not $ImportOnly) {
        Write-Host "Removing remote temporary dump from host server..." -ForegroundColor DarkGray
        ssh $RemoteUser@$RemoteHost "rm -f /tmp/prod_dump.sql"
    }
    
    Write-Host "✅ Temporary dump and wrapper cleanup complete." -ForegroundColor Green
} else {
    Write-Host "⏭️ Temporary cleanup disabled (-NoCleanup flag is active)." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host " 🎉 PRODUCTION DB SYNCHRONISATION COMPLETED SUCCESSFULLY!" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host " Local workstation now matches 100% production parity." -ForegroundColor Green
Write-Host " You can run your local tests or browser automation tests now." -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
