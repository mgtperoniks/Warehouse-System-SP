<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pengecekan Barang Datang</title>
    <style>
        @page {
            margin: 100px 35px 50px 35px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #000;
            line-height: 1.3;
        }
        header {
            position: fixed;
            top: -85px;
            left: 0;
            right: 0;
            height: 75px;
            border-bottom: 2px solid #000;
        }
        footer {
            position: fixed;
            bottom: -35px;
            left: 0;
            right: 0;
            height: 25px;
            font-size: 7.5px;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 4px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .company-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }
        .iso-code {
            text-align: right;
            font-size: 8px;
            font-weight: bold;
            color: #000;
        }
        .meta-table {
            width: 100%;
            margin-top: 6px;
            border-collapse: collapse;
        }
        .meta-table td {
            font-size: 8.5px;
            padding: 1.5px 0;
            vertical-align: top;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .content-table th {
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 4px 5px;
            text-align: left;
            vertical-align: middle;
            background-color: #f2f2f2;
        }
        .content-table td {
            border: 1px solid #000;
            padding: 4px 5px;
            font-size: 8px;
            vertical-align: middle;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .variance-pos {
            color: #15803d;
            font-weight: bold;
            font-size: 7.5px;
        }
        .variance-neg {
            color: #b91c1c;
            font-weight: bold;
            font-size: 7.5px;
        }
        .removed-badge {
            color: #b91c1c;
            font-weight: bold;
            font-style: italic;
        }
        .notes-section {
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 6px;
            background-color: #fafafa;
            border-radius: 2px;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 33.333%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }
        .signature-title {
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
            text-align: center;
        }
        .signature-box {
            height: 48px;
            text-align: center;
            vertical-align: middle;
            margin-bottom: 4px;
        }
        .signature-img {
            max-height: 46px;
            max-width: 120px;
            display: inline-block;
            vertical-align: middle;
            margin: 0 auto;
        }
        .signature-line-container {
            text-align: center;
            margin-top: 2px;
        }
        .signature-name {
            font-weight: bold;
            font-size: 8px;
            border-top: 1px solid #000;
            padding-top: 3px;
            display: inline-block;
            width: 140px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td class="company-name" style="width: 50%;">PT. PERONI KARYA SENTRA</td>
                <td class="iso-code" style="width: 50%;">FR/GUD/10-01-05/17-00-1/1</td>
            </tr>
        </table>
        <div class="header-title">BUKTI PENGECEKAN BARANG DATANG</div>
    </header>

    <footer>
        <table style="width: 100%;">
            <tr>
                <td>Bukti Pengecekan Barang Datang - WMS Generated (Sesi #{{ $session->id }})</td>
                <td style="text-align: right;">Tanggal Cetak: {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</td>
            </tr>
        </table>
    </footer>

    <!-- Metadata Section -->
    <table class="meta-table">
        <tr>
            <td style="width: 14%; font-weight: bold;">No. PO</td>
            <td style="width: 36%;">: {{ $session->outstandingPurchaseOrder->po_number }}</td>
            <td style="width: 14%; font-weight: bold;">Tanggal Datang</td>
            <td style="width: 36%;">: {{ $session->completed_at ? \Carbon\Carbon::parse($session->completed_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB' : ($session->started_at ? \Carbon\Carbon::parse($session->started_at)->timezone('Asia/Jakarta')->format('d/m/Y') : now()->format('d/m/Y')) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Supplier</td>
            <td>: {{ $session->outstandingPurchaseOrder->supplier_name_snapshot }}</td>
            <td style="font-weight: bold;">Warehouse</td>
            <td>: {{ $session->warehouse->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Status</td>
            <td>: {{ $session->status }}</td>
            <td style="font-weight: bold;">Operator / Checker</td>
            <td>: {{ $session->creator->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Reviewer</td>
            <td>: {{ $session->reviewedBy->name ?? '-' }}</td>
            <td style="font-weight: bold;">No. Sesi</td>
            <td>: #{{ $session->id }}</td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">NO</th>
                <th style="width: 16%;">KODE</th>
                <th style="width: 32%;">NAMA BARANG</th>
                <th style="width: 12%;" class="text-center">QTY DATANG</th>
                <th style="width: 12%;" class="text-center">QTY TERIMA</th>
                <th style="width: 12%;" class="text-center">HASIL PENGECEKAN</th>
                <th style="width: 12%;" class="text-center">DEPT. PEMESAN</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($items as $item)
                @php
                    $rawErpCode = $item->variant->erp_code ?? ($item->outstandingPurchaseOrderItem->erp_code ?? '-');
                    $deptName = $item->outstandingPurchaseOrderItem->department_name ?: ($session->outstandingPurchaseOrder->department_name ?: '-');
                    $qtyDatang = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
                    $qtyTerima = (float)$item->received_qty;
                    $expected = (float)$item->expected_qty;
                    $diff = round($qtyDatang - $expected, 3);
                @endphp
                <tr>
                    <td class="text-center">{{ $no++ }}</td>
                    <td style="font-family: monospace; font-size: 7.5px;">{{ $rawErpCode }}</td>
                    <td style="font-weight: bold;">
                        {{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}
                        @if($item->check_notes)
                            <div style="font-weight: normal; font-style: italic; color: #555; font-size: 7px; margin-top: 1px;">
                                Catatan: {{ $item->check_notes }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($item->isRemoved())
                            <span class="removed-badge">-</span>
                        @else
                            {{ (float)$qtyDatang == (int)$qtyDatang ? (int)$qtyDatang : number_format($qtyDatang, 2) }}
                            @if($diff > 0.0001)
                                <div class="variance-pos">(+{{ (float)$diff == (int)$diff ? (int)$diff : number_format($diff, 2) }})</div>
                            @elseif($diff < -0.0001)
                                <div class="variance-neg">({{ (float)$diff == (int)$diff ? (int)$diff : number_format($diff, 2) }})</div>
                            @endif
                        @endif
                    </td>
                    <td class="text-center font-bold">
                        @if($item->isRemoved())
                            <span class="removed-badge">REMOVED</span>
                        @else
                            {{ (float)$qtyTerima == (int)$qtyTerima ? (int)$qtyTerima : number_format($qtyTerima, 2) }}
                        @endif
                    </td>
                    <td class="text-center">
                        @if($item->isRemoved())
                            <span class="removed-badge">REMOVED ({{ $item->removed_reason }})</span>
                        @else
                            <strong>{{ $item->check_result ?: 'OK' }}</strong>
                        @endif
                    </td>
                    <td class="text-center">{{ $deptName }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Session Remarks if any -->
    @if($session->remarks)
        <div class="notes-section">
            <span style="font-weight: bold; text-transform: uppercase; font-size: 8px;">Keterangan / Catatan Sesi:</span>
            <div style="margin-top: 2px; font-size: 8px;">{{ $session->remarks }}</div>
        </div>
    @endif

    <!-- Signature Area -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">DISERAHKAN OLEH</div>
                <div class="signature-box">
                    @php
                        $sigDiserahkan = $signatures['DISERAHKAN_OLEH'] ?? null;
                        $diserahkanSrc = $sigDiserahkan->base64_data ?? (isset($sigDiserahkan->signature_path) && Storage::disk('public')->exists($sigDiserahkan->signature_path) ? 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($sigDiserahkan->signature_path)) : null);
                    @endphp
                    @if($diserahkanSrc)
                        <img class="signature-img" src="{{ $diserahkanSrc }}" alt="Diserahkan Oleh Signature">
                    @endif
                </div>
                <div class="signature-line-container">
                    <div class="signature-name">( Vendor / Sopir )</div>
                </div>
            </td>
            <td>
                <div class="signature-title">DITERIMA/DICEK OLEH</div>
                <div class="signature-box">
                    @php
                        $sigDiterima = $signatures['DITERIMA_OLEH'] ?? null;
                        $diterimaSrc = $sigDiterima->base64_data ?? (isset($sigDiterima->signature_path) && Storage::disk('public')->exists($sigDiterima->signature_path) ? 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($sigDiterima->signature_path)) : null);
                    @endphp
                    @if($diterimaSrc)
                        <img class="signature-img" src="{{ $diterimaSrc }}" alt="Diterima Oleh Signature">
                    @endif
                </div>
                <div class="signature-line-container">
                    <div class="signature-name">( {{ $session->creator->name ?? 'Checker' }} )</div>
                </div>
            </td>
            <td>
                <div class="signature-title">BAG. GUDANG</div>
                <div class="signature-box">
                    @php
                        $sigGudang = $signatures['BAG_GUDANG'] ?? null;
                        $gudangSrc = $sigGudang->base64_data ?? (isset($sigGudang->signature_path) && Storage::disk('public')->exists($sigGudang->signature_path) ? 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($sigGudang->signature_path)) : null);
                    @endphp
                    @if($gudangSrc)
                        <img class="signature-img" src="{{ $gudangSrc }}" alt="Bagian Gudang Signature">
                    @endif
                </div>
                <div class="signature-line-container">
                    <div class="signature-name">( {{ $session->reviewedBy->name ?? 'Staff Gudang' }} )</div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
