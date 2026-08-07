<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class AwardCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $awardType;
    public $periodLabel;
    public $user;
    public $departmentLabel;
    public $cvoName;
    public $hrManagerName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $awardType, string $period, User $user, ?string $department = null)
    {
        $this->user = $user;

        // Beautify award type label
        $awardLabels = [
            'employee_of_the_month' => 'Employee of the Month',
            'department_of_the_month' => 'Department of the Month',
            'employee_of_the_year' => 'Employee of the Year',
            'department_of_the_year' => 'Department of the Year',
        ];
        $this->awardType = $awardLabels[$awardType] ?? ucwords(str_replace('_', ' ', $awardType));

        // Beautify period label
        if (strlen($period) === 7) {
            $this->periodLabel = Carbon::createFromFormat('!Y-m', $period)->format('F Y');
        } else {
            $this->periodLabel = $period;
        }

        // Department label
        if ($department) {
            $departments = [
                'hr_admin'            => 'HR & Admin',
                'finance'             => 'Finance',
                'client_relations'    => 'Client Relations',
                'operations_projects' => 'Operations / Projects',
                'brands_marketing'    => 'Brands & Marketing',
                'creatives'           => 'Creatives',
            ];
            $this->departmentLabel = $departments[$department] ?? ucwords(str_replace('_', ' ', $department));
        } else {
            $this->departmentLabel = null;
        }

        // CVO name — use the CVO/Super Admin account name if found, else fall back to config constant
        $cvoUser = User::where('access_role', 'super_admin')
            ->orWhere('access_role', 'cvo')
            ->whereNotNull('name')
            ->orderByDesc('id')
            ->first();
        $this->cvoName = $cvoUser?->name ?? config('cmih.cvo_name', 'Solomon Nanfa');

        // HR Manager name — look up the HR & Admin department manager from the database
        $hrManager = User::whereRaw("LOWER(TRIM(department)) IN ('hr_admin','hr_and_admin','admin','hr','human_resources')")
            ->where(function ($q) {
                $q->whereRaw("LOWER(TRIM(job_level)) = 'manager'")
                  ->orWhereRaw("LOWER(TRIM(position_title)) IN ('manager','hr manager','department head')");
            })
            ->whereNotNull('name')
            ->orderByDesc('id')
            ->first();
        $this->hrManagerName = $hrManager?->name ?? 'HR Manager';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = "🏆 Congratulations! You won {$this->awardType} ({$this->periodLabel})";
        
        return $this->subject($subject)
            ->view('emails.winner-certificate');
    }
}
