<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Carta Responsiva</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h1 {
            color: #003366;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #003366;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #002244;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generar Carta Responsiva</h1>
        <form action="Plantilla_carta_responsiva.php" method="post" target="_blank">
            <div class="form-group">
                <label for="nombre_empleado">Nombre del Empleado:</label>
                <input type="text" id="nombre_empleado" name="nombre_empleado" required>
            </div>
            <div class="form-group">
                <label for="numero_empleado">Número de Empleado:</label>
                <input type="text" id="numero_empleado" name="numero_empleado" required>
            </div>
            <!-- Puedes agregar más campos según sea necesario -->
            <button type="submit">Generar PDF</button>
        </form>
    </div>
</body>
</html>