<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Servicio temporalmente no disponible</title>
        <style>
            * { box-sizing: border-box; }
            body {
                align-items: center;
                background: #f1f5f9;
                color: #0f172a;
                display: flex;
                font-family: Arial, sans-serif;
                justify-content: center;
                margin: 0;
                min-height: 100vh;
                padding: 24px;
            }
            main {
                background: #ffffff;
                border: 1px solid #dbe4f0;
                border-radius: 14px;
                box-shadow: 0 12px 35px rgb(15 23 42 / 10%);
                max-width: 620px;
                padding: 36px;
                width: 100%;
            }
            h1 { font-size: 26px; margin: 0 0 14px; }
            p { color: #475569; line-height: 1.6; margin: 0 0 16px; }
            .reference {
                background: #eff6ff;
                border-radius: 8px;
                color: #1e3a8a;
                font-family: ui-monospace, Consolas, monospace;
                overflow-wrap: anywhere;
                padding: 12px;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>Servicio temporalmente no disponible</h1>
            <p>
                No fue posible consultar la base de datos. Intenta nuevamente
                en unos momentos. Si el problema continúa, entrega el siguiente
                identificador al administrador del sistema.
            </p>
            <div class="reference">Diagnóstico: {{ $diagnosticId }}</div>
        </main>
    </body>
</html>
