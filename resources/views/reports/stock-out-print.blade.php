<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WMS Stock Out Thermal Receipt</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            zoom: 1 !important;
            transform: none !important;
        }

        @page {
            size: 58mm 200mm;
            margin: 0;
        }

        html,
        body,
        .thermal-root,
        .receipt {
            position: relative !important;
            left: 0 !important;
            top: 0 !important;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;

            width: 58mm !important;

            background: #fff;

            overflow: visible !important;
        }

        body {
            display: block !important;

            font-family: "Courier New", monospace;
            font-size: 11px;
            line-height: 1.2;
            color: #000;

            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .thermal-root {
            width: 58mm;

            display: block;

            margin: 0;
            padding: 0;

            overflow: visible;
        }

        .receipt {
            width: 54mm;

            padding: 2mm;

            display: block;
        }

        .header {
            text-align: center;
            margin-bottom: 4px;
        }

        .header h1 {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .header p {
            font-size: 10px;
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }

        .meta-section {
            margin-bottom: 4px;
        }

        .meta-row {
            margin-bottom: 2px;
            font-weight: bold;
            word-break: break-word;
        }

        .item-list {
            margin-top: 4px;
        }

        .item-row {
            margin-bottom: 6px;
        }

        .item-name {
            font-weight: bold;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .item-qty {
            margin-top: 1px;
            text-align: right;
            font-weight: bold;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }

        .print-helper {
            padding: 6px;
            font-size: 11px;
            font-weight: bold;
            background: #fff8dc;
            border-bottom: 1px dashed #000;
        }

        @media print {
            .print-helper {
                display: none !important;
            }

            html,
            body {
                width: 58mm !important;
                height: auto !important;

                margin: 0 !important;
                padding: 0 !important;

                overflow: visible !important;
            }

            .thermal-root {
                width: 58mm !important;
            }

            .receipt {
                width: 54mm !important;
            }

            * {
                zoom: 1 !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>

    @php
        $tx = $transactions->first();
    @endphp

    @if($tx)
        <div class="print-helper">
            PRINT SETTINGS:<br>
            Scale = 100%<br>
            Margins = NONE<br>
            Paper = 58mm Thermal<br>
            Headers/Footers = OFF
        </div>
        <div class="thermal-root">
            <div class="receipt">
                <div class="divider"></div>
                <div class="header">
                    <h1>SPAREPART WAREHOUSE</h1>
                    <p>STOCK OUT RECEIPT</p>
                </div>
                <div class="divider"></div>

                <div class="meta-section">
                    <div class="meta-row">{{ $tx->code }}</div>
                    <div class="meta-row">{{ $tx->created_at->format('d/m/Y H:i') }}</div>
                    <div class="meta-row" style="margin-top: 4px;">OP  : {{ strtoupper($tx->operator->name ?? auth()->user()->name) }}</div>
                    <div class="meta-row">DEPT: {{ strtoupper($tx->department->name ?? 'UNMAPPED') }}</div>
                </div>

                <div class="divider"></div>

                <div class="item-list">
                    @foreach($tx->items as $item)
                        <div class="item-row">
                            <div class="item-name">
                                {{ strtoupper($item->item_name_snapshot ?? $item->variant->item->name ?? 'N/A') }}
                            </div>
                            <div class="item-qty">
                                x{{ $item->qty }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="divider"></div>

                <div class="footer">
                    RECEIVED SUCCESSFULLY
                </div>
            </div>
        </div>
    @else
        <div class="thermal-root">
            <div class="receipt" style="text-align: center;">
                <p>NO TRANSACTION FOUND</p>
            </div>
        </div>
    @endif

    <script>
        window.onload = function () {
            document.body.style.width = "58mm";
            document.body.style.margin = "0";
            document.body.style.padding = "0";

            document.documentElement.style.width = "58mm";

            window.onafterprint = function () {
                window.close();
            };

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    window.print();
                });
            });
        };
    </script>
</body>
</html>
