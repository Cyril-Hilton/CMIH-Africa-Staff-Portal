<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip Statement</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #0d0d0d; color: #ffffff; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #141414; border: 1px solid #2a2a2a; border-radius: 16px; padding: 32px; }
        .header { text-align: center; border-bottom: 1px solid #2a2a2a; padding-bottom: 20px; margin-bottom: 24px; }
        .brand-title { color: #e50914; font-size: 24px; font-weight: 800; letter-spacing: 2px; margin: 0; }
        .sub-title { color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
        .meta-grid { display: table; width: 100%; margin-bottom: 24px; font-size: 13px; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 6px 0; color: #aaaaaa; }
        .meta-cell strong { color: #ffffff; }
        .table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        .table th { background: #1f1f1f; color: #888888; text-transform: uppercase; font-size: 11px; padding: 10px; text-align: left; letter-spacing: 1px; }
        .table td { padding: 10px; border-bottom: 1px solid #222222; }
        .text-right { text-align: right; }
        .highlight-net { background: rgba(229, 9, 20, 0.1); border: 1px solid rgba(229, 9, 20, 0.3); border-radius: 12px; padding: 16px; text-align: center; margin-top: 24px; }
        .net-val { font-size: 28px; font-weight: bold; color: #10b981; }
        .footer { text-align: center; margin-top: 32px; font-size: 11px; color: #555555; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="brand-title">CMIH AFRICA</h1>
            <p class="sub-title">Official Staff Payslip Statement • {{ $payslip->period_label }}</p>
        </div>

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
                    <td class="text-right" style="color: #10b981;">+ GHS {{ number_format($payslip->bonuses, 2) }}</td>
                </tr>
                <tr>
                    <td>SSNIT Employee Contribution (5.5%) (-)</td>
                    <td class="text-right" style="color: #ef4444;">- GHS {{ number_format($payslip->ssnit_employee, 2) }}</td>
                </tr>
                <tr>
                    <td>GRA PAYE Income Tax (-)</td>
                    <td class="text-right" style="color: #ef4444;">- GHS {{ number_format($payslip->paye_tax, 2) }}</td>
                </tr>
                <tr>
                    <td>Other Deductions (-)</td>
                    <td class="text-right" style="color: #ef4444;">- GHS {{ number_format($payslip->other_deductions, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="highlight-net">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #888888;">Net Payable Salary</div>
            <div class="net-val">GHS {{ number_format($payslip->net_salary, 2) }}</div>
        </div>

        <p style="font-size: 12px; color: #aaaaaa; text-align: center; margin-top: 20px;">
            A copy of this payslip has also been permanently archived in your personal CMIH Portal account. You can log in and re-download it anytime at <a href="{{ route('portal.payroll') }}" style="color: #e50914;">cmih.africa/portal/payroll</a>.
        </p>

        <div class="footer">
            Confidential Document • Issued by CMIH Africa HR & Payroll Department • {{ now()->format('M d, Y') }}
        </div>
    </div>
</body>
</html>
