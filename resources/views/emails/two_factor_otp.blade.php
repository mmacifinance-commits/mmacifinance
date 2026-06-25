<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            color: #374151;
            line-height: 1.5;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .header {
            background-color: #1b283d;
            padding: 40px 20px;
            text-align: center;
            border-bottom-left-radius: 5px;
            border-bottom-right-radius: 5px;
            margin: 15px 25px 0 25px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .header h1 {
            color: #facc15;
            /* Mustard */
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 700;
        }

        .header p {
            color: #9ca3af;
            margin: 0;
            font-size: 14px;
        }

        .content {
            padding: 40px;
            text-align: center;
        }

        .greeting {
            font-size: 16px;
            text-align: left;
            margin-bottom: 20px;
        }

        .message-text {
            font-size: 14px;
            color: #4b5563;
            text-align: left;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .otp-container {
            display: inline-block;
            border: 2px dashed #1b283d;
            border-radius: 10px;
            padding: 15px 30px;
            margin-bottom: 20px;
            background-color: #f8fafc;
        }

        .otp-code {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 12px;
            color: #111827;
            margin: 0;
            margin-right: -12px;
            /* compensate for last letter spacing */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Budget Fund Utilization Tracking System</h1>
            <p>Merchant Marine Academy of Caraga, Inc.</p>
        </div>

        <div class="content">
            <div class="greeting">
                Hello <strong>{{ $userName }}</strong>,
            </div>

            <div class="message-text">
                Use the following verification code to complete your login. This code will expire in <strong>10
                    minutes</strong>.
            </div>

            <div class="otp-container">
                <div class="otp-code">{{ implode(' ', str_split($otp)) }}</div>
            </div>
        </div>
    </div>
</body>

</html>