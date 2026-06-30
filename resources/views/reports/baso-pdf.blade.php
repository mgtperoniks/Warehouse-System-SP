<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Berita Acara Stock Opname</title>
    <style>
        @page {
            margin: 155px 40px 70px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #333;
            line-height: 1.4;
        }
        header {
            position: fixed;
            top: -135px;
            left: 0;
            right: 0;
            height: 125px;
            border-bottom: 1px solid #000;
        }
        footer {
            position: fixed;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 30px;
        }
        .company-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin: 0;
            color: #000;
        }
        .header-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin: 3px 0 0 0;
            color: #000;
        }
        .doc-number {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            margin: 3px 0 0 0;
            color: #000;
        }
        .header-meta {
            width: 100%;
            margin-top: 6px;
        }
        .header-meta td {
            font-size: 9px;
            padding: 2px 0;
            vertical-align: top;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .content-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        .content-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 9px;
            vertical-align: top;
        }
        thead {
            display: table-header-group;
        }
        .content-table tr {
            page-break-inside: avoid;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .status-badge {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
        }
        .status-approved {
            color: #15803d;
        }
        .status-rejected {
            color: #b91c1c;
        }
        .page-number:before {
            content: counter(page);
        }
        .total-pages:before {
            content: counter(pages);
        }
    </style>
</head>
<body>
    <header>
        <div style="border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0;">
            <div class="company-name">PT PERONI KARYA SENTRA</div>
            <div class="header-title">BERITA ACARA STOCK OPNAME</div>
            <div class="doc-number">No. {{ $baso->baso_number }}</div>
        </div>
        
        <table class="header-meta" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 15%; font-weight: bold;">Adjustment Ref</td>
                <td style="width: 35%;">: {{ $adjustment->adjustment_no }}</td>
                <td style="width: 15%; font-weight: bold;">Warehouse</td>
                <td style="width: 35%;">: {{ $warehouseName }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Business Date</td>
                <td>: {{ \Carbon\Carbon::parse($businessDate)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y') }}</td>
                <td style="font-weight: bold;">Operator</td>
                <td>: {{ $operatorName }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Generated Time</td>
                <td>: {{ \Carbon\Carbon::parse($baso->generated_at)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y H:i') }} WIB</td>
                <td style="font-weight: bold;">Manager PPIC</td>
                <td>: {{ $managerName }}</td>
            </tr>
        </table>
    </header>

    <footer>
        <table style="width: 100%; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 8px; color: #64748b;">
            <tr>
                <td>Document Ref: {{ $baso->baso_number }}</td>
                <td style="text-align: right;">
                    Page <span class="page-number"></span> of <span class="total-pages"></span>
                </td>
            </tr>
        </table>
    </footer>

    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th style="width: 70px;">ERP Code</th>
                <th>Item Name</th>
                <th style="width: 50px;">Bin</th>
                <th style="width: 50px;" class="text-right">System Qty</th>
                <th style="width: 50px;" class="text-right">Phys Qty</th>
                <th style="width: 50px;" class="text-right">Variance</th>
                <th>Reason</th>
                <th>Notes</th>
                <th style="width: 55px;" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                @php
                    $rawErpCode = $item->erp_code_snapshot ?? ($item->variant->erp_code ?? '');
                    // Format ERP code: strip prefix numbers
                    $erpCode = preg_replace('/^[0-9]+\./', '', $rawErpCode);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $erpCode }}</td>
                    <td style="font-weight: bold;">{{ $item->item_name_snapshot ?? ($item->variant->item->name ?? 'N/A') }}</td>
                    <td>{{ $item->bin_code_snapshot ?? ($item->bin->code ?? 'N/A') }}</td>
                    <td class="text-right">{{ (int)$item->system_qty }}</td>
                    <td class="text-right">{{ (int)$item->physical_qty }}</td>
                    <td class="text-right" style="font-weight: bold; color: {{ $item->variance > 0 ? '#16a34a' : ($item->variance < 0 ? '#dc2626' : '#000') }}">
                        {{ $item->variance > 0 ? '+' : '' }}{{ (int)$item->variance }}
                    </td>
                    <td>
                        @php
                            $reasonLabel = $item->reason;
                            if (isset($reasonsMap[$item->reason])) {
                                $reasonLabel = $reasonsMap[$item->reason];
                            }
                        @endphp
                        {{ $reasonLabel }}
                    </td>
                    <td>{{ $item->notes ?: '-' }}</td>
                    <td class="text-center status-badge {{ $item->status === 'APPROVED' ? 'status-approved' : 'status-rejected' }}">
                        {{ $item->status }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box" style="margin-top: 15px; border: 1px solid #cbd5e1; background-color: #f8fafc; padding: 8px 12px; page-break-inside: avoid;">
        <div style="font-weight: bold; font-size: 9px; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-bottom: 6px; color: #1e293b;">
            RINGKASAN HASIL STOCK OPNAME
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
            <tr>
                <td style="width: 20%; font-weight: bold; color: #475569; padding: 3px 0;">Total Item</td>
                <td style="width: 30%; padding: 3px 0;">: {{ $totalItems }}</td>
                <td style="width: 25%; font-weight: bold; color: #475569; padding: 3px 0;">Positive Adjustment</td>
                <td style="width: 25%; padding: 3px 0; color: #16a34a; font-weight: bold;">: +{{ (int)$posVariance }} PCS</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #475569; padding: 3px 0;">Approved</td>
                <td style="padding: 3px 0; color: #15803d; font-weight: bold;">: {{ $approvedCount }}</td>
                <td style="font-weight: bold; color: #475569; padding: 3px 0;">Negative Adjustment</td>
                <td style="padding: 3px 0; color: #dc2626; font-weight: bold;">: {{ (int)$negVariance }} PCS</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #475569; padding: 3px 0;">Rejected</td>
                <td style="padding: 3px 0; color: #b91c1c; font-weight: bold;">: {{ $rejectedCount }}</td>
                <td style="font-weight: bold; color: #475569; padding: 3px 0;">Net Difference</td>
                <td style="padding: 3px 0; font-weight: bold; color: {{ $netVariance > 0 ? '#16a34a' : ($netVariance < 0 ? '#dc2626' : '#000') }}">
                    : {{ $netVariance > 0 ? '+' : '' }}{{ (int)$netVariance }} PCS
                </td>
            </tr>
        </table>
    </div>

    <div class="signature-container" style="margin-top: 35px; page-break-inside: avoid;">
        <table style="width: 100%; font-size: 9px; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; text-align: left; vertical-align: top;">
                    <div style="font-weight: bold; margin-bottom: 50px;">Prepared By,</div>
                    <div style="margin-bottom: 2px;">_______________________________</div>
                    <div style="font-weight: bold;">( {{ $operatorName }} )</div>
                    <div style="color: #64748b; font-size: 8px;">Admin Warehouse</div>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <div style="font-weight: bold; margin-bottom: 50px; text-align: right;">Reviewed & Approved,</div>
                    <div style="margin-bottom: 2px; text-align: right;">_______________________________</div>
                    <div style="font-weight: bold; text-align: right;">( {{ $managerName }} )</div>
                    <div style="color: #64748b; font-size: 8px; text-align: right;">Manager PPIC</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
