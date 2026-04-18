<?php
// cron_calcular_rendimientos.php
// Script para ejecutar vía CRON JOB o manualmente.
// Reemplaza a calcular_rendimientos.py

// Definir rutas base
$base_dir = __DIR__;
$users_file = $base_dir . '/users/usuarios.json';
$config_file = $base_dir . '/config.json';
$accounts_dir = $base_dir . '/cuentas/';

// 1. Cargar Configuración
if (!file_exists($config_file)) die("Error: No existe config.json");
$config = json_decode(file_get_contents($config_file), true);

$tasa_ars = $config['interes_mensual'] ?? 0.02;
$tasa_usd = $config['interes_mensual_usd'] ?? 0.01;

// 2. Cargar Usuarios
if (!file_exists($users_file)) die("Error: No existe usuarios.json");
$data_users = json_decode(file_get_contents($users_file), true);

echo "--- Inicio proceso de rendimientos: " . date('Y-m-d H:i:s') . " ---\n";

function yaTieneRendimientoHoy($movimientos) {
    $hoy = date('Y-m-d');

    for ($indice = count($movimientos) - 1; $indice >= 0; $indice--) {
        $movimiento = $movimientos[$indice];

        if (($movimiento['tipo'] ?? '') !== 'Rendimiento') {
            continue;
        }

        if (substr($movimiento['fecha'] ?? '', 0, 10) === $hoy) {
            return true;
        }
    }

    return false;
}

function cargarMovimientosBloqueados($handle) {
    rewind($handle);
    $contenido = stream_get_contents($handle);

    if ($contenido === false || trim($contenido) === '') {
        return [];
    }

    $movimientos = json_decode($contenido, true);

    return is_array($movimientos) ? $movimientos : null;
}

foreach ($data_users['users'] as $user) {
    $username = strtolower($user['username']);

    // --- Procesar ARS y USD ---
    // Definimos los dos tipos de archivos posibles
    $cuentas = [
        'ARS' => [
            'archivo' => $accounts_dir . $username . '.json',
            'tasa' => $tasa_ars
        ],
        'USD' => [
            'archivo' => $accounts_dir . $username . '_usd.json',
            'tasa' => $tasa_usd
        ]
    ];

    foreach ($cuentas as $moneda => $datos) {
        $archivo = $datos['archivo'];
        $tasa = $datos['tasa'];

        if (!file_exists($archivo)) {
            continue;
        }

        if ($tasa <= 0) {
            echo "SKIP: {$moneda} para usuario {$username} con tasa no positiva\n";
            continue;
        }

        $handle = fopen($archivo, 'c+');
        if ($handle === false) {
            echo "ERROR: No se pudo abrir {$archivo}\n";
            continue;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            echo "ERROR: No se pudo bloquear {$archivo}\n";
            continue;
        }

        $movimientos = cargarMovimientosBloqueados($handle);
        if (!is_array($movimientos) || count($movimientos) === 0) {
            flock($handle, LOCK_UN);
            fclose($handle);
            echo "SKIP: {$moneda} para usuario {$username} sin movimientos validos\n";
            continue;
        }

        if (yaTieneRendimientoHoy($movimientos)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            echo "SKIP: {$moneda} para usuario {$username} ya tiene rendimiento hoy\n";
            continue;
        }

        $ultimo_movimiento = end($movimientos);
        $saldo_actual = $ultimo_movimiento['total_en_cuenta'] ?? 0;

        if ($saldo_actual <= 0) {
            flock($handle, LOCK_UN);
            fclose($handle);
            continue;
        }

        $interes = round(($saldo_actual * $tasa) / 30, 2);
        if ($interes <= 0) {
            flock($handle, LOCK_UN);
            fclose($handle);
            echo "SKIP: {$moneda} para usuario {$username} con rendimiento no positivo\n";
            continue;
        }

        $nuevo_mov = [
            'fecha' => date('Y-m-d H:i:s'),
            'tipo' => 'Rendimiento',
            'monto' => $interes,
            'concepto' => 'Rendimiento diario',
            'total_en_cuenta' => round($saldo_actual + $interes, 2)
        ];

        $movimientos[] = $nuevo_mov;
        $json = json_encode($movimientos, JSON_PRETTY_PRINT);

        if ($json === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            echo "ERROR: No se pudo serializar {$archivo}\n";
            continue;
        }

        rewind($handle);
        ftruncate($handle, 0);

        if (fwrite($handle, $json) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            echo "ERROR: No se pudo escribir en {$archivo}\n";
            continue;
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        echo "OK: {$moneda} para usuario {$username} (+ {$interes})\n";
    }
}

// 3. Ejecutar Backup Remoto (Encadenado)
// Esto ejecutará el script de backup inmediatamente después de calcular los intereses
if (file_exists($base_dir . '/cron_backup_remoto.php')) {
    echo "\n>>> Iniciando cadena de Backup Remoto...\n";
    include $base_dir . '/cron_backup_remoto.php';
}

echo "--- Fin del proceso ---\n";
?>