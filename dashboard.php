<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['role'] !== 'cliente') {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$moneda = isset($_GET['moneda']) ? $_GET['moneda'] : 'ars'; // Default ARS

// Cargar archivo según moneda
$archivo = 'cuentas/' . strtolower($username) . ($moneda === 'usd' ? '_usd.json' : '.json');
$movimientos = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

// Cálculos básicos
$capital = 0;
$rendimientos = 0;
$total_en_cuenta = end($movimientos)['total_en_cuenta'] ?? 0;
$rendimientos_ult_dia = 0;
$rendimientos_ult_30 = 0;

$fecha_ayer = new DateTime('-1 day');
$fecha_30 = new DateTime('-30 days');

// Datos para gráfico
$totales_grafico = [];
$labels_grafico = [];

foreach ($movimientos as $mov) {
    $fecha = new DateTime($mov['fecha']);
    
    if ($mov['tipo'] === 'Ingreso') $capital += $mov['monto'];
    if ($mov['tipo'] === 'Egreso') $capital -= $mov['monto'];
    if ($mov['tipo'] === 'Rendimiento') {
        $rendimientos += $mov['monto'];
        if ($fecha >= $fecha_ayer) $rendimientos_ult_dia += $mov['monto'];
        if ($fecha >= $fecha_30) $rendimientos_ult_30 += $mov['monto'];
    }
    
    // Datos gráfico (últimos 30 puntos para no saturar)
    $totales_grafico[] = $mov['total_en_cuenta'];
    $labels_grafico[] = $fecha->format('d/m');
}

// Recortar gráfico a últimos 20 movimientos significativos
if(count($totales_grafico) > 20) {
    $totales_grafico = array_slice($totales_grafico, -20);
    $labels_grafico = array_slice($labels_grafico, -20);
}

// Agrupación para tabla
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
    $dias = count($lista);
    $f_ini = (new DateTime($primero['fecha']))->format('d/m');
    $f_fin = (new DateTime($ultimo['fecha']))->format('d/m');
    $concepto = ($dias > 1) ? "Rendimientos ($dias días: $f_ini al $f_fin)" : "Rendimiento diario ($f_ini)";
    $destino[] = [
        'fecha' => $ultimo['fecha'],
        'tipo' => 'Rendimientos',
        'monto' => $suma,
        'concepto' => $concepto,
        'total_en_cuenta' => $ultimo['total_en_cuenta']
    ];
}

$simbolo = ($moneda === 'usd') ? 'US$' : '$';
$clase_tema = ($moneda === 'usd') ? 'theme-usd' : 'theme-ars';
$color_grafico = ($moneda === 'usd') ? '#28a745' : '#007bff';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - BANCO FSNET</title>
    <link rel="stylesheet" href="assets/css/style.css?v=31">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?= $clase_tema ?>">
    <form action="logout.php" method="POST" class="logout-btn">
        <button type="submit"><i class="fas fa-sign-out-alt"></i> Salir</button>
    </form>

    <div class="container">
        <img src="logo.png" alt="Logo" class="logo">
        <div class="panel">
            <h1>Hola, <?= htmlspecialchars($_SESSION['usuario']); ?></h1>

            <div class="tabs">
                <a href="?moneda=ars" class="tab-btn <?= $moneda === 'ars' ? 'active-ars' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i> Pesos
                </a>
                <a href="?moneda=usd" class="tab-btn <?= $moneda === 'usd' ? 'active-usd' : '' ?>">
                    <i class="fas fa-dollar-sign"></i> Dólares
                </a>
            </div>

            <div class="card-grid">
                <div class="stat-card">
                    <h3>Saldo Total</h3>
                    <p><?= $simbolo . number_format($total_en_cuenta, 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Ganancia (Últ. 30 días)</h3>
                    <p class="text-success">+<?= $simbolo . number_format($rendimientos_ult_30, 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Ganancia (Ayer)</h3>
                    <p class="text-success">+<?= $simbolo . number_format($rendimientos_ult_dia, 2); ?></p>
                </div>
            </div>

            <div class="chart-container" style="position: relative; height:300px; width:100%; margin-bottom: 30px;">
                <canvas id="accountChart"></canvas>
            </div>

            <h3>Últimos Movimientos</h3>
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
                    <?php foreach (array_slice(array_reverse($agrupados), 0, 10) as $mov): ?>
                        <tr>
                            <td><?= (new DateTime($mov['fecha']))->format('d/m/Y'); ?></td>
                            <td><?= htmlspecialchars($mov['tipo']); ?></td>
                            <td><?= htmlspecialchars($mov['concepto']); ?></td>
                            <td style="font-weight:bold; color: <?= $mov['tipo'] === 'Egreso' ? 'var(--danger)' : 'inherit' ?>">
                                <?= ($mov['tipo'] === 'Egreso' ? '-' : '') . $simbolo . number_format($mov['monto'], 2); ?>
                            </td>
                            <td><?= $simbolo . number_format($mov['total_en_cuenta'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('accountChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels_grafico) ?>,
                datasets: [{
                    label: 'Evolución de Saldo',
                    data: <?= json_encode($totales_grafico) ?>,
                    borderColor: '<?= $color_grafico ?>',
                    backgroundColor: '<?= $color_grafico ?>20',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: false } }
            }
        });
    </script>
</body>
</html>