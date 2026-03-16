<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $emailSubject }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f5f7ff;
    color: #1a1a2e;
  }
  .wrapper {
    max-width: 600px;
    margin: 40px auto;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(79,110,247,0.10);
  }
  .header {
    background: {{ $primaryColor }};
    padding: 32px 40px;
    text-align: center;
  }
  .header h1 {
    color: white;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.3px;
  }
  .header p {
    color: rgba(255,255,255,0.8);
    font-size: 13px;
    margin-top: 4px;
  }
  .body {
    padding: 40px;
  }
  .greeting {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 16px;
  }
  .message-body {
    font-size: 15px;
    line-height: 1.7;
    color: #4a4a6a;
    white-space: pre-wrap;
    background: #f8f9ff;
    border-left: 4px solid {{ $primaryColor }};
    border-radius: 0 8px 8px 0;
    padding: 20px 24px;
    margin: 16px 0 28px;
  }
  .btn {
    display: inline-block;
    background: {{ $primaryColor }};
    color: white !important;
    text-decoration: none;
    padding: 12px 28px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    margin: 8px 0 24px;
  }
  .divider {
    border: none;
    border-top: 1px solid #f0f0f8;
    margin: 24px 0;
  }
  .footer {
    background: #f8f9ff;
    padding: 24px 40px;
    text-align: center;
  }
  .footer p {
    font-size: 12px;
    color: #9090b0;
    line-height: 1.6;
  }
  .footer strong {
    color: {{ $primaryColor }};
  }
  .badge {
    display: inline-block;
    background: #eef1ff;
    color: {{ $primaryColor }};
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 16px;
  }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>{{ $companyName }}</h1>
    <p>Human Resources Department</p>
  </div>

  <div class="body">
    <div class="badge">HR Communication</div>

    @if($recipientName)
    <div class="greeting">
      Dear {{ $recipientName }},
    </div>
    @endif

    <div class="message-body">
{{ $emailBody }}
    </div>

    <a href="{{ config('app.url') }}" class="btn">
      Open HR Portal ->
    </a>

    <hr class="divider">

    <p style="font-size:13px; color:#9090b0; line-height:1.6">
      This message was sent by
      <strong style="color:{{ $primaryColor }}">
        {{ $senderName }}
      </strong>
      via the {{ $companyName }} HR System.
      <br>
      Please do not reply directly to this email - log in to the HR portal to respond.
    </p>
  </div>

  <div class="footer">
    <p>
      <strong>{{ $companyName }}</strong>
      HR System
      <br>
      &copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.
      <br><br>
      You are receiving this because you are a registered employee or applicant in our HR system.
    </p>
  </div>
</div>
</body>
</html>
