<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'cliente') {
    header('Location: index.php');
    exit;
}

// Cargar el usuario logueado
$nombre_usuario = $_SESSION['usuario'];  // Ejemplo: 'Fernando Sánchez'
$username = $_SESSION['username'];  // Ejemplo: 'fernando' (nombre para el archivo JSON)
$moneda = isset($_GET['moneda']) ? $_GET['moneda'] : 'ars'; // Default ARS

// Verificar si el archivo JSON del usuario existe
$suffix = ($moneda === 'usd') ? '_usd.json' : '.json';
$cuenta_json_path = 'cuentas/' . strtolower($username) . $suffix;

if (!file_exists($cuenta_json_path)) {
    // Si no existe, inicializamos vacío para no romper la web
    $movimientos = [];
} else {
    // Cargar los movimientos de la cuenta del usuario desde su archivo JSON (solo lectura)
    $movimientos = json_decode(file_get_contents($cuenta_json_path), true);
    // Solo lectura, no requiere flock ni validación de escritura
}

// Verificar si la lectura de los movimientos fue exitosa
if ($movimientos === null && file_exists($cuenta_json_path)) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error: No se pudo leer el archivo JSON de movimientos.</div>';
    exit;
}
$simbolo = ($moneda === 'usd') ? 'US$' : '$';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos los Movimientos - BANCO FSNET</title>
    <link rel="stylesheet" href="assets/css/style.css?=v14">
</head>
<body>
    <header class="site-header">
        <img src="logo.png" alt="Logo" class="logo">
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="todos_los_movimientos.php" class="active">Movimientos</a>
            <form action="logout.php" method="POST" style="display:inline; margin:0;">
                <button type="submit" style="background:var(--danger);margin-left:10px;"><i class="fas fa-sign-out-alt"></i> Salir</button>
            </form>
        </nav>
    </header>
    <div class="container">
        <div class="panel">
            <h1>Todos los Movimientos</h1>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo de movimiento</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                        <th>Total en cuenta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($movimientos) as $movimiento): ?>
                        <tr>
                            <td><?= htmlspecialchars($movimiento['fecha']) ?></td>
                            <td><?= htmlspecialchars($movimiento['tipo']) ?></td>
                            <td><?= htmlspecialchars($movimiento['concepto']) ?></td>
                            <td><?= $simbolo . number_format($movimiento['monto'], 2) ?></td>
                            <td><?= $simbolo . number_format($movimiento['total_en_cuenta'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Botón para volver al Dashboard -->
            <a href="dashboard.php" class="btn">Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>
