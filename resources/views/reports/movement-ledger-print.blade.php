<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KARTU STOK WMS - {{ $variant->erp_code }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 landscape;
            margin: 12mm 10mm 12mm 10mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            background: #fff;
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-helper {
            background: #fff8dc;
            border: 1px dashed #b45309;
            color: #78350f;
            padding: 8px 12px;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .header-title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-tight: -0.025em;
        }

        .meta-label {
            font-size: 8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .meta-val {
            font-size: 10px;
            font-weight: bold;
            color: #1e293b;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table th {
            background: #f1f5f9 !important;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 8px;
            font-size: 8.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 8px;
            font-size: 9px;
            vertical-align: middle;
        }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-black {
            font-weight: 900;
        }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 7.5px;
            font-weight: 900;
            border-radius: 2px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
        }

        .badge-in { background-color: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .badge-out { background-color: #fdf2f8; border-color: #fbcfe8; color: #9d174d; }
        .badge-adj { background-color: #faf5ff; border-color: #e9d5ff; color: #6b21a8; }
        .badge-rev { background-color: #f8fafc; border-color: #e2e8f0; color: #475569; }

        .footer-signatures {
            margin-top: 24px;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 25%;
            text-align: center;
            vertical-align: bottom;
            height: 60px;
            font-size: 9px;
            font-weight: bold;
            color: #475569;
        }

        .signature-line {
            width: 130px;
            border-bottom: 1px solid #475569;
            margin: 0 auto 5px auto;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="print-helper no-print">
        <strong>🖨️ PETUNJUK PRINT KARTU STOK:</strong><br>
        1. Gunakan Layout <strong>LANDSCAPE</strong> (Tidur).<br>
        2. Set Paper Size ke <strong>A4</strong>.<br>
        3. Atur Margin ke <strong>DEFAULT</strong> atau <strong>MINIMAL</strong>.<br>
        4. Centang / Aktifkan <strong>Background Graphics</strong> untuk mencetak warna baris dan badge.
    </div>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <h1 class="header-title">KARTU STOK / MOVEMENT LEDGER</h1>
                <p style="font-size: 9px; color: #64748b; font-weight: bold; uppercase; margin-top: 2px;">
                    Warehouse Management System (WMS) &mdash; {{ session('active_warehouse_name', 'Sparepart Warehouse') }}
                </p>
            </td>
            <td style="width: 25%; text-align: left; border-left: 2px solid #e2e8f0; padding-left: 12px;">
                <div class="meta-label">Kode Barang (ERP)</div>
                <div class="meta-val font-mono" style="font-size: 11px;">{{ $variant->erp_code }}</div>
                
                <div class="meta-label" style="margin-top: 4px;">Nama Barang</div>
                <div class="meta-val truncate" style="max-width: 220px;">{{ $variant->item->name }}</div>
            </td>
            <td style="width: 25%; text-align: left; border-left: 2px solid #e2e8f0; padding-left: 12px;">
                <div class="meta-label">Periode Audit</div>
                <div class="meta-val font-mono">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</div>
                
                <div class="meta-label" style="margin-top: 4px;">Unit Satuan / Tipe</div>
                <div class="meta-val font-mono uppercase">{{ $variant->unit }} / {{ $movementType }}</div>
            </td>
        </tr>
    </table>

    <!-- Ledger Data Grid -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 100px;">Date & Time</th>
                <th style="width: 130px;">Reference No.</th>
                <th>Department / PIC / Supplier</th>
                <th class="text-center" style="width: 60px;">Type</th>
                <th class="text-right" style="width: 80px;">Qty In</th>
                <th class="text-right" style="width: 80px;">Qty Out</th>
                <th class="text-right" style="width: 90px;">Running Balance</th>
                <th class="text-center" style="width: 80px;">Bin Loc</th>
                <th class="text-right" style="width: 90px;">Operator</th>
            </tr>
        </thead>
        <tbody>
            
            <!-- Dynamic Starting Balance row -->
            <tr style="background: #fafaf9; font-style: italic; color: #64748b;">
                <td class="font-mono text-center">-</td>
                <td class="font-mono font-bold">INIT_BALANCE</td>
                <td class="font-bold">Saldo awal sebelum {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</td>
                <td class="text-center font-mono">-</td>
                <td class="text-right font-mono">-</td>
                <td class="text-right font-mono">-</td>
                <td class="text-right font-mono font-black text-slate-800" style="background: #f1f5f9;">
                    {{ number_format($startingBalance) }}
                </td>
                <td class="text-center font-mono">-</td>
                <td class="text-right uppercase font-mono" style="font-size: 8.5px;">SYSTEM</td>
            </tr>

            <!-- Chronological items list -->
            @php
                $runningBalance = $startingBalance;
            @endphp
            @forelse($movements as $mov)
                @php
                    $runningBalance += ($mov->type === 'OUT' ? -$mov->qty : $mov->qty);
                    $picDept = 'N/A';
                    if ($mov->type === 'IN') {
                        if ($mov->receipt) {
                            $picDept = ($mov->receipt->supplier ? $mov->receipt->supplier->name : 'BPB Inbound') . ' / ' . ($mov->receipt->operator ? $mov->receipt->operator->name : 'Operator');
                        } elseif ($mov->supplier) {
                            $picDept = $mov->supplier->name . ' / Inbound';
                        } else {
                            $picDept = 'General Stock In';
                        }
                    } elseif ($mov->type === 'OUT') {
                        if ($mov->transaction) {
                            $picDept = ($mov->transaction->department ? $mov->transaction->department->name : 'General') . ' / ' . ($mov->transaction->user ? $mov->transaction->user->name : 'PIC');
                        } else {
                            $picDept = 'Direct Checkout';
                        }
                    } elseif ($mov->type === 'ADJUSTMENT' || $mov->type === 'REVERSAL') {
                        $picDept = 'Opname Adjustment / Reversal';
                    }
                @endphp
                <tr class="font-mono">
                    <td style="color: #64748b; font-size: 8.5px;">
                        {{ $mov->created_at->format('d M Y H:i') }}
                    </td>
                    <td class="font-bold text-slate-800">
                        {{ $mov->reference ?: 'SYSTEM_GEN' }}
                    </td>
                    <td style="font-family: inherit; font-weight: bold; text-transform: uppercase; color: #334155;">
                        {{ $picDept }}
                    </td>
                    <td class="text-center">
                        @if($mov->type === 'IN')
                            <span class="badge badge-in">IN</span>
                        @elseif($mov->type === 'OUT')
                            <span class="badge badge-out">OUT</span>
                        @elseif($mov->type === 'ADJUSTMENT')
                            <span class="badge badge-adj">ADJ</span>
                        @elseif($mov->type === 'REVERSAL')
                            <span class="badge badge-rev">REV</span>
                        @else
                            <span class="badge">{{ $mov->type }}</span>
                        @endif
                    </td>
                    
                    <!-- Qty In -->
                    <td class="text-right font-black" style="color: #166534;">
                        @if($mov->type !== 'OUT' && $mov->qty > 0)
                            +{{ number_format($mov->qty) }}
                        @else
                            -
                        @endif
                    </td>

                    <!-- Qty Out -->
                    <td class="text-right font-black" style="color: #9d174d;">
                        @if($mov->type === 'OUT')
                            {{ number_format($mov->qty) }}
                        @else
                            -
                        @endif
                    </td>

                    <!-- Running Balance -->
                    <td class="text-right font-black text-slate-900" style="background: #f8fafc; font-size: 9.5px;">
                        {{ number_format($runningBalance) }}
                    </td>

                    <!-- Bin location -->
                    <td class="text-center font-bold" style="color: #64748b;">
                        [{{ $mov->bin ? $mov->bin->code : 'UNASSIGND' }}]
                    </td>

                    <!-- Operator -->
                    <td class="text-right uppercase font-bold" style="color: #64748b; font-size: 8.5px;">
                        {{ substr($mov->operator ? $mov->operator->name : ($mov->created_by ?: 'System'), 0, 10) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 30px; font-weight: bold; color: #64748b; font-style: italic;">
                        Tidak ada log pergerakan barang ditemukan untuk filter parameter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Signature Block for Audit Compliance -->
    <table class="footer-signatures">
        <tr>
            <td class="signature-box">
                <div class="signature-line"></div>
                Admin Warehouse
            </td>
            <td class="signature-box">
                <div class="signature-line"></div>
                Supervisor / Checker
            </td>
            <td class="signature-box">
                <div class="signature-line"></div>
                Logistic Manager
            </td>
            <td class="signature-box">
                <div class="signature-line"></div>
                Internal Auditor
            </td>
        </tr>
    </table>

    <script>
        window.onload = function () {
            window.onafterprint = function () {
                window.close();
            };

            // Trigger window print after layout settles
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    window.print();
                });
            });
        };
    </script>
</body>
</html>
