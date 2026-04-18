<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$username = isset($_GET['username']) ? strtolower($_GET['username']) : '';
$moneda = isset($_GET['moneda']) ? $_GET['moneda'] : 'ars'; // Default ARS

// Cargar usuario
$usuarios = json_decode(file_get_contents('users/usuarios.json'), true)['users'];
$usuario_encontrado = null;
foreach ($usuarios as $user) {
    if (strtolower($user['username']) === $username) {
        $usuario_encontrado = $user;
        break;
    }
}

if (!$usuario_encontrado) exit("Usuario no encontrado.");

// Cargar movimientos según moneda
$archivo = 'cuentas/' . $username . ($moneda === 'usd' ? '_usd.json' : '.json');
$movimientos = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

// Configuración
$config = json_decode(file_get_contents('config.json'), true);
$interes_aplicado = ($moneda === 'usd') ? ($config['interes_mensual_usd'] ?? 0) : ($config['interes_mensual'] ?? 0);

// Cálculos
$rendimientos_totales = 0;
$total_en_cuenta = end($movimientos)['total_en_cuenta'] ?? 0;

foreach ($movimientos as $mov) {
    if ($mov['tipo'] === 'Rendimiento') $rendimientos_totales += $mov['monto'];
}

// --- LÓGICA DE AGRUPACIÓN ---
$agrupados = [];
$consecutivos = [];

foreach ($movimientos as $mov) {
    if ($mov['tipo'] === 'Rendimiento') {
        $consecutivos[] = $mov;
    } else {
        if (!empty($consecutivos)) {
            procesarConsecutivos($consecutivos, $agrupados);
            $consecutivos = [];
        }
        $agrupados[] = $mov;
    }
}
if (!empty($consecutivos)) {
    procesarConsecutivos($consecutivos, $agrupados);
}

function procesarConsecutivos($lista, &$destino) {
    $suma = array_sum(array_column($lista, 'monto'));
    $primero = $lista[0];
    $ultimo = end($lista);
    $dias = contarDiasUnicos($lista);
    
    $fecha_inicio = (new DateTime($primero['fecha']))->format('d/m');
    $fecha_fin = (new DateTime($ultimo['fecha']))->format('d/m');
    
    $concepto = ($dias > 1) 
        ? "Rendimientos ($dias días: $fecha_inicio al $fecha_fin)" 
        : "Rendimiento diario ($fecha_inicio)";

    $destino[] = [
        'fecha' => $ultimo['fecha'],
        'tipo' => 'Rendimientos',
        'monto' => $suma,
        'concepto' => $concepto,
        'total_en_cuenta' => $ultimo['total_en_cuenta']
    ];
}

function contarDiasUnicos($lista) {
    $fechas = [];

    foreach ($lista as $movimiento) {
        $fechas[(new DateTime($movimiento['fecha']))->format('Y-m-d')] = true;
    }

    return count($fechas);
}

$simbolo = ($moneda === 'usd') ? 'US$' : '$';
$clase_tema = ($moneda === 'usd') ? 'theme-usd' : 'theme-ars';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle - <?= htmlspecialchars($usuario_encontrado['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=32">
</head>
<body class="<?= $clase_tema ?>">
    <div class="container">
        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0;"><i class="fas fa-wallet"></i> <?= htmlspecialchars($usuario_encontrado['name']) ?></h1>
                <a href="admin.php" class="btn" style="background:#6c757d;">Volver</a>
            </div>

            <!-- Selector de Moneda -->
            <div class="tabs">
                <a href="?username=<?= $username ?>&moneda=ars" class="tab-btn <?= $moneda === 'ars' ? 'active-ars' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i> Pesos (ARS)
                </a>
                <a href="?username=<?= $username ?>&moneda=usd" class="tab-btn <?= $moneda === 'usd' ? 'active-usd' : '' ?>">
                    <i class="fas fa-dollar-sign"></i> Dólares (USD)
                </a>
            </div>

            <div class="card-grid">
                <div class="stat-card">
                    <h3>Total en Cuenta</h3>
                    <p><?= $simbolo . number_format($total_en_cuenta, 2) ?></p>
                </div>
                <!-- Capital Neto eliminado -->
                <div class="stat-card">
                    <h3>Rendimientos Totales</h3>
                    <p class="text-success">+<?= $simbolo . number_format($rendimientos_totales, 2) ?></p>
                </div>
                <div class="stat-card">
                    <h3>Tasa Mensual</h3>
                    <p><?= number_format($interes_aplicado * 100, 2) ?>%</p>
                </div>
            </div>

            <h2>Movimientos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($agrupados) as $mov): ?>
                        <tr>
                            <td><?= (new DateTime($mov['fecha']))->format('d/m/Y') ?></td>
                            <td>
                                <?php if($mov['tipo'] == 'Ingreso'): ?> <span class="text-success"><i class="fas fa-arrow-down"></i> Ingreso</span>
                                <?php elseif($mov['tipo'] == 'Egreso'): ?> <span class="text-danger"><i class="fas fa-arrow-up"></i> Egreso</span>
                                <?php else: ?> <span style="color:#fd7e14"><i class="fas fa-chart-line"></i> Rendimiento</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($mov['concepto']) ?></td>
                            <td style="font-weight:bold;">
                                <?php if ($mov['tipo'] === 'Egreso'): ?>
                                    -<?= $simbolo . number_format($mov['monto'], 2) ?>
                                <?php else: ?>
                                    <?= $simbolo . number_format($mov['monto'], 2) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $simbolo . number_format($mov['total_en_cuenta'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>