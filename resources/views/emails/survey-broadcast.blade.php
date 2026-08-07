<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Message from CMIH Africa' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #0c0c0c;
            color: #f0f0f0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            padding: 32px 16px;
        }
        .wrapper { max-width: 620px; margin: 0 auto; }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid #222;
            margin-bottom: 28px;
        }
        .brand { font-size: 13px; font-weight: 700; letter-spacing: 0.25em; text-transform: uppercase; color: #E50914; }
        .survey-label { font-size: 11px; color: #666; letter-spacing: 0.15em; text-transform: uppercase; }

        /* Card */
        .card {
            background: #141414;
            border: 1px solid #2a2a2a;
            border-radius: 20px;
            overflow: hidden;
        }

        /* Red top bar */
        .card-accent { height: 5px; background: linear-gradient(90deg, #E50914, #b30000); }

        .card-body { padding: 36px 32px; }

        .greeting { font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 20px; }

        /* Body text */
        .message-body {
            font-size: 15px;
            color: #cccccc;
            white-space: pre-line;
            line-height: 1.8;
            margin-bottom: 24px;
        }

        /* Event Details Card */
        .event-details {
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 14px;
            padding: 20px 24px;
            margin-top: 24px;
        }
        .event-details-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: #E50914;
            margin-bottom: 16px;
        }
        .event-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .event-icon {
            font-size: 16px;
            width: 24px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .event-value { font-size: 14px; color: #e0e0e0; }
        .event-key { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 2px; }

        /* Map link button */
        .map-btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 20px;
            background: #E50914;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
        }

        /* Divider */
        .divider { border: none; border-top: 1px solid #222; margin: 28px 0; }

        /* Footer */
        .footer { text-align: center; padding: 24px 32px; border-top: 1px solid #1a1a1a; }
        .footer-text { font-size: 12px; color: #555; line-height: 1.6; }
        .footer-brand { font-size: 11px; color: #E50914; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; margin-top: 8px; }
    </style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <span class="brand">CMIH Africa</span>
        @if($surveyTitle)
            <span class="survey-label">{{ $surveyTitle }}</span>
        @endif
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-accent"></div>
        <div class="card-body">

            <!-- Greeting -->
            <p class="greeting">Hello {{ $recipientName }},</p>

            <!-- Custom Body Message -->
            <div class="message-body">{{ $body }}</div>

            <!-- Event Details Block (shown only when at least one detail is set) -->
            @if($eventDate || $eventTime || $eventLocation || $eventMapUrl)
                <div class="event-details">
                    <p class="event-details-title">📅 Event Details</p>

                    @if($eventDate)
                        <div class="event-row">
                            <span class="event-icon">📅</span>
                            <div>
                                <p class="event-key">Date</p>
                                <p class="event-value">{{ $eventDate }}</p>
                            </div>
                        </div>
                    @endif

                    @if($eventTime)
                        <div class="event-row">
                            <span class="event-icon">⏰</span>
                            <div>
                                <p class="event-key">Time</p>
                                <p class="event-value">{{ $eventTime }}</p>
                            </div>
                        </div>
                    @endif

                    @if($eventLocation)
                        <div class="event-row">
                            <span class="event-icon">📍</span>
                            <div>
                                <p class="event-key">Location</p>
                                <p class="event-value">{{ $eventLocation }}</p>
                            </div>
                        </div>
                    @endif

                    @if($eventMapUrl)
                        <a href="{{ $eventMapUrl }}" class="map-btn" target="_blank">
                            📍 View on Google Maps
                        </a>
                    @endif
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="footer">
            @if($senderName)
                <p class="footer-text">Sent by <strong style="color:#ccc">{{ $senderName }}</strong> via the CMIH Portal</p>
            @endif
            <p class="footer-text" style="margin-top:8px">If you believe this was sent in error, please disregard this email.</p>
            <p class="footer-brand">CMIH Africa — We Make It Happen</p>
        </div>
    </div>

</div>
</body>
</html>
