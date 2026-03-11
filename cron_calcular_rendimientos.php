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

        if (file_exists($archivo)) {
            $movimientos = json_decode(file_get_contents($archivo), true);

            // Validar que sea un array y tenga movimientos
            if (is_array($movimientos) && count($movimientos) > 0) {
                // Obtener el último saldo
                $ultimo_movimiento = end($movimientos);
                $saldo_actual = $ultimo_movimiento['total_en_cuenta'] ?? 0;

                // Calcular interés si hay saldo positivo
                if ($saldo_actual > 0) {
                    // Lógica: (Saldo * TasaMensual) / 30 días
                    $interes = ($saldo_actual * $tasa) / 30;
                    $nuevo_total = $saldo_actual + $interes;

                    // Crear movimiento
                    $nuevo_mov = [
                        'fecha' => date('Y-m-d H:i:s'),
                        'tipo' => 'Rendimiento',
                        'monto' => round($interes, 2),
                        'concepto' => 'Rendimiento diario',
                        'total_en_cuenta' => round($nuevo_total, 2)
                    ];

                    // Guardar
                    $movimientos[] = $nuevo_mov;
                    if (file_put_contents($archivo, json_encode($movimientos, JSON_PRETTY_PRINT))) {
                        echo "OK: {$moneda} para usuario {$username} (+ " . round($interes, 2) . ")\n";
                    } else {
                        echo "ERROR: No se pudo escribir en {$archivo}\n";
                    }
                }
            }
        }
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