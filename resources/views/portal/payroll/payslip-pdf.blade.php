<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payslip {{ $payslip->period_label }}</title>
<style>
@page { margin: 32px 38px; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #182333; line-height: 1.5; }
.rule { height: 6px; background: #e50914; margin-bottom: 22px; }
table { width: 100%; border-collapse: collapse; }
.header td { vertical-align: top; width: 50%; }
.logo { width: 190px; height: auto; }
.contacts { font-size: 8px; text-align: right; color: #5b6472; }
h1 { font-size: 22px; margin: 24px 0 4px; }
.period { font-size: 12px; font-weight: bold; color: #c00915; margin-bottom: 22px; }
.meta { background: #f4f6f9; margin-bottom: 22px; }
.meta td { padding: 10px 12px; width: 50%; vertical-align: top; }
.label { font-size: 8px; text-transform: uppercase; color: #626d7c; }
.amounts th { padding: 10px; background: #182333; color: white; text-align: left; font-size: 9px; }
.amounts td { padding: 12px 10px; border-bottom: 1px solid #e0e5eb; }
.amounts .right { text-align: right; }
.net { background: #182333; color: white; padding: 18px; text-align: center; margin: 24px 0; }
.net strong { display: block; font-size: 25px; color: #73e6ac; }
.note { color: #626d7c; font-size: 9px; }
.footer { border-top: 1px solid #dce2e9; padding-top: 14px; margin-top: 28px; font-size: 8px; color: #626d7c; text-align: center; }
</style></head><body>
<div class="rule"></div>
<table class="header"><tr><td><img src="{{ $logo }}" class="logo" alt="CMIH Africa"><p><strong>CMIH AFRICA</strong><br>Official Payroll Statement</p></td><td class="contacts"><strong>Ghana Office</strong><br>No. 7 Afum Street, North Legon, Accra<br>+233 542204282<br>info@cmihgh.com<br><br><strong>Nigeria Office</strong><br>CONCEPTS MAKE IT HAPPEN LTD, NIGERIA<br>25, Ajanaku Street, Awuse Estates, Opebi Ikeja, Lagos<br>+234 8065776473</td></tr></table>
<h1>Staff Payslip Statement</h1><div class="period">{{ $payslip->period_label }}</div>
<table class="meta"><tr><td><div class="label">Staff member</div><strong>{{ $staff->name }}</strong></td><td><div class="label">Department</div><strong>{{ \App\Models\User::departmentLabel($staff->department) }}</strong></td></tr><tr><td><div class="label">Bank</div><strong>{{ $payslip->bank_name ?: 'N/A' }}</strong></td><td><div class="label">Account / MoMo</div><strong>{{ $payslip->account_number ?: ($payslip->momo_number ?: 'N/A') }}</strong></td></tr></table>
<table class="amounts"><thead><tr><th>Earning / Deduction Item</th><th class="right">Amount (GHS)</th></tr></thead><tbody>
@foreach(['Gross Base Salary'=>$payslip->gross_salary,'Bonuses / Allowances (+)'=>$payslip->bonuses,'SSNIT Employee Contribution (-)'=>$payslip->ssnit_employee,'GRA PAYE Income Tax (-)'=>$payslip->paye_tax,'Other Deductions (-)'=>$payslip->other_deductions] as $label=>$amount)<tr><td>{{ $label }}</td><td class="right">GHS {{ number_format($amount,2) }}</td></tr>@endforeach
</tbody></table>
<div class="net">NET PAYABLE SALARY<strong>GHS {{ number_format($payslip->net_salary,2) }}</strong></div>
<p class="note">This statement is a copy of the issued monthly payslip held in your personal CMIH Portal account.</p>
<div class="footer">Confidential Document · CMIH Africa HR &amp; Payroll Department<br>Issued {{ $payslip->issued_at?->format('d M Y') ?? $payslip->created_at?->format('d M Y') }}</div>
</body></html>
