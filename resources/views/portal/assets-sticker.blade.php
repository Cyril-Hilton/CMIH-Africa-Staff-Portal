<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Sticker - {{ $asset->asset_tag_or_code }} - CMIH Africa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: #111;
            color: #111;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .controls {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            background: #E50914;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #b80710;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* The Physical Sticker Badge Container */
        .sticker-card {
            width: 95mm;
            max-width: 100%;
            min-height: 60mm;
            background: #ffffff;
            border: 2.5px solid #111111;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Top Header */
        .sticker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #111111;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .brand-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .brand-logo-badge {
            background: #E50914;
            color: #fff;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 14px;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.05em;
        }

        .brand-text {
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #111;
        }

        .tag-pill {
            font-family: 'JetBrains Mono', monospace;
            background: #111;
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        /* Middle Body */
        .sticker-body {
            display: flex;
            gap: 12px;
            align-items: center;
            flex: 1;
        }

        .qr-box {
            width: 34mm;
            height: 34mm;
            min-width: 34mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #111;
            border-radius: 6px;
            padding: 3px;
            background: #fff;
        }

        .qr-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .qr-label {
            font-size: 7px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: 2px;
            color: #555;
        }

        .meta-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .asset-name {
            font-size: 13px;
            font-weight: 800;
            color: #111;
            line-height: 1.2;
            word-break: break-word;
        }

        .meta-row {
            display: flex;
            font-size: 9px;
            line-height: 1.3;
        }

        .meta-key {
            width: 70px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.05em;
            flex-shrink: 0;
        }

        .meta-val {
            font-weight: 600;
            color: #111;
            word-break: break-word;
        }

        .highlight-owner {
            color: #E50914;
            font-weight: 800;
        }

        /* Footer */
        .sticker-footer {
            border-top: 1px dashed #888;
            margin-top: 6px;
            padding-top: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }

        /* Print styles */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                justify-content: flex-start !important;
            }

            .controls {
                display: none !important;
            }

            .sticker-card {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                page-break-inside: avoid;
                margin: 0;
            }

            @page {
                size: auto;
                margin: 4mm;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <a href="{{ route('portal.assets') }}" class="btn btn-secondary">
            ← Back to Assets
        </a>
        <button onclick="window.print()" class="btn">
            🖨️ Print Sticker
        </button>
        <button id="downloadStickerBtn" class="btn btn-secondary">
            ⬇️ Download PNG Sticker
        </button>
        <a href="{{ $asset->scan_url }}" target="_blank" class="btn btn-secondary">
            📱 Test Scan URL
        </a>
    </div>

    {{-- Printable Sticker Element --}}
    <div class="sticker-card" id="stickerBadge">
        <div class="sticker-header">
            <div class="brand-title">
                <span class="brand-logo-badge">CMIH</span>
                <span class="brand-text">CMIH Africa Asset Tag</span>
            </div>
            <span class="tag-pill">{{ $asset->asset_tag_or_code }}</span>
        </div>

        <div class="sticker-body">
            <div class="qr-box">
                {!! \App\Services\QrCodeService::svg($asset->scan_url, 130, 0) !!}
                <span class="qr-label">Scan to verify</span>
            </div>

            <div class="meta-box">
                <div class="asset-name">{{ $asset->name }}</div>

                <div class="meta-row">
                    <span class="meta-key">Category:</span>
                    <span class="meta-val">{{ $asset->category ?: ($asset->type ?: 'Office Asset') }}</span>
                </div>

                <div class="meta-row">
                    <span class="meta-key">Type:</span>
                    <span class="meta-val">{{ $asset->type }}</span>
                </div>

                <div class="meta-row">
                    <span class="meta-key">Assigned To:</span>
                    <span class="meta-val highlight-owner">
                        {{ $asset->assignee?->name ?? 'Company Pool' }}
                        @if($asset->assignee)
                            ({{ $asset->department_name }})
                        @endif
                    </span>
                </div>

                <div class="meta-row">
                    <span class="meta-key">Location:</span>
                    <span class="meta-val">{{ $asset->location ?: ($asset->warehouse_location ?: 'CMIH Office') }}</span>
                </div>

                <div class="meta-row">
                    <span class="meta-key">Status / Cond:</span>
                    <span class="meta-val">{{ $asset->status }} &bull; {{ $asset->condition }}</span>
                </div>

                <div class="meta-row">
                    <span class="meta-key">Owner:</span>
                    <span class="meta-val">{{ $asset->owner ?: 'CMIH Africa' }}</span>
                </div>
            </div>
        </div>

        <div class="sticker-footer">
            <span>Property of CMIH Africa &bull; Do Not Remove</span>
            <span>portal.cmih.africa</span>
        </div>
    </div>

    {{-- Include local qrcode.min.js and canvas exporter --}}
    <script src="{{ asset('js/qrcode.min.js') }}"></script>
    <script>
        // Automatic print if ?print=1 in URL
        if (new URLSearchParams(window.location.search).has('print')) {
            window.addEventListener('load', () => window.print());
        }

        // Canvas export for PNG download
        document.getElementById('downloadStickerBtn').addEventListener('click', function() {
            const sticker = document.getElementById('stickerBadge');
            const canvas = document.createElement('canvas');
            const scale = 3; // 300 DPI high-resolution
            const width = 380;
            const height = 240;
            canvas.width = width * scale;
            canvas.height = height * scale;
            const ctx = canvas.getContext('2d');
            ctx.scale(scale, scale);

            // Background
            ctx.fillStyle = '#ffffff';
            ctx.roundRect ? ctx.roundRect(0, 0, width, height, 8) : ctx.rect(0, 0, width, height);
            ctx.fill();

            // Border
            ctx.strokeStyle = '#111111';
            ctx.lineWidth = 2.5;
            ctx.stroke();

            // Header bar
            ctx.fillStyle = '#E50914';
            ctx.fillRect(12, 12, 38, 18);
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px sans-serif';
            ctx.fillText('CMIH', 17, 26);

            ctx.fillStyle = '#111111';
            ctx.font = 'bold 11px sans-serif';
            ctx.fillText('CMIH AFRICA ASSET TAG', 56, 26);

            // Asset Tag Badge
            ctx.fillStyle = '#111111';
            ctx.fillRect(width - 120, 12, 108, 20);
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 10px monospace';
            ctx.fillText('{{ $asset->asset_tag_or_code }}', width - 114, 26);

            // Divider
            ctx.strokeStyle = '#111111';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(12, 38);
            ctx.lineTo(width - 12, 38);
            ctx.stroke();

            // Render QR Code onto canvas
            const qrImg = new Image();
            qrImg.crossOrigin = 'anonymous';
            qrImg.onload = function() {
                // QR Box border
                ctx.strokeStyle = '#111111';
                ctx.lineWidth = 1;
                ctx.strokeRect(12, 46, 124, 124);
                ctx.drawImage(qrImg, 14, 48, 120, 120);

                ctx.fillStyle = '#555555';
                ctx.font = 'bold 8px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('SCAN TO VERIFY', 74, 182);
                ctx.textAlign = 'left';

                // Asset Details
                ctx.fillStyle = '#111111';
                ctx.font = 'bold 13px sans-serif';
                ctx.fillText('{{ addslashes($asset->name) }}', 148, 62);

                let y = 82;
                function drawField(label, val, isHighlight = false) {
                    ctx.fillStyle = '#666666';
                    ctx.font = 'bold 8px sans-serif';
                    ctx.fillText(label.toUpperCase(), 148, y);

                    ctx.fillStyle = isHighlight ? '#E50914' : '#111111';
                    ctx.font = isHighlight ? 'bold 10px sans-serif' : '600 9px sans-serif';
                    ctx.fillText(val, 215, y);
                    y += 18;
                }

                drawField('Category:', '{{ addslashes($asset->category ?: ($asset->type ?: "Office Asset")) }}');
                drawField('Type:', '{{ addslashes($asset->type) }}');
                drawField('Assigned:', '{{ addslashes($asset->assignee?->name ?? "Company Pool") }} ({{ addslashes($asset->department_name) }})', true);
                drawField('Location:', '{{ addslashes($asset->location ?: ($asset->warehouse_location ?: "CMIH Office")) }}');
                drawField('Condition:', '{{ addslashes($asset->status) }} • {{ addslashes($asset->condition) }}');
                drawField('Owner:', '{{ addslashes($asset->owner ?: "CMIH Africa") }}');

                // Footer
                ctx.strokeStyle = '#888888';
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(12, 214);
                ctx.lineTo(width - 12, 214);
                ctx.stroke();
                ctx.setLineDash([]);

                ctx.fillStyle = '#666666';
                ctx.font = '7px sans-serif';
                ctx.fillText('PROPERTY OF CMIH AFRICA • DO NOT REMOVE', 12, 228);
                ctx.textAlign = 'right';
                ctx.fillText('portal.cmih.africa', width - 12, 228);

                // Trigger download
                const link = document.createElement('a');
                link.download = 'cmih-sticker-{{ $asset->asset_tag_or_code }}.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            };

            // Convert SVG to data URL
            const svgString = `{!! addslashes(\App\Services\QrCodeService::svg($asset->scan_url, 200, 0)) !!}`;
            qrImg.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgString);
        });
    </script>
</body>
</html>
