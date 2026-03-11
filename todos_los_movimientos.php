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

// Verificar si el archivo JSON del usuario existe
$cuenta_json_path = 'cuentas/' . strtolower($username) . '.json';
if (!file_exists($cuenta_json_path)) {
    echo "Error: No se encontró el archivo de movimientos para el usuario.";
    exit;
}

// Cargar los movimientos de la cuenta del usuario desde su archivo JSON
$movimientos = json_decode(file_get_contents($cuenta_json_path), true);

// Verificar si la lectura de los movimientos fue exitosa
if ($movimientos === null) {
    echo "Error: No se pudo leer el archivo JSON de movimientos.";
    exit;
}
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
                    <?php foreach ($movimientos as $movimiento): ?>
                        <tr>
                            <td><?= htmlspecialchars($movimiento['fecha']) ?></td>
                            <td><?= htmlspecialchars($movimiento['tipo']) ?></td>
                            <td><?= htmlspecialchars($movimiento['concepto']) ?></td>
                            <td>$<?= number_format($movimiento['monto'], 2) ?></td>
                            <td>$<?= number_format($movimiento['total_en_cuenta'], 2) ?></td>
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
