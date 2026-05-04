<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Test Print - {{ strtoupper($type) }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #fff;
            font-family: sans-serif;
        }

        .no-print-header {
            background: #333;
            color: white;
            padding: 15px;
            text-align: center;
        }

        .no-print-header a {
            color: #4da6ff;
            text-decoration: none;
            margin: 0 10px;
            font-weight: bold;
        }

        /* Print Specific Styles */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: white;
            }
            .no-print-header {
                display: none;
            }
            .page {
                margin: 0 !important;
                border: none !important;
            }
        }

        /* Preview container */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 5mm;
            box-sizing: border-box;
        }

        .label-grid {
            display: grid;
            gap: 2mm;
            /* Fixed grid columns based on type */
            grid-template-columns: {{ $type === 'bin' ? 'repeat(2, 80mm)' : 'repeat(6, 30mm)' }};
            justify-content: center;
        }

        .label-item {
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px dashed #ccc; /* Visual guide */
        }

        @media print {
            .label-item {
                border: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print-header">
    <strong>Barcode Test Mode: {{ strtoupper($type) }}</strong>
    | 
    <a href="?type=item">Switch to Item Label (30x50)</a>
    | 
    <a href="?type=bin">Switch to Bin Label (80x50)</a>
    |
    <button onclick="window.print()" style="padding: 5px 15px; cursor: pointer;">PRINT NOW</button>
</div>

<div class="page">
    <div class="label-grid">
        @foreach($labels as $label)
            <div class="label-item">
                {!! $label !!}
            </div>
        @endforeach
    </div>
</div>

</body>
</html>
