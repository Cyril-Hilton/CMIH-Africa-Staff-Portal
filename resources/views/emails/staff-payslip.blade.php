<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip Statement</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f4f5f7; color: #111111; margin: 0; padding: 24px; }
        .container { max-width: 720px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e5ea; border-radius: 12px; overflow: hidden; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .top-rule { height: 8px; background: #e50914; }
        .letterhead { padding: 28px 32px 22px; border-bottom: 1px solid #e6e8ee; }
        .letterhead-grid { display: table; width: 100%; }
        .letterhead-left { display: table-cell; vertical-align: top; width: 48%; }
        .letterhead-right { display: table-cell; vertical-align: top; width: 52%; text-align: right; }
        .logo { display: block; max-width: 245px; height: auto; margin: 0 0 16px; }
        .logo-dark-mode { display: none; }
        .brand-title { color: #111111; font-size: 18px; font-weight: 800; letter-spacing: 1px; margin: 0; text-transform: uppercase; }
        .sub-title { color: #e50914; font-size: 11px; text-transform: uppercase; letter-spacing: 1.6px; margin: 6px 0 0; font-weight: 800; }
        .contact-title { color: #111111; font-size: 11px; font-weight: 800; letter-spacing: 1.4px; margin: 0 0 8px; text-transform: uppercase; }
        .office { margin-bottom: 10px; color: #4b5563; font-size: 11px; line-height: 1.55; }
        .office strong { display: block; color: #111111; font-size: 12px; margin-bottom: 2px; }
        .office a { color: #e50914; text-decoration: none; }
        .content { padding: 28px 32px 32px; }
        .statement-title { margin: 0 0 18px; color: #111111; font-size: 24px; line-height: 1.2; font-weight: 800; letter-spacing: 0.3px; }
        .period-badge { display: inline-block; margin-top: 8px; padding: 7px 12px; border-radius: 999px; background: #fff1f2; border: 1px solid #fecdd3; color: #b91c1c; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .meta-grid { display: table; width: 100%; margin-bottom: 24px; font-size: 13px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 12px 16px; color: #4b5563; border-bottom: 1px solid #e5e7eb; }
        .meta-row:last-child .meta-cell { border-bottom: 0; }
        .meta-cell strong { color: #111111; }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        .table th { background: #111111; color: #ffffff; text-transform: uppercase; font-size: 11px; padding: 12px; text-align: left; letter-spacing: 1px; }
        .table td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #1f2937; }
        .text-right { text-align: right; }
        .highlight-net { background: #111111; border: 1px solid #111111; border-radius: 12px; padding: 18px; text-align: center; margin-top: 24px; }
        .net-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #d1d5db; }
        .net-val { font-size: 30px; font-weight: bold; color: #22c55e; margin-top: 6px; }
        .archive-note { font-size: 12px; color: #4b5563; text-align: center; margin: 20px 0 0; line-height: 1.6; }
        .archive-note a { color: #e50914; font-weight: 700; text-decoration: none; }
        .action-row { text-align: center; margin: 24px 0 4px; }
        .download-button, .print-button { display: inline-block; border-radius: 999px; padding: 12px 18px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; text-decoration: none; }
        .download-button { background: #e50914; color: #ffffff; border: 1px solid #e50914; }
        .print-button { background: #ffffff; color: #111111; border: 1px solid #cbd5e1; cursor: pointer; margin-left: 8px; }
        .footer { text-align: center; padding: 18px 32px 24px; border-top: 1px solid #e6e8ee; font-size: 11px; color: #6b7280; background: #fafafa; line-height: 1.5; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .container { border: 0; border-radius: 0; box-shadow: none; }
            .action-row { display: none; }
            .logo-light-mode { display: block !important; }
            .logo-dark-mode { display: none !important; }
        }
        @media (prefers-color-scheme: dark) {
            body { background-color: #0d0d0d; color: #ffffff; }
            .container { background: #141414; border-color: #2a2a2a; box-shadow: none; }
            .letterhead { border-bottom-color: #2a2a2a; }
            .logo-light-mode { display: none !important; }
            .logo-dark-mode { display: block !important; }
            .brand-title, .contact-title, .office strong, .statement-title, .meta-cell strong { color: #ffffff; }
            .office, .meta-cell, .table td, .archive-note { color: #d1d5db; }
            .meta-grid { background: #1a1a1a; border-color: #2a2a2a; }
            .meta-cell { border-bottom-color: #2a2a2a; }
            .table th { background: #e50914; color: #ffffff; }
            .table td { border-bottom-color: #2a2a2a; }
            .highlight-net { background: #000000; border-color: #2a2a2a; }
            .print-button { background: #141414; color: #ffffff; border-color: #3f3f46; }
            .footer { background: #101010; border-top-color: #2a2a2a; color: #9ca3af; }
        }
        @media only screen and (max-width: 620px) {
            body { padding: 10px; }
            .letterhead, .content, .footer { padding-left: 18px; padding-right: 18px; }
            .letterhead-left, .letterhead-right, .meta-cell { display: block; width: auto; text-align: left; }
            .letterhead-right { margin-top: 18px; }
            .logo { max-width: 210px; }
            .statement-title { font-size: 21px; }
        }
    </style>
</head>
<body>
    @php
        $logoLightPath = public_path('images/logo/logo-light.png');
        $logoDarkPath = public_path('images/logo/logo-dark.png');
        $logoLightUrl = isset($message) && file_exists($logoLightPath)
            ? $message->embed($logoLightPath)
            : asset('images/logo/logo-light.png');
        $logoDarkUrl = isset($message) && file_exists($logoDarkPath)
            ? $message->embed($logoDarkPath)
            : asset('images/logo/logo-dark.png');
        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'portal.payroll.payslip.signed',
            now()->addDays(14),
            ['payslip' => $payslip->id]
        );
    @endphp

    <div class="container">
        <div class="top-rule"></div>

        <div class="letterhead">
            <div class="letterhead-grid">
                <div class="letterhead-left">
                    <img src="{{ $logoLightUrl }}" alt="CMIH Africa" class="logo logo-light-mode">
                    <img src="{{ $logoDarkUrl }}" alt="CMIH Africa" class="logo logo-dark-mode">
                    <h1 class="brand-title">CMIH Africa</h1>
                    <p class="sub-title">Official Payroll Document</p>
                </div>

                <div class="letterhead-right">
                    <p class="contact-title">Contact</p>

                    <div class="office">
                        <strong>Ghana Office</strong>
                        Email: <a href="mailto:info@cmihgh.com">info@cmihgh.com</a><br>
                        Phone: +233 542204282<br>
                        Location: No. 7 Afum Street, North Legon. Accra - Ghana
                    </div>

                    <div class="office">
                        <strong>Nigeria Office</strong>
                        CONCEPTS MAKE IT HAPPEN LTD, NIGERIA<br>
                        Phone: +234 8065776473<br>
                        Location: 25, Ajanaku Street, Awuse Estates, Opebi Ikeja, Lagos, Nigeria.
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <h2 class="statement-title">
                Staff Payslip Statement<br>
                <span class="period-badge">{{ $payslip->period_label }}</span>
            </h2>

            <div class="meta-grid">
                <div class="meta-row">
                    <div class="meta-cell">Employee: <strong>{{ $staff->name }}</strong></div>
                    <div class="meta-cell text-right">Department: <strong>{{ ucwords(str_replace('_', ' ', $staff->department ?? 'N/A')) }}</strong></div>
                </div>
                <div class="meta-row">
                    <div class="meta-cell">Bank: <strong>{{ $payslip->bank_name ?: 'N/A' }}</strong></div>
                    <div class="meta-cell text-right">Account / MoMo: <strong>{{ $payslip->account_number ?: ($payslip->momo_number ?: 'N/A') }}</strong></div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Earning / Deduction Item</th>
                        <th class="text-right">Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Gross Base Salary</td>
                        <td class="text-right">GHS {{ number_format($payslip->gross_salary, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Bonuses / Allowances (+)</td>
                        <td class="text-right" style="color: #059669;">+ GHS {{ number_format($payslip->bonuses, 2) }}</td>
                    </tr>
                    <tr>
                        <td>SSNIT Employee Contribution (5.5%) (-)</td>
                        <td class="text-right" style="color: #dc2626;">- GHS {{ number_format($payslip->ssnit_employee, 2) }}</td>
                    </tr>
                    <tr>
                        <td>GRA PAYE Income Tax (-)</td>
                        <td class="text-right" style="color: #dc2626;">- GHS {{ number_format($payslip->paye_tax, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Other Deductions (-)</td>
                        <td class="text-right" style="color: #dc2626;">- GHS {{ number_format($payslip->other_deductions, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="highlight-net">
                <div class="net-label">Net Payable Salary</div>
                <div class="net-val">GHS {{ number_format($payslip->net_salary, 2) }}</div>
            </div>

            <p class="archive-note">
                A copy of this payslip has also been permanently archived in your personal CMIH Portal account.
                You can log in and re-download it anytime at <a href="{{ route('portal.payroll') }}">cmih.africa/portal/payroll</a>.
            </p>

            <div class="action-row">
                <a href="{{ $downloadUrl }}" class="download-button">Download / View Payslip</a>
                @if(! isset($message))
                    <button type="button" class="print-button" onclick="window.print()">Print / Save as PDF</button>
                @endif
            </div>
        </div>

        <div class="footer">
            Confidential Document &bull; Issued by CMIH Africa HR & Payroll Department &bull; {{ now()->format('M d, Y') }}
        </div>
    </div>
</body>
</html>
