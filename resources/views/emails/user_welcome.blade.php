<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido al Sistema</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #1976d2;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f5f5f5;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .credentials {
            background-color: white;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #1976d2;
        }
        .credentials strong {
            color: #1976d2;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Bienvenido al Sistema Contratos Tecnotur!</h1>
    </div>
    <div class="content">
        <p>Hola <strong>{{ $user->name }}</strong>,</p>
        
        <p>Tu cuenta ha sido creada exitosamente en nuestro sistema. A continuación, encontrarás tus credenciales de acceso:</p>
        
        <div class="credentials">
            <p><strong>Usuario:</strong> {{ $user->username }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Contraseña temporal:</strong> {{ $password }}</p>
        </div>
        
        <div class="warning">
            <p><strong>⚠️ Importante:</strong></p>
            <ul>
                <li>Esta es una contraseña temporal generada automáticamente.</li>
                <li>Por seguridad, te recomendamos cambiarla en tu primer inicio de sesión.</li>
                <li>No compartas esta contraseña con nadie.</li>
            </ul>
        </div>
        
        <p>Si tienes alguna pregunta o problema para acceder al sistema, no dudes en contactar con el administrador.</p>
        
        <p>Saludos cordiales,<br>
        <strong>El equipo del sistema</strong></p>
    </div>
    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
    </div>
</body>
</html>
