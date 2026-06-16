<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm account deletion — TTS Bandmate</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f5f5f7;
            color: #1d1d1f;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            max-width: 480px;
            margin: 24px;
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { font-size: 16px; line-height: 1.5; color: #515154; margin: 0 0 24px; }
        button {
            background: #ff3b30;
            color: #fff;
            border: 0;
            border-radius: 12px;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #e0352b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Delete your account?</h1>
        <p>
            This permanently deletes your TTS Bandmate account and removes you
            from your bands. This action cannot be undone.
        </p>
        <form method="POST" action="{{ $actionUrl }}">
            @csrf
            <button type="submit">Permanently delete my account</button>
        </form>
    </div>
</body>
</html>
