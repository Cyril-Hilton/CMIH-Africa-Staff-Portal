<?php

namespace App\Mail;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffPayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public Payslip $payslip;
    public User $staff;

    public function __construct(Payslip $payslip, User $staff)
    {
        $this->payslip = $payslip;
        $this->staff = $staff;
    }

    public function build()
    {
        $periodLabel = $this->payslip->period_label;
        $subject = "📄 Official Payslip Statement - {$periodLabel} | CMIH Africa";

        return $this->subject($subject)
            ->view('emails.staff-payslip');
    }
}
