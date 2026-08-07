<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $isRequestNotice ? 'Leave Request Notice' : 'Leave Approval Needed' }}</title>
        <style>
            body { margin: 0; padding: 0; background: #0a0a0a; color: #ffffff; font-family: 'Sora', Arial, sans-serif; }
            .container { max-width: 640px; margin: 0 auto; padding: 32px 24px; }
            .card { background: #111111; border: 1px solid #333333; border-radius: 16px; padding: 24px; }
            .label { font-size: 12px; letter-spacing: 0.3em; text-transform: uppercase; color: #f0f0f0; }
            .title { font-size: 28px; margin: 12px 0 8px; color: #ffffff; }
            .text { font-size: 14px; line-height: 1.6; color: #e0e0e0; margin-bottom: 12px; }
            .key { font-weight: 700; color: #ffffff; }
            .badge { display: inline-block; border-radius: 999px; border: 1px solid #7f1d1d; background: #450a0a; color: #fecaca; padding: 6px 10px; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; font-weight: 700; }
            .button { display: inline-block; margin-top: 16px; background: #dc2626; color: #ffffff !important; text-decoration: none; border-radius: 12px; padding: 12px 18px; font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; font-weight: 800; }
            .footer { margin-top: 24px; font-size: 12px; color: #b0b0b0; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="label">CMIH Africa</div>
                <h1 class="title">{{ $isRequestNotice ? 'Leave Request Notice' : 'Leave Approval Needed' }}</h1>
                <p class="text">Hello {{ $approver->name }},</p>
                <p class="text">
                    @if($isRequestNotice)
                        <span class="key">{{ $staff->name }}</span> submitted this leave request. This resend is a request notice for your records.
                    @else
                        <span class="key">{{ $staff->name }}</span> submitted a leave request that needs attention in the portal.
                    @endif
                </p>
                <p class="text"><span class="key">Leave Type:</span> {{ ucfirst($leave->leave_type) }} Leave</p>
                <p class="text"><span class="key">Start Date:</span> {{ $leave->start_date->format('M d, Y') }}</p>
                <p class="text"><span class="key">End Date:</span> {{ $leave->end_date->format('M d, Y') }}</p>
                <p class="text"><span class="key">Line Manager:</span> {{ $lineManager?->name ?? 'Not selected' }}</p>
                <p class="text"><span class="key">Duty Cover:</span> {{ $coveringStaff?->name ?? 'Not selected' }}</p>
                <p class="text"><span class="badge">{{ str_replace('_', ' ', $leave->status) }}</span></p>
                @if($leave->comments)
                    <p class="text"><span class="key">Comments / Handover:</span> {{ $leave->comments }}</p>
                @endif
                <a href="{{ route('portal.leaves') }}" class="button">Review Leave</a>
                <p class="footer">CMIH Africa — We Make It Happen</p>
            </div>
        </div>
    </body>
</html>
