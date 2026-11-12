<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Settled</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #2c3e50; margin-top: 0;">You're all settled</h1>
        <p>Good news: your balance for <strong>{{ $performance }}</strong> is settled, and nothing further is owed.</p>
    </div>

    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; font-size: 14px; color: #6c757d;">
        <p style="margin: 0;">Thank you{{ $bandName ? ' from all of us at ' . $bandName : '' }}!</p>
    </div>
</body>
</html>
