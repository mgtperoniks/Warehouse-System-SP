<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pengecekan Barang Datang</title>
    <style>
        @page {
            margin: 110px 40px 60px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #000;
            line-height: 1.4;
        }
        header {
            position: fixed;
            top: -90px;
            left: 0;
            right: 0;
            height: 80px;
            border-bottom: 2px solid #000;
        }
        footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
            font-size: 8px;
            color: #555;
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
            margin-top: 10px;
        }
        .iso-code {
            text-align: right;
            font-size: 8px;
            font-weight: bold;
            color: #000;
        }
        .meta-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .meta-table td {
            font-size: 9px;
            padding: 2px 0;
            vertical-align: top;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .content-table th {
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            vertical-align: middle;
            background-color: #f2f2f2;
        }
        .content-table td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 8.5px;
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
        }
        .variance-neg {
            color: #b91c1c;
            font-weight: bold;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 33.3%;
            text-align: center;
            vertical-align: bottom;
            height: 100px;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 50px;
            font-size: 9px;
            text-transform: uppercase;
        }
        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }
        .signature-img {
            max-height: 45px;
            max-width: 120px;
            display: block;
            margin: 0 auto 5px auto;
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
        <table style="width: 100%; border-top: 1px solid #ccc; padding-top: 5px;">
            <tr>
                <td>Bukti Pengecekan Barang Datang - WMS Generated</td>
                <td style="text-align: right;">Tanggal Cetak: {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </footer>

    <!-- Metadata Section -->
    <table class="meta-table">
        <tr>
            <td style="width: 15%; font-weight: bold;">No. PO</td>
            <td style="width: 35%;">: {{ $session->outstandingPurchaseOrder->po_number }}</td>
            <td style="width: 15%; font-weight: bold;">Tanggal Datang</td>
            <td style="width: 35%;">: {{ \Carbon\Carbon::parse($session->completed_at)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y') }}</td>
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
            <td>: {{ $session->reviewedBy->name ?? ($session->creator->name ?? 'N/A') }}</td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">NO</th>
                <th style="width: 15%;">KODE</th>
                <th style="width: 35%;">NAMA BARANG</th>
                <th style="width: 10%;" class="text-center">QTY DATANG</th>
                <th style="width: 10%;" class="text-center">QTY TERIMA</th>
                <th style="width: 13%;" class="text-center">HASIL PENGECEKAN</th>
                <th style="width: 12%;" class="text-center">DEPT. PEMESAN</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($items as $item)
                @if(!$item->isRemoved())
                    @php
                        $rawErpCode = $item->variant->erp_code ?? '';
                        $variance = $item->received_qty - $item->expected_qty;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td style="font-family: monospace;">{{ $rawErpCode }}</td>
                        <td style="font-weight: bold;">{{ $item->outstandingPurchaseOrderItem->item_name_snapshot }}</td>
                        <td class="text-center">{{ (int)$item->expected_qty }}</td>
                        <td class="text-center">{{ (int)$item->received_qty }}</td>
                        <td class="text-center">
                            @if($variance > 0)
                                <span class="variance-pos">+{{ $variance }}</span>
                            @elseif($variance < 0)
                                <span class="variance-neg">{{ $variance }}</span>
                            @else
                                &nbsp;
                            @endif
                        </td>
                        <td class="text-center">&nbsp;</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <!-- Signature Area -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">DISERAHKAN OLEH</div>
                @if(isset($signatures['DISERAHKAN_OLEH']) && Storage::disk('public')->exists($signatures['DISERAHKAN_OLEH']->signature_path))
                    <img class="signature-img" src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($signatures['DISERAHKAN_OLEH']->signature_path)) }}" alt="Diserahkan Oleh Signature">
                @else
                    <div style="height: 50px;">&nbsp;</div>
                @endif
                <div class="signature-name">( Vendor / Sopir )</div>
            </td>
            <td>
                <div class="signature-title">DITERIMA/DICEK OLEH</div>
                @if(isset($signatures['DITERIMA_OLEH']) && Storage::disk('public')->exists($signatures['DITERIMA_OLEH']->signature_path))
                    <img class="signature-img" src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($signatures['DITERIMA_OLEH']->signature_path)) }}" alt="Diterima Oleh Signature">
                @else
                    <div style="height: 50px;">&nbsp;</div>
                @endif
                <div class="signature-name">( Checker )</div>
            </td>
            <td>
                <div class="signature-title">BAG. GUDANG</div>
                @if(isset($signatures['BAG_GUDANG']) && Storage::disk('public')->exists($signatures['BAG_GUDANG']->signature_path))
                    <img class="signature-img" src="{{ 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($signatures['BAG_GUDANG']->signature_path)) }}" alt="Bagian Gudang Signature">
                @else
                    <div style="height: 50px;">&nbsp;</div>
                @endif
                <div class="signature-name">( Staff Gudang )</div>
            </td>
        </tr>
    </table>
</body>
</html>
