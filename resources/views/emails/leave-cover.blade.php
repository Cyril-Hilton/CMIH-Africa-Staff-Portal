<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Duty Cover Delegation Notification</title>
        <style>
            body { margin: 0; padding: 0; background: #0a0a0a; color: #ffffff; font-family: 'Sora', Arial, sans-serif; }
            .container { max-width: 640px; margin: 0 auto; padding: 32px 24px; }
            .card { background: #111111; border: 1px solid #333333; border-radius: 16px; padding: 24px; }
            .label { font-size: 12px; letter-spacing: 0.3em; text-transform: uppercase; color: #f0f0f0; }
            .title { font-size: 28px; margin: 12px 0 8px; color: #ffffff; }
            .text { font-size: 14px; line-height: 1.6; color: #e0e0e0; margin-bottom: 12px; }
            .key { font-weight: 600; color: #ffffff; }
            .footer { margin-top: 24px; font-size: 12px; color: #b0b0b0; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="label">CMIH Africa</div>
                <h1 class="title">Leave Cover Delegation</h1>
                <p class="text">Hello {{ $coveringStaff->name }},</p>
                <p class="text">You have been designated as the duty cover colleague for <span class="key">{{ $staff->name }}</span> during their upcoming leave period.</p>
                <p class="text"><span class="key">Leave Type:</span> {{ ucfirst($leave->leave_type) }} Leave</p>
                <p class="text"><span class="key">Start Date:</span> {{ $leave->start_date->format('M d, Y') }}</p>
                <p class="text"><span class="key">End Date:</span> {{ $leave->end_date->format('M d, Y') }}</p>
                @if($leave->comments)
                    <p class="text"><span class="key">Comments / Handover Instructions:</span> {{ $leave->comments }}</p>
                @endif
                <p class="footer">Thank you for your support in keeping CMIH operations running smoothly.</p>
                <p class="footer">CMIH Africa — We Make It Happen</p>
            </div>
        </div>
    </body>
</html>
