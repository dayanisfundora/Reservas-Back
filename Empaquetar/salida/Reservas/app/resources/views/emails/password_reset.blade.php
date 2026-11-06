<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contraseña de restauración</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f4f7f5;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #d7e0da;
        }
        .header {
            background-color: #06380f;
            color: #ffffff;
            padding: 22px 24px;
        }
        .content {
            padding: 28px 24px;
        }
        .credentials {
            background: #f4f7f5;
            border-left: 4px solid #06380f;
            padding: 14px 16px;
            margin: 18px 0;
        }
        .password {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.04em;
            color: #06380f;
            word-break: break-all;
        }
        .footer {
            margin-top: 18px;
            font-size: 12px;
            color: #6b7280;
        }
        .warning {
            background: #fff8e6;
            border-left: 4px solid #f2c037;
            padding: 10px 12px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin:0;font-size:20px;">Contraseña de restauración</h1>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $user->name }}</strong>,</p>
            <p>Recibimos una solicitud para recuperar el acceso a tu cuenta.</p>
            <p>Tu nueva contraseña temporal es:</p>

            <div class="credentials">
                <div><strong>Usuario:</strong> {{ $user->username }}</div>
                <div style="margin-top:8px;"><strong>Contraseña:</strong></div>
                <div class="password">{{ $temporaryPassword }}</div>
            </div>

            <p>Inicia sesión con esta contraseña y, por seguridad, cámbiala después desde tu perfil.</p>

            <div class="warning">
                Si no solicitaste esta restauración, contacta al administrador del sistema.
            </div>
            <div class="footer">
                Sofget S.U.R.L.
            </div>
        </div>
    </div>
</body>
</html>
