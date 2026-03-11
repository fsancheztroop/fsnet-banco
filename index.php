<?php
session_start();

// Mostrar errores de PHP (para depuración)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar usuarios desde JSON
$usuarios = json_decode(file_get_contents('users/usuarios.json'), true)['users'];

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = strtolower(trim($_POST['usuario'])); // Convertir a minúsculas
    $password = $_POST['password'] ?? '';

    // Verificar si el usuario existe
    $usuario_encontrado = false;
    foreach ($usuarios as $user) {
        if (strtolower($user['username']) === $usuario && $user['password'] === $password) {
            $usuario_encontrado = true;

            // Iniciar sesión
            $_SESSION['usuario'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

			// Guardar el login en un log
			$log = [
				'usuario' => $usuario,
				'fecha' => date('Y-m-d H:i:s'),
				'ip' => $_SERVER['REMOTE_ADDR']
			];

			// Leer el contenido existente del archivo
			$log_data = [];
			$log_file = 'logs/log_login.json';
			if (file_exists($log_file)) {
				$log_data = json_decode(file_get_contents($log_file), true);
				if (!is_array($log_data)) {
					$log_data = [];
				}
			}

			// Agregar el nuevo log
			$log_data[] = $log;

			// Guardar el nuevo contenido en el archivo
			file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));

            // Redireccionar según el rol del usuario
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }

    if (!$usuario_encontrado) {
        $mensaje_error = 'Usuario o contraseña incorrectos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BANCO FSNET</title>
    <link rel="stylesheet" href="assets/css/style.css?=v24">
</head>
<body>
    <div class="login-container">
        <img src="logo.png" alt="Logo Banco FSNET" class="logo">
        <h1>Iniciar Sesión</h1>

        <?php if ($mensaje_error): ?>
            <p class="error"><?= $mensaje_error ?></p>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <label for="usuario">Usuario:</label>
            <input type="text" id="usuario" name="usuario" required>

            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
