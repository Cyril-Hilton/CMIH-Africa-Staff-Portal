<?php

namespace App\Console\Commands;

use App\Mail\LeaveApprovalNeededMail;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResendLeaveApprovalPrompts extends Command
{
    protected $signature = 'leave:resend-approval-prompts
        {--email= : Applicant company/contact email to search for}
        {--name= : Applicant name search, e.g. Barakah}
        {--leave-id= : Specific leave application ID}
        {--request-notice : Send the original request notice to line manager + HR managers, even if the leave is no longer pending}
        {--send : Actually send emails and in-app notifications; without this it only previews recipients}';

    protected $description = 'Resend missed leave approval prompt emails for a pending leave request.';

    public function handle(): int
    {
        $leave = $this->resolveLeave();

        if (! $leave) {
            $this->error('No matching pending leave request was found.');
            return self::FAILURE;
        }

        $recipients = $this->recipientsFor($leave);

        if ($recipients->isEmpty()) {
            $this->error("No active recipients were found for leave #{$leave->id} at status {$leave->status}.");
            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        $requestNotice = (bool) $this->option('request-notice');
        $title = $requestNotice ? 'leave request notices' : 'leave approval prompts';
        $this->info(($send ? 'Sending' : 'Previewing') . " {$title} for leave #{$leave->id}.");
        $this->line("Applicant: {$leave->user?->name} <" . ($leave->user?->contact_email ?: $leave->user?->email ?: 'no-email') . '>');
        $this->line('Current status: ' . str_replace('_', ' ', $leave->status));

        foreach ($recipients as $recipient) {
            $email = $recipient->contact_email ?: $recipient->email;
            $this->line("- {$recipient->name} <{$email}>");

            if (! $send || ! $email) {
                continue;
            }

            try {
                Mail::to($email)->send(new LeaveApprovalNeededMail($leave, $recipient, $requestNotice));
                NotificationService::send(
                    (int) $recipient->id,
                    $requestNotice ? 'Leave Request Notice' : 'Leave Approval Needed',
                    $requestNotice
                        ? "{$leave->user->name} submitted a {$leave->leave_type} leave request. This is a resend notice."
                        : "{$leave->user->name} submitted a {$leave->leave_type} leave request that needs approval.",
                    route('portal.leaves')
                );
            } catch (\Throwable $e) {
                Log::error('Manual leave approval prompt resend failed: ' . $e->getMessage(), [
                    'leave_id' => $leave->id,
                    'recipient_id' => $recipient->id,
                ]);
                $this->error("Failed sending to {$recipient->name}: {$e->getMessage()}");
            }
        }

        $this->info($send ? 'Resend complete.' : 'Preview complete. Re-run with --send to dispatch.');

        return self::SUCCESS;
    }

    private function resolveLeave(): ?LeaveApplication
    {
        $query = LeaveApplication::with(['user', 'lineManager', 'coveringStaff'])
            ->latest();

        if (! $this->option('request-notice')) {
            $query->whereIn('status', ['pending_manager', 'pending_cvo', 'pending_hr']);
        }

        if ($leaveId = $this->option('leave-id')) {
            return $query->whereKey((int) $leaveId)->first();
        }

        $email = trim((string) $this->option('email'));
        $name = trim((string) $this->option('name'));

        if ($email !== '') {
            $normalizedEmail = strtolower($email);
            $query->whereHas('user', function ($userQuery) use ($normalizedEmail) {
                $userQuery->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->orWhereRaw('LOWER(contact_email) = ?', [$normalizedEmail]);
            });
        }

        if ($name !== '') {
            $query->whereHas('user', function ($userQuery) use ($name) {
                $userQuery->where('name', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $name) . '%');
            });
        }

        return $query->first();
    }

    private function recipientsFor(LeaveApplication $leave): Collection
    {
        $ids = collect();

        if ($this->option('request-notice')) {
            $ids->push($leave->line_manager_id);
            $ids = $ids->merge($this->activeHrManagerIds($leave->user_id));
        } elseif ($leave->status === 'pending_manager') {
            $ids->push($leave->line_manager_id);
            $ids = $ids->merge($this->activeHrManagerIds($leave->user_id));
        } elseif ($leave->status === 'pending_cvo') {
            $ids = $ids->merge(NotificationService::activeCvoApproverIds($leave->user_id));
        } elseif ($leave->status === 'pending_hr') {
            $ids = $ids->merge(NotificationService::activeHrApproverIds($leave->user_id));
        }

        return User::whereIn('id', $ids->filter()->unique()->values()->all())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function activeHrManagerIds(?int $excludeUserId = null): array
    {
        return User::internalStaff()
            ->where('status', 'active')
            ->get()
            ->filter(function (User $user) use ($excludeUserId) {
                if ($excludeUserId && (int) $user->id === (int) $excludeUserId) {
                    return false;
                }

                $department = strtolower(trim((string) $user->department));
                $position = strtolower(trim((string) $user->position_title));
                $jobLevel = strtolower(trim((string) $user->job_level));

                return in_array($department, ['hr_admin', 'admin', 'hr'], true)
                    && (
                        $user->access_role === 'manager'
                        || $jobLevel === 'manager'
                        || in_array($position, ['manager', 'department head'], true)
                    );
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
