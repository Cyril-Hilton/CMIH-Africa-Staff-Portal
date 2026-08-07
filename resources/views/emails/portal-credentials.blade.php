<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CMIH Portal Credentials</title>
        <style>
            body { margin: 0; padding: 0; background: #0a0a0a; color: #ffffff; font-family: 'Sora', Arial, sans-serif; }
            .container { max-width: 640px; margin: 0 auto; padding: 32px 24px; }
            .card { background: #111111; border: 1px solid #333333; border-radius: 16px; padding: 24px; }
            .label { font-size: 12px; letter-spacing: 0.3em; text-transform: uppercase; color: #f0f0f0; } /* lighter */
            .title { font-size: 28px; margin: 12px 0 8px; color: #ffffff; }
            .text { font-size: 14px; line-height: 1.6; color: #e0e0e0; margin-bottom: 12px; } /* lighter */
            .key { font-weight: 600; color: #ffffff; }
            .cta { display: inline-block; margin-top: 18px; padding: 12px 24px; border-radius: 999px; background-color: #e21c1e; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; }
            .footer { margin-top: 24px; font-size: 12px; color: #b0b0b0; text-align: center; } /* lighter */
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="label">CMIH Africa</div>
                <h1 class="title">Welcome to the CMIH Portal</h1>
                <p class="text">Hello {{ $user->name }},</p>
                <p class="text">Your portal access has been created. Use the credentials below to sign in once your account is approved by the super admin.</p>
                <p class="text"><span class="key">Company Email:</span> {{ $user->email }}</p>
                <p class="text"><span class="key">Temporary Password:</span> {{ $temporaryPassword }}</p>
                <a class="cta" href="{{ $loginUrl }}">Sign in to the CMIH Portal</a>
                <p class="footer">For security, please reset your password after your first successful login.</p>
                <p class="footer">CMIH Africa — We Make It Happen</p>
            </div>
        </div>
    </body>
</html>
