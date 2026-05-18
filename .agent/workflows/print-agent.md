---
description: SETUP_PRINT_AGENT.md
---



# Industrial Thermal Print Bridge Setup
## Warehouse-System-SP

Dokumentasi setup print agent untuk komputer admin/operator Windows yang terhubung langsung ke printer thermal TSC.

---

# ARCHITECTURE

```text
Laravel Server (Linux)
10.88.8.46:6031
        │
        │ HTTP API Queue
        ▼
Windows Print Agent
10.88.8.97
(agent.py / agent.exe)
        │
        │ RAW TSPL
        ▼
TSC TE244 Printer
FLOW
User klik PRINT dari Laravel
Laravel membuat print_jobs
Windows Agent polling queue
Agent claim job
Agent kirim RAW TSPL ke printer
Agent mark complete
SERVER REQUIREMENTS

Linux Server:

Laravel app running
API accessible

Endpoint:

http://10.88.8.46:6031/api

Test:

curl http://10.88.8.46:6031/api/print-jobs/stats

Expected:

{
  "pending": 0,
  "processing": 0,
  "printed_today": 15,
  "failed": 0
}
WINDOWS REQUIREMENTS

Install:

Python 3.11+
Printer driver TSC
Printer already tested from Windows

Test printer name:

import win32print
print(win32print.EnumPrinters(2))

Printer name MUST match exactly:

TSC TE244
PRINT AGENT LOCATION

Recommended production path:

C:\TSC-Agent\

Files:

C:\TSC-Agent\
 ├── agent.exe
 ├── config.json
 └── agent.log
CONFIG.JSON

Example:

{
  "server_url": "http://10.88.8.46:6031/api",
  "machine_id": "WAREHOUSE-PC-01",
  "printer_name": "TSC TE244",
  "poll_interval": 2
}
BUILD EXE

Inside print-agent folder:

pip install pyinstaller

Build:

pyinstaller --onefile --noconsole agent.py

Result:

dist\agent.exe

Copy to:

C:\TSC-Agent\
TEST MANUAL

Run:

agent.exe

Expected log:

Agent started
Polling URL: http://10.88.8.46:6031/api

Then test print from Laravel.

AUTO START ON WINDOWS LOGIN

Use:

Windows Task Scheduler

DO NOT use:

Startup folder
Manual CMD
BAT file only
TASK SCHEDULER SETUP
General

Name:

TSC Print Agent

Enable:

Run whether user is logged on or not
Run with highest privileges
Trigger
At log on
Action

Program:

C:\TSC-Agent\agent.exe

Start in:

C:\TSC-Agent
Settings

Enable:

Restart if task fails
Retry every 1 minute
Retry 999 times
LOG FILE

Log file:

C:\TSC-Agent\agent.log

Monitor:

Job claimed
Job completed
Printer opened
RAW spool completed
COMMON PROBLEMS
1. Agent tidak print

Check:

http://10.88.8.46:6031/api/print-jobs/stats

Check pending queue.

2. Printer not found

Verify exact Windows printer name:

win32print.EnumPrinters(2)

Update:

"printer_name": "TSC TE244"
3. Firewall blocked

Open server port:

sudo ufw allow 6031/tcp
4. Queue stuck

Clear pending jobs:

UPDATE print_jobs
SET status='failed'
WHERE status='pending';
5. Agent jalan tapi tidak claim

Check:

server_url

Must be:

http://10.88.8.46:6031/api

NOT:

10.88.8.97
localhost
6032
https
PRODUCTION NOTES

Server:

Linux = queue authority
Windows = print executor

This architecture is intentional.

Printer NEVER directly connected to Linux server.

CURRENT LABEL STANDARD

ITEM_LABEL:

Thermal 50x30 mm
Max 2 lines item name
Large barcode
Human readable barcode
TSPL RAW printing

BIN_LABEL:

80x50 mm
FUTURE ROADMAP

Potential upgrades:

Multi printer routing
Print dashboard
Auto reconnect
Printer offline detection
Queue analytics
Reprint history
Agent auto-update
Tray icon monitor
Log rotation
Multi warehouse print hubs
IMPORTANT

If print suddenly duplicates:

Check Laravel logs
Check pending queue
Check Task Scheduler duplicate tasks
Ensure only ONE agent instance running
QUICK HEALTH CHECK
Server
curl http://10.88.8.46:6031/api/print-jobs/stats
Windows

Check:

agent.log
Printer

Test print directly from Windows.

FINAL STATUS

Industrial Print Bridge:
✅ WORKING
✅ Linux Queue
✅ Windows Thermal Agent
✅ RAW TSPL
✅ Auto Queue Claim
✅ Production Ready