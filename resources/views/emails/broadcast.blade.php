<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Update from Hitee</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: #0056b3; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 20px; color: #333333; line-height: 1.6; }
        .footer { background: #eeeeee; padding: 15px; text-align: center; color: #777777; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Hitee Platform</h2>
        </div>
        <div class="content">
            {!! $bodyContent !!}
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Hitee Platform. All rights reserved.<br>
            <small>If you have any questions, contact our support team.</small>
        </div>
    </div>
</body>
</html>
