<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Received</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #2c3e50; margin-top: 0;">Payment Received</h1>
        <p>Thank you! We have received your payment.</p>
    </div>

    <div style="background-color: #fff; border: 1px solid #dee2e6; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
        <h2 style="color: #2c3e50; font-size: 18px; margin-top: 0;">Payment Details</h2>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Event:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">{{ $performance }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6;"><strong>Payment Amount:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #dee2e6; text-align: right;">${{ number_format($amount / 100, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Remaining Balance:</strong></td>
                <td style="padding: 8px 0; text-align: right;">${{ number_format($balance / 100, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; font-size: 14px; color: #6c757d;">
        <p style="margin: 0;">A receipt is attached to this email for your records.</p>
    </div>
</body>
</html>
