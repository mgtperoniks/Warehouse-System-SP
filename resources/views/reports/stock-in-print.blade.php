<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WMS Stock In Receipt</title>
    <style>
        @page {
            size: 58mm auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 2mm 3mm;
            width: 52mm;
            line-height: 1.2;
        }
        .no-print {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px;
            margin-bottom: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .no-print button {
            background: #0f172a;
            color: #fff;
            border: 0;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        .header {
            text-align: center;
            margin-bottom: 4px;
        }
        .header h1 {
            font-size: 12px;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header p {
            margin: 0;
            font-size: 8px;
            text-transform: uppercase;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .meta-info {
            font-size: 8px;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .meta-info div {
            margin-bottom: 1px;
        }
        .receipt-section {
            margin-bottom: 10px;
        }
        .receipt-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .item-list {
            width: 100%;
            border-collapse: collapse;
        }
        .footer {
            text-align: center;
            font-size: 8px;
            margin-top: 8px;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
                width: 58mm;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <span style="font-weight: bold; color: #475569; display: block; margin-bottom: 6px; font-size: 10px;">🖨️ 58MM THERMAL RECEIPT</span>
        <button onclick="window.print()">Trigger Print</button>
    </div>

    <div class="header">
        <h1>WMS Stock In</h1>
        <p>Inbound Receipt Records</p>
    </div>

    <div class="divider"></div>

    <div class="meta-info">
        <div>DATE: {{ now()->format('Y-m-d H:i:s') }}</div>
        <div>WH  : {{ session('active_warehouse_code', 'SPAREPART') }}</div>
    </div>

    <div class="divider"></div>

    @forelse($receipts as $receipt)
        <div class="receipt-section">
            <div class="receipt-title">RC: {{ $receipt->receipt_code }}</div>
            <div class="meta-info" style="margin-bottom: 4px;">
                <div>OP: {{ $receipt->operator->name ?? 'N/A' }}</div>
                @if($receipt->purchase_order_ref)
                    <div>PO: {{ $receipt->purchase_order_ref }}</div>
                @endif
            </div>
            
            <table class="item-list">
                <thead>
                    <tr>
                        <th style="text-align: left; border: 0; padding: 2px 0; font-size: 9px; font-weight: bold;">ITEM</th>
                        <th style="text-align: right; border: 0; padding: 2px 0; font-size: 9px; font-weight: bold;">QTY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="2" style="border: 0; padding: 0;"><div class="divider" style="margin: 1px 0;"></div></td>
                    </tr>
                    @foreach($receipt->items as $item)
                        <tr>
                            <td style="border: 0; padding: 1px 0; font-size: 9px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 135px;">
                                {{ Str::limit($item->variant->item->name ?? 'N/A', 18, '') }}
                            </td>
                            <td style="border: 0; padding: 1px 0; font-size: 9px; text-align: right; font-weight: bold; white-space: nowrap;">
                                x{{ $item->qty }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; font-weight: bold; font-size: 8px;">No receiving session matches active print filters.</p>
    @endforelse

    <div class="divider"></div>
    
    <div class="footer">
        <p>ADMIN: {{ auth()->user()->name }}</p>
        <p style="margin-top: 4px;">RECEIVED SUCCESSFULLY</p>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
