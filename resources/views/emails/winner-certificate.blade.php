<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate of Excellence — CMIH Africa</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Cormorant+SC:wght@300;400;500;600&family=Great+Vibes&family=Lato:ital,wght@0,300;0,400;1,300&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #0a0a0c;
            font-family: 'Cormorant Garamond', Georgia, serif;
            -webkit-text-size-adjust: none;
            color: #e8e0cc;
        }

        .page-wrap {
            background:
                radial-gradient(ellipse 100% 40% at 50% 0%,
                    rgba(180, 20, 50, 0.12) 0%,
                    transparent 60%),
                #0a0a0c;
            min-height: 100vh;
            padding: 56px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ══════════════════════════
           CERTIFICATE CARD
        ══════════════════════════ */
        .certificate {
            position: relative;
            max-width: 680px;
            width: 100%;
            background: #0d0d10;
            /* Single elegant border — no multi-layer noise */
            border: 1px solid rgba(185, 155, 80, 0.45);
            border-radius: 3px;
            box-shadow:
                inset 0 0 0 8px  #0d0d10,
                inset 0 0 0 9px  rgba(185, 155, 80, 0.20),
                0 40px 120px rgba(0, 0, 0, 0.95),
                0 0 60px rgba(0, 0, 0, 0.6);
        }

        /* ── TOP CRIMSON RULE ── */
        .top-rule {
            height: 3px;
            background: linear-gradient(90deg,
                transparent 0%,
                #b01530 15%,
                #e11d48 50%,
                #b01530 85%,
                transparent 100%);
            border-radius: 3px 3px 0 0;
        }

        /* ── INNER FRAME BORDER ── */
        .inner-frame {
            margin: 22px;
            border: 1px solid rgba(185, 155, 80, 0.15);
            border-radius: 2px;
            padding: 52px 56px 56px;
            position: relative;
        }

        /* Subtle corner accents */
        .inner-frame::before,
        .inner-frame::after {
            content: '';
            position: absolute;
            width: 32px;
            height: 32px;
            border-color: rgba(185, 155, 80, 0.55);
            border-style: solid;
        }
        .inner-frame::before {
            top: -1px; left: -1px;
            border-width: 1.5px 0 0 1.5px;
        }
        .inner-frame::after {
            bottom: -1px; right: -1px;
            border-width: 0 1.5px 1.5px 0;
        }
        /* Additional corners via wrapper */
        .inner-frame .corner-tr,
        .inner-frame .corner-bl {
            position: absolute;
            width: 32px;
            height: 32px;
            border-color: rgba(185, 155, 80, 0.55);
            border-style: solid;
        }
        .inner-frame .corner-tr {
            top: -1px; right: -1px;
            border-width: 1.5px 1.5px 0 0;
        }
        .inner-frame .corner-bl {
            bottom: -1px; left: -1px;
            border-width: 0 0 1.5px 1.5px;
        }

        /* ── LOGO ── */
        .logo-row {
            text-align: center;
            margin-bottom: 36px;
        }
        .cmih-logo {
            max-height: 52px;
            width: auto;
            opacity: 0.92;
        }

        /* ── THIN RULE ── */
        .rule-gold {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(185,155,80,0.6) 20%,
                rgba(185,155,80,0.9) 50%,
                rgba(185,155,80,0.6) 80%,
                transparent 100%);
            margin-bottom: 36px;
        }

        /* ── CERTIFICATE LABEL ── */
        .cert-label {
            text-align: center;
            margin-bottom: 8px;
        }
        .cert-overline {
            font-family: 'Lato', sans-serif;
            font-size: 9px;
            font-weight: 300;
            letter-spacing: 0.55em;
            color: #c0995a;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .cert-main-title {
            font-family: 'Cormorant SC', 'Cormorant Garamond', serif;
            font-size: 38px;
            font-weight: 300;
            color: #f0e8d4;
            letter-spacing: 0.18em;
            line-height: 1;
        }
        .cert-subtitle-of {
            font-family: 'Cormorant Garamond', serif;
            font-size: 15px;
            font-weight: 300;
            font-style: italic;
            color: #c0995a;
            letter-spacing: 0.22em;
            margin-top: 10px;
        }

        /* ── PRESENTED TEXT ── */
        .present-row {
            text-align: center;
            margin: 38px 0 0;
        }
        .present-text {
            font-family: 'Lato', sans-serif;
            font-size: 11px;
            font-weight: 300;
            font-style: italic;
            letter-spacing: 0.12em;
            color: #7a8090;
            margin-bottom: 18px;
        }

        /* ── RECIPIENT NAME ── */
        .recipient-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 48px;
            font-weight: 400;
            font-style: italic;
            color: #f5ead0;
            letter-spacing: 0.02em;
            line-height: 1.1;
            text-shadow: 0 1px 30px rgba(185,155,80,0.15);
        }

        /* Name divider */
        .name-rule {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 16px auto 0;
            max-width: 360px;
        }
        .name-rule-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(185,155,80,0.5));
        }
        .name-rule-line:last-child {
            background: linear-gradient(90deg, rgba(185,155,80,0.5), transparent);
        }
        .name-rule-lozenge {
            width: 5px;
            height: 5px;
            background: #c0995a;
            transform: rotate(45deg);
            flex-shrink: 0;
        }

        /* ── AWARD TYPE ── */
        .award-type-row {
            text-align: center;
            margin: 28px 0 6px;
        }
        .award-type {
            font-family: 'Cormorant SC', 'Cormorant Garamond', serif;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.38em;
            color: #e11d48;
            text-transform: uppercase;
        }
        .period-text {
            font-family: 'Lato', sans-serif;
            font-size: 10px;
            font-weight: 300;
            letter-spacing: 0.35em;
            color: #8a7a5a;
            text-transform: uppercase;
            margin-top: 8px;
        }

        /* ── CITATION ── */
        .citation-row {
            margin: 32px auto 0;
            max-width: 480px;
            text-align: center;
        }
        .citation-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: 15px;
            font-weight: 300;
            font-style: italic;
            color: #8a909a;
            line-height: 2;
            letter-spacing: 0.02em;
        }
        .citation-text strong {
            font-style: normal;
            font-weight: 500;
            color: #b0a080;
        }

        /* ── FOOTER RULE ── */
        .footer-rule {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(185,155,80,0.3) 20%,
                rgba(185,155,80,0.5) 50%,
                rgba(185,155,80,0.3) 80%,
                transparent 100%);
            margin: 44px 0 36px;
        }

        /* ── SIGNATURES ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-cell {
            width: 50%;
            text-align: center;
            padding: 0 24px;
            vertical-align: bottom;
        }
        /* Calligraphy name */
        .sig-calligraphy {
            font-family: 'Great Vibes', cursive;
            font-size: 34px;
            color: #ddd0aa;
            line-height: 1;
            margin-bottom: 2px;
            letter-spacing: 0.01em;
        }
        /* Thin gold line under signature */
        .sig-line {
            width: 120px;
            height: 1px;
            background: rgba(185,155,80,0.45);
            margin: 10px auto 12px;
        }
        .sig-printed-name {
            font-family: 'Cormorant SC', serif;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: 0.18em;
            color: #c8c0a8;
        }
        .sig-role {
            font-family: 'Lato', sans-serif;
            font-size: 8.5px;
            font-weight: 300;
            letter-spacing: 0.22em;
            color: #e11d48;
            text-transform: uppercase;
            margin-top: 5px;
            opacity: 0.85;
        }

        /* ── BOTTOM GOLD RULE ── */
        .bottom-rule {
            height: 2px;
            background: linear-gradient(90deg,
                transparent 0%,
                #8b6a20 10%,
                #c9a84c 30%,
                #f0d878 50%,
                #c9a84c 70%,
                #8b6a20 90%,
                transparent 100%);
            border-radius: 0 0 3px 3px;
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="certificate">

        <div class="top-rule"></div>

        <div class="inner-frame">
            <div class="corner-tr"></div>
            <div class="corner-bl"></div>

            {{-- Logo --}}
            @php
                $logoPath = public_path('images/logo/logo-dark.png');
                $logoSrc = file_exists($logoPath)
                    ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
                    : 'https://cmih.africa/images/logo/logo-dark.png';
            @endphp
            <div class="logo-row">
                <img src="{{ $logoSrc }}" alt="CMIH Africa" class="cmih-logo">
            </div>

            <div class="rule-gold"></div>

            {{-- Certificate heading --}}
            <div class="cert-label">
                <div class="cert-overline">Award of Recognition</div>
                <div class="cert-main-title">Certificate</div>
                <div class="cert-subtitle-of">of Excellence</div>
            </div>

            {{-- Presented to --}}
            <div class="present-row">
                <div class="present-text">This certificate is proudly presented to</div>
                <div class="recipient-name">{{ $user->name }}</div>
                <div class="name-rule">
                    <div class="name-rule-line"></div>
                    <div class="name-rule-lozenge"></div>
                    <div class="name-rule-line"></div>
                </div>
            </div>

            {{-- Award type + period --}}
            <div class="award-type-row">
                <div class="award-type">{{ $awardType }}</div>
                <div class="period-text">{{ $periodLabel }}</div>
            </div>

            {{-- Citation --}}
            <div class="citation-row">
                <div class="citation-text">
                    @if($departmentLabel)
                        For outstanding collaboration and performance as a key member of the
                        <strong>{{ $departmentLabel }}</strong> department, which has won the prestigious
                        <strong>{{ $awardType }}</strong> award.
                    @else
                        In recognition of exceptional performance, unwavering dedication,
                        and the operational excellence that led to winning the
                        <strong>{{ $awardType }}</strong> award.
                    @endif
                </div>
            </div>

            {{-- Signature row --}}
            <div class="footer-rule"></div>

            <table class="sig-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="sig-cell">
                        <div class="sig-calligraphy">{{ $cvoName }}</div>
                        <div class="sig-line"></div>
                        <div class="sig-printed-name">{{ $cvoName }}</div>
                        <div class="sig-role">Chief Visionary Officer</div>
                    </td>
                    <td class="sig-cell">
                        <div class="sig-calligraphy">{{ $hrManagerName }}</div>
                        <div class="sig-line"></div>
                        <div class="sig-printed-name">{{ $hrManagerName }}</div>
                        <div class="sig-role">CMIH HR &amp; Admin</div>
                    </td>
                </tr>
            </table>

        </div>

        <div class="bottom-rule"></div>

    </div>
</div>
</body>
</html>
