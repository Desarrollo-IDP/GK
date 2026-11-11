<?php
// Redirigir a la página principal de login
header('Location: ../index.php');
exit();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Contraseña - Sistema de Tickets</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: url('logo.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 400px;
            width: 100%;
            margin: 2rem auto;
        }
        .glass-card h3 {
            font-weight: 700;
            color: #222;
            margin-bottom: 1.5rem;
        }
        .glass-card .form-label {
            color: #333;
            font-weight: 500;
        }
        .glass-card .btn-primary {
            background: linear-gradient(90deg, #4e54c8 0%, #8f94fb 100%);
            border: none;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .glass-card .btn-primary:hover {
            background: linear-gradient(90deg, #8f94fb 0%, #4e54c8 100%);
        }
        .glass-card .alert-danger {
            background: rgba(255, 0, 0, 0.1);
            color: #b71c1c;
            border: none;
        }
        .glass-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .glass-logo img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="glass-card shadow">
            <div class="glass-logo">
                <img src="https://cdn-icons-png.flaticon.com/512/3064/3064197.png" alt="Logo">
            </div>
            <h3 class="mb-3 text-center">Confirma tu contraseña</h3>
            <?php if ($error): ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100">Confirmar</button>
            </form>
        </div>
    </div>
</body>
</html>