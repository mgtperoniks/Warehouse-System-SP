<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pengecekan Barang Datang</title>
    <style>
        @page {
            size: 210mm 330mm;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 210mm;
            color: #000;
            background: #fff;
            font-size: 7.5pt;
            line-height: 1.15;
            font-family: Arial, Helvetica, sans-serif;
        }
        .f4-page {
            width: 210mm;
            background: #fff;
        }

        /* ============================================================
           FORM BLOCK: Exactly 210mm × 110mm.
           All padding is INSIDE the 110mm via box-sizing: border-box.
           3 × 110mm = 330mm exactly — no separators consume extra height.
        ============================================================ */
        .form-block {
            width: 210mm;
            height: 110mm;
            max-height: 110mm;
            overflow: hidden;
            padding: 3mm 10mm 2mm 10mm;
            box-sizing: border-box;
            position: relative;
        }

        /* Content area is exactly 190mm = 210mm - 10mm(L) - 10mm(R) */
        .form-content {
            width: 190mm;
        }

        /* ============================================================
           COMPANY HEADER
        ============================================================ */
        .header-table {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 0.5mm;
        }
        .header-table td {
            padding: 0;
            vertical-align: middle;
        }
        .col-logo {
            width: 24mm;
            text-align: left;
            vertical-align: middle;
        }
        .col-center {
            width: 142mm;
            text-align: center;
            vertical-align: middle;
        }
        .col-spacer {
            width: 24mm;
        }
        .company-logo-img {
            width: 22mm;
            height: auto;
            display: block;
        }
        .company-name-text {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #000;
            margin-bottom: 0.3mm;
        }
        .company-name-img {
            height: 8mm;
            width: auto;
            display: block;
            margin: 0 auto 0.4mm auto;
        }
        .company-address {
            font-size: 5.5pt;
            line-height: 1.3;
            color: #000;
        }

        /* Header divider line */
        .header-rule {
            width: 190mm;
            border: none;
            border-top: 0.75pt solid #000;
            margin: 0.3mm 0;
        }

        /* ============================================================
           FORM TITLE
        ============================================================ */
        .form-title {
            text-align: center;
            font-size: 9.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0.5mm 0 0.8mm 0;
            color: #000;
        }

        /* ============================================================
           METADATA ROW
        ============================================================ */
        .meta-table {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 0.6mm;
        }
        .meta-table td {
            padding: 0;
            vertical-align: bottom;
        }

        /* ============================================================
           ITEMS TABLE
           32 + 59.5 + 12 + 12 + 50.5 + 24 = 190mm exactly
           (16.842% + 31.316% + 6.316% + 6.316% + 26.579% + 12.631% = 100%)
        ============================================================ */
        .items-table {
            width: 190mm;
            border-collapse: collapse;
        }
        .items-table th {
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
            border: 0.5pt solid #000;
            padding: 0.8px 0.5px;
            background-color: #fff;
            vertical-align: middle;
            line-height: 1.1;
        }
        .items-table td {
            border: 0.5pt solid #000;
            padding: 0.8px 1.5px;
            font-size: 6.5pt;
            vertical-align: middle;
            height: 5mm;
            line-height: 1.1;
            overflow: hidden;
        }
        .col-kode   { width: 16.842%; }
        .col-nama   { width: 31.316%; }
        .col-qtyd   { width: 6.316%; }
        .col-qtyt   { width: 6.316%; }
        .col-hasil  { width: 26.579%; }
        .col-dept   { width: 12.631%; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }
        .font-mono   { font-family: 'Courier New', Courier, monospace; font-size: 6pt; }
        .font-bold   { font-weight: bold; }

        /* ============================================================
           SIGNATURE AREA
           3 cols × 63.33mm, label + 15mm blank space
        ============================================================ */
        .signatures-table {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 1mm;
        }
        .signatures-table td {
            width: 63.33mm;
            text-align: center;
            vertical-align: top;
            padding: 0 2px;
        }
        .sig-title {
            font-size: 7pt;
            font-weight: bold;
            text-align: center;
        }
        .sig-blank-space {
            height: 15mm;
        }

        /* ============================================================
           FOOTER: Document reference only
        ============================================================ */
        .doc-footer {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 5.5pt;
            color: #000;
            margin-top: 0.5mm;
        }

        /* ============================================================
           CUT SEPARATOR: absolutely positioned at bottom of block,
           zero additional height — stays inside 110mm.
        ============================================================ */
        .cut-indicator {
            position: absolute;
            bottom: 0;
            left: 10mm;
            right: 10mm;
            border-top: 1pt dashed #999;
            height: 0;
            overflow: visible;
        }
        .cut-icon {
            position: absolute;
            right: 0;
            top: -6px;
            font-size: 7pt;
            color: #888;
            background: #fff;
            padding-left: 2px;
        }
    </style>
</head>
<body>
    @php
        /* ---- Asset Loading ---- */
        $logoBase64  = '';
        $brandBase64 = '';

        $logoCandidates = [
            public_path('assets/images/pdf/pks_logo_black_hd.png'),
            public_path('assets/images/pdf/pks_logo_black.png'),
            public_path('assets/images/pdf/pks_logo_round.png'),
        ];
        foreach ($logoCandidates as $lp) {
            if (file_exists($lp)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($lp));
                break;
            }
        }

        $brandCandidates = [
            public_path('assets/images/pdf/pks_brand_black_hd.png'),
            public_path('assets/images/pdf/pks_brand_black.png'),
            public_path('assets/images/pdf/pks_brand_name.png'),
        ];
        foreach ($brandCandidates as $bp) {
            if (file_exists($bp)) {
                $brandBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($bp));
                break;
            }
        }

        /* ---- Session Data ---- */
        $supplierName  = $session->outstandingPurchaseOrder->supplier_name_snapshot ?? 'N/A';
        $poNumber      = $session->outstandingPurchaseOrder->po_number ?? 'N/A';
        $dateFormatted = $session->completed_at
            ? \Carbon\Carbon::parse($session->completed_at)->timezone('Asia/Jakarta')->format('d/m/Y')
            : ($session->started_at
                ? \Carbon\Carbon::parse($session->started_at)->timezone('Asia/Jakarta')->format('d/m/Y')
                : now()->format('d/m/Y'));

        /* ---- Pagination: 6 items per block, 3 blocks per F4 page ---- */
        $itemChunks = $items->chunk(6);
        if ($itemChunks->isEmpty()) {
            $itemChunks = collect([collect()]);
        }
        $pages = $itemChunks->chunk(3);

        /* ---- Signature Map: keyed by role (passed from controller) ---- */
        $signatures = $signatures ?? [];
    @endphp

    @foreach($pages as $pageIndex => $pageBlocks)
        <div class="f4-page" style="{{ !$loop->last ? 'page-break-after: always;' : '' }}">

            @for($b = 0; $b < 3; $b++)
                @php $blockItems = $pageBlocks->values()->get($b) ?? collect(); @endphp

                {{-- FORM BLOCK: 210mm × 110mm (box-sizing: border-box) --}}
                <div class="form-block">
                    <div class="form-content">

                        {{-- ===== COMPANY HEADER ===== --}}
                        <table class="header-table">
                            <tr>
                                <td class="col-logo">
                                    @if($logoBase64)
                                        <img src="{{ $logoBase64 }}" class="company-logo-img" alt="PKS Logo">
                                    @endif
                                </td>
                                <td class="col-center">
                                    @if($brandBase64)
                                        <img src="{{ $brandBase64 }}" class="company-name-img" alt="PT. PERONI KARYA SENTRA">
                                    @else
                                        <div class="company-name-text">PT. PERONI KARYA SENTRA</div>
                                    @endif
                                    <div class="company-address">
                                        Ngoro Industri Persada Blok K-5A, Ngoro, Mojokerto 61385 East Java - Indonesia<br>
                                        Tel: +62 321 6818225, 6818226, 6818099 &bull; Fax: +62 321 6817229, 6818220<br>
                                        Email : peroni@indosat.net.id &bull; peroni@peroniks.com &bull; URL: www.peroniks.com
                                    </div>
                                </td>
                                <td class="col-spacer"></td>
                            </tr>
                        </table>
                        <div class="header-rule"></div>

                        {{-- ===== FORM TITLE ===== --}}
                        <div class="form-title">BUKTI PENGECEKAN BARANG DATANG</div>

                        {{-- ===== METADATA ROW ===== --}}
                        <table class="meta-table">
                            <tr>
                                <td style="width:130mm; text-align:left;">
                                    Telah terima barang dari&nbsp;:&nbsp;<strong>{{ $supplierName }}</strong>&nbsp;&nbsp;PO&nbsp;:&nbsp;<strong>{{ $poNumber }}</strong>
                                </td>
                                <td style="width:60mm; text-align:right;">
                                    Tgl&nbsp;:&nbsp;<strong>{{ $dateFormatted }}</strong>
                                </td>
                            </tr>
                        </table>

                        {{-- ===== ITEMS TABLE ===== --}}
                        {{-- Physical proportions: 32mm / 59.5mm / 12mm / 12mm / 50.5mm / 24mm (190mm total)
                             Using percentage widths without table-layout:fixed so DomPDF resolves exact geometry. --}}
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th class="col-kode" rowspan="2">KODE</th>
                                    <th class="col-nama" rowspan="2">NAMA BARANG</th>
                                    <th colspan="2" style="font-size:6.5pt; padding: 0.5px 0;">QTY</th>
                                    <th class="col-hasil" rowspan="2">HASIL PENGECEKAN</th>
                                    <th class="col-dept" rowspan="2">DEPT.<br>PEMESAN</th>
                                </tr>
                                <tr>
                                    <th class="col-qtyd" style="font-size:5.5pt; padding:0.5px 0;">Datang</th>
                                    <th class="col-qtyt" style="font-size:5.5pt; padding:0.5px 0;">Terima</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for($r = 0; $r < 6; $r++)
                                    @php $item = $blockItems->values()->get($r); @endphp
                                    @if($item)
                                        @php
                                             $erpCode     = $item->variant->erp_code ?? ($item->outstandingPurchaseOrderItem->erp_code ?? '-');
                                             $deptName    = $item->outstandingPurchaseOrderItem->department_name
                                                          ?: ($session->outstandingPurchaseOrder->department_name ?: '-');
                                             $qtyDatang   = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
                                             $qtyTerima   = (float)$item->received_qty;
                                             $fmtDatang   = (float)$qtyDatang == (int)$qtyDatang ? (int)$qtyDatang : number_format($qtyDatang, 2);
                                             $fmtTerima   = (float)$qtyTerima == (int)$qtyTerima ? (int)$qtyTerima : number_format($qtyTerima, 2);
                                             $checkResult = $item->isRemoved() ? 'REMOVED' : ($item->check_result ?: 'OK');
                                        @endphp
                                        <tr>
                                            <td class="col-kode font-mono text-left">{{ $erpCode }}</td>
                                            <td class="col-nama font-bold text-left">
                                                {{ $item->outstandingPurchaseOrderItem->item_name_snapshot ?? '-' }}
                                                @if($item->check_notes)
                                                    <div style="font-weight:normal;font-style:italic;font-size:5pt;color:#444;">{{ $item->check_notes }}</div>
                                                @endif
                                            </td>
                                            <td class="col-qtyd text-center" style="font-size:6pt;">{{ $fmtDatang }}</td>
                                            <td class="col-qtyt text-center font-bold" style="font-size:6pt;">{{ $fmtTerima }}</td>
                                            <td class="col-hasil text-center font-bold">{{ $checkResult }}</td>
                                            <td class="col-dept text-center">{{ $deptName }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td class="col-kode">&nbsp;</td>
                                            <td class="col-nama">&nbsp;</td>
                                            <td class="col-qtyd">&nbsp;</td>
                                            <td class="col-qtyt">&nbsp;</td>
                                            <td class="col-hasil">&nbsp;</td>
                                            <td class="col-dept">&nbsp;</td>
                                        </tr>
                                    @endif
                                @endfor
                            </tbody>
                        </table>

                        {{-- ===== SIGNATURE AREA ===== --}}
                        {{-- $signatures is keyed by role: DISERAHKAN_OLEH | DITERIMA_OLEH | BAG_GUDANG --}}
                        {{-- Layout: label ABOVE, signature sits in blank space BELOW the label --}}
                        <table class="signatures-table">
                            <tr>
                                <td>
                                    <div class="sig-title">Diserahkan Oleh</div>
                                    <div class="sig-blank-space">
                                        @if(!empty($signatures['DISERAHKAN_OLEH']) && !empty($signatures['DISERAHKAN_OLEH']->base64_data))
                                            <img src="{{ $signatures['DISERAHKAN_OLEH']->base64_data }}"
                                                 style="max-height:13mm; max-width:58mm; width:auto; height:auto; display:block; margin:2mm auto 0 auto;"
                                                 alt="">
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="sig-title">Diterima/Dicek Oleh</div>
                                    <div class="sig-blank-space">
                                        @if(!empty($signatures['DITERIMA_OLEH']) && !empty($signatures['DITERIMA_OLEH']->base64_data))
                                            <img src="{{ $signatures['DITERIMA_OLEH']->base64_data }}"
                                                 style="max-height:13mm; max-width:58mm; width:auto; height:auto; display:block; margin:2mm auto 0 auto;"
                                                 alt="">
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="sig-title">Bag. Gudang</div>
                                    <div class="sig-blank-space">
                                        @if(!empty($signatures['BAG_GUDANG']) && !empty($signatures['BAG_GUDANG']->base64_data))
                                            <img src="{{ $signatures['BAG_GUDANG']->base64_data }}"
                                                 style="max-height:13mm; max-width:58mm; width:auto; height:auto; display:block; margin:2mm auto 0 auto;"
                                                 alt="">
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>

                        {{-- ===== FOOTER ===== --}}
                        <table class="doc-footer">
                            <tr>
                                <td style="text-align:left; width:50%;">FR/GUD/10-01-05/17-00-1/1</td>
                                <td style="text-align:right; width:50%;"></td>
                            </tr>
                        </table>

                    </div>{{-- /form-content --}}

                    {{-- Cut separator: absolutely positioned at bottom edge, zero extra height --}}
                    @if($b < 2)
                        <div class="cut-indicator">
                            <span class="cut-icon">&#9986;</span>
                        </div>
                    @endif

                </div>{{-- /form-block --}}

            @endfor
        </div>{{-- /f4-page --}}
    @endforeach
</body>
</html>
