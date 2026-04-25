<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$usuarios = json_decode(file_get_contents('users/usuarios.json'), true)['users'];
$config_path = 'config.json';
$config = json_decode(file_get_contents($config_path), true);
$interes_mensual_ars = $config['interes_mensual'] ?? 0.02;
$interes_mensual_usd = $config['interes_mensual_usd'] ?? 0.01;

$mensaje_exito = '';
$mensaje_error = '';

// --- PROCESAR ACCIONES ---

// 1. Actualizar Configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_config') {
    $config['interes_mensual'] = floatval($_POST['interes_ars']) / 100;
    $config['interes_mensual_usd'] = floatval($_POST['interes_usd']) / 100;
    $json = json_encode($config, JSON_PRETTY_PRINT);
    if ($json === false) {
        $mensaje_error = "Error al serializar la configuración.";
    } else {
        $fp = fopen($config_path, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $ok = fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            if ($ok === false) {
                $mensaje_error = "Error al guardar la configuración.";
            } else {
                $interes_mensual_ars = $config['interes_mensual'];
                $interes_mensual_usd = $config['interes_mensual_usd'];
                $mensaje_exito = "Tasas de interés actualizadas correctamente.";
            }
        } else {
            $mensaje_error = "No se pudo bloquear el archivo de configuración.";
        }
    }
}

// 2. Nuevo Movimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_movement') {
    $usuario_target = $_POST['usuario'];
    $moneda = $_POST['moneda'];
    $tipo = $_POST['tipo'];
    $monto = floatval($_POST['monto']);
    $concepto = $_POST['concepto'];

    $archivo = 'cuentas/' . strtolower($usuario_target) . ($moneda === 'usd' ? '_usd.json' : '.json');
    $movimientos = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    $total_actual = end($movimientos)['total_en_cuenta'] ?? 0;
    $nuevo_total = ($tipo === 'Ingreso') ? $total_actual + $monto : $total_actual - $monto;
    $movimientos[] = [
        'fecha' => date('Y-m-d H:i:s'),
        'tipo' => $tipo,
        'monto' => $monto,
        'concepto' => htmlspecialchars($concepto),
        'total_en_cuenta' => $nuevo_total
    ];
    $json = json_encode($movimientos, JSON_PRETTY_PRINT);
    if ($json === false) {
        $mensaje_error = "Error al serializar los movimientos.";
    } else {
        $fp = fopen($archivo, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            $ok = fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            if ($ok === false) {
                $mensaje_error = "Error al guardar el movimiento.";
            } else {
                $mensaje_exito = "Movimiento registrado en la cuenta " . strtoupper($moneda) . " de $usuario_target.";
            }
        } else {
            $mensaje_error = "No se pudo bloquear el archivo de cuenta.";
        }
    }
}

// 3. Cambio de Divisas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'exchange') {
    $usuario_target = $_POST['usuario'];
    $origen = $_POST['moneda_origen'];
    $monto_salida = floatval($_POST['monto_salida']);
    $monto_entrada = floatval($_POST['monto_entrada']);
    
    $destino = ($origen === 'ars') ? 'usd' : 'ars';
    
    $file_origen = 'cuentas/' . strtolower($usuario_target) . ($origen === 'usd' ? '_usd.json' : '.json');
    $file_destino = 'cuentas/' . strtolower($usuario_target) . ($destino === 'usd' ? '_usd.json' : '.json');
    
    $movs_origen = file_exists($file_origen) ? json_decode(file_get_contents($file_origen), true) : [];
    $movs_destino = file_exists($file_destino) ? json_decode(file_get_contents($file_destino), true) : [];
    $saldo_origen = end($movs_origen)['total_en_cuenta'] ?? 0;
    if ($saldo_origen < $monto_salida) {
        $mensaje_error = "Error: Saldo insuficiente en la cuenta de origen (" . strtoupper($origen) . ").";
    } else {
        // Calcular tasa implícita para mostrar en concepto
        if ($origen === 'ars') {
            $tasa = ($monto_entrada > 0) ? $monto_salida / $monto_entrada : 0;
            $txt_tasa = "1 USD = " . number_format($tasa, 2) . " ARS";
        } else {
            $tasa = ($monto_salida > 0) ? $monto_entrada / $monto_salida : 0;
            $txt_tasa = "1 USD = " . number_format($tasa, 2) . " ARS";
        }
        // 1. Restar de Origen
        $movs_origen[] = [
            'fecha' => date('Y-m-d H:i:s'),
            'tipo' => 'Egreso',
            'monto' => $monto_salida,
            'concepto' => "Cambio a " . strtoupper($destino) . " ($txt_tasa)",
            'total_en_cuenta' => $saldo_origen - $monto_salida
        ];
        // 2. Sumar a Destino
        $saldo_destino = end($movs_destino)['total_en_cuenta'] ?? 0;
        $movs_destino[] = [
            'fecha' => date('Y-m-d H:i:s'),
            'tipo' => 'Ingreso',
            'monto' => $monto_entrada,
            'concepto' => "Cambio desde " . strtoupper($origen) . " ($txt_tasa)",
            'total_en_cuenta' => $saldo_destino + $monto_entrada
        ];
        $json_origen = json_encode($movs_origen, JSON_PRETTY_PRINT);
        $json_destino = json_encode($movs_destino, JSON_PRETTY_PRINT);
        if ($json_origen === false || $json_destino === false) {
            $mensaje_error = "Error al serializar los movimientos de cambio.";
        } else {
            $fp1 = fopen($file_origen, 'c+');
            $fp2 = fopen($file_destino, 'c+');
            $ok1 = $ok2 = false;
            if ($fp1 && flock($fp1, LOCK_EX)) {
                ftruncate($fp1, 0);
                rewind($fp1);
                $ok1 = fwrite($fp1, $json_origen);
                fflush($fp1);
                flock($fp1, LOCK_UN);
                fclose($fp1);
            }
            if ($fp2 && flock($fp2, LOCK_EX)) {
                ftruncate($fp2, 0);
                rewind($fp2);
                $ok2 = fwrite($fp2, $json_destino);
                fflush($fp2);
                flock($fp2, LOCK_UN);
                fclose($fp2);
            }
            if ($ok1 === false || $ok2 === false) {
                $mensaje_error = "Error al guardar los movimientos de cambio.";
            } else {
                $mensaje_exito = "Cambio de divisas realizado exitosamente.";
            }
        }
    }
}

// --- OBTENER DATOS PARA TABLA Y JS ---
$informacion_usuarios = [];
$saldos_js = []; // Array para pasar a JS

foreach ($usuarios as $user) {
    // ARS
    $path_ars = 'cuentas/' . strtolower($user['username']) . '.json';
    $movs_ars = file_exists($path_ars) ? json_decode(file_get_contents($path_ars), true) : [];
    $total_ars = end($movs_ars)['total_en_cuenta'] ?? 0;

    // USD
    $path_usd = 'cuentas/' . strtolower($user['username']) . '_usd.json';
    $movs_usd = file_exists($path_usd) ? json_decode(file_get_contents($path_usd), true) : [];
    $total_usd = end($movs_usd)['total_en_cuenta'] ?? 0;

    $informacion_usuarios[] = [
        'nombre' => $user['name'],
        'username' => $user['username'],
        'total_ars' => $total_ars,
        'total_usd' => $total_usd
    ];

    // Guardar para JS
    $saldos_js[$user['username']] = [
        'ars' => $total_ars,
        'usd' => $total_usd
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - BANCO FSNET</title>
    <link rel="stylesheet" href="assets/css/style.css?v=32">
</head>

<body>
    <header class="site-header">
        <img src="logo.png" alt="Logo" class="logo">
        <nav>
            <a href="admin.php" class="active">Panel</a>
            <?php // Solo mostrar Dashboard y Movimientos si NO es admin (por seguridad redundante, aunque este archivo es solo para admin) ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="todos_los_movimientos.php">Movimientos</a>
            <?php endif; ?>
            <form action="logout.php" method="POST" style="display:inline; margin:0;">
                <button type="submit" style="background:var(--danger);margin-left:10px;"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
            </form>
        </nav>
    </header>

    <div class="container">
        
        <div class="panel">
            <h1><i class="fas fa-user-shield"></i> Panel de Administrador</h1>
            
            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($mensaje_exito) ?>
                </div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($mensaje_error) ?>
                </div>
            <?php endif; ?>

            <!-- Configuración -->
            <div class="flex-row">
                <div class="flex-col">
                    <h3><i class="fas fa-cog"></i> Configuración de Intereses</h3>
                    <form method="POST" action="admin.php" style="background: #f9f9f9; padding: 20px; border-radius: 15px;">
                        <input type="hidden" name="action" value="update_config">
                        <div class="flex-row">
                            <div class="flex-col">
                                <label>Interés Mensual ARS (%):</label>
                                <input type="number" step="0.01" name="interes_ars" value="<?= $interes_mensual_ars * 100 ?>" required>
                            </div>
                            <div class="flex-col">
                                <label>Interés Mensual USD (%):</label>
                                <input type="number" step="0.01" name="interes_usd" value="<?= $interes_mensual_usd * 100 ?>" required>
                            </div>
                        </div>
                        <button type="submit">Actualizar Tasas</button>
                    </form>
                </div>
            </div>

            <hr style="margin: 40px 0; border: 0; border-top: 1px solid #eee;">

            <!-- Operaciones -->
            <div class="flex-row">
                <!-- Nuevo Movimiento -->
                <div class="flex-col">
                    <h3><i class="fas fa-plus-circle"></i> Nuevo Movimiento</h3>
                    <form method="POST" action="admin.php">
                        <input type="hidden" name="action" value="new_movement">
                        <label>Usuario:</label>
                        <select name="usuario" required>
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?= $user['username'] ?>"><?= $user['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="flex-row">
                            <div class="flex-col">
                                <label>Moneda:</label>
                                <select name="moneda">
                                    <option value="ars">Pesos (ARS)</option>
                                    <option value="usd">Dólares (USD)</option>
                                </select>
                            </div>
                            <div class="flex-col">
                                <label>Tipo:</label>
                                <select name="tipo">
                                    <option value="Ingreso">Ingreso</option>
                                    <option value="Egreso">Egreso</option>
                                </select>
                            </div>
                        </div>
                        <label>Monto:</label>
                        <input type="number" step="0.01" name="monto" required>
                        <label>Concepto:</label>
                        <input type="text" name="concepto" required>
                        <button type="submit">Crear Movimiento</button>
                    </form>
                </div>

                <!-- Cambio de Divisas -->
                <div class="flex-col" style="border-left: 1px solid #eee; padding-left: 20px;">
                    <h3><i class="fas fa-exchange-alt"></i> Cambio de Divisas</h3>
                    <form method="POST" action="admin.php" id="form-exchange">
                        <input type="hidden" name="action" value="exchange">
                        
                        <label>Usuario:</label>
                        <select name="usuario" id="ex-usuario" required onchange="actualizarSaldo()">
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?= $user['username'] ?>"><?= $user['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Moneda que SALE (Origen):</label>
                        <select name="moneda_origen" id="ex-moneda" required onchange="actualizarSaldo()">
                            <option value="ars">Pesos (ARS)</option>
                            <option value="usd">Dólares (USD)</option>
                        </select>
                        
                        <div class="flex-row">
                            <div class="flex-col">
                                <label>Monto que SALE:</label>
                                <input type="number" step="0.01" name="monto_salida" id="ex-monto-salida" required oninput="calcularEntrada()">
                                <small id="saldo-disponible" style="color: #666; font-size: 0.85em; display:block; margin-top:-15px; margin-bottom:10px;">Cargando...</small>
                            </div>
                        </div>

                        <!-- Opción de cálculo -->
                        <div style="margin-bottom: 15px; background: #e9ecef; padding: 10px; border-radius: 8px;">
                            <label style="display:flex; align-items:center; cursor:pointer; margin:0;">
                                <input type="checkbox" id="check-tasa" onchange="toggleModoCalculo()" style="width:auto; margin-right:10px; margin-bottom:0;">
                                Calcular usando Tipo de Cambio
                            </label>
                        </div>

                        <div class="flex-row">
                            <!-- Input Tipo de Cambio (Oculto por defecto) -->
                            <div class="flex-col" id="div-tasa" style="display:none;">
                                <label>Tipo de Cambio (1 USD = ? ARS):</label>
                                <input type="number" step="0.01" id="ex-tasa" placeholder="Ej: 1200" oninput="calcularEntrada()">
                            </div>

                            <div class="flex-col">
                                <label>Monto que ENTRA:</label>
                                <input type="number" step="0.01" name="monto_entrada" id="ex-monto-entrada" required>
                            </div>
                        </div>
                        
                        <button type="submit" style="background-color: #6f42c1;">Ejecutar Cambio</button>
                    </form>
                </div>
            </div>

            <hr style="margin: 40px 0; border: 0; border-top: 1px solid #eee;">

            <!-- Tabla Resumen -->
            <h3><i class="fas fa-users"></i> Estado de Cuentas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Total ARS</th>
                        <th>Total USD</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($informacion_usuarios as $info): ?>
                        <tr>
                            <td><?= htmlspecialchars($info['nombre']) ?></td>
                            <td style="color: var(--ars-dark); font-weight: bold;">$<?= number_format($info['total_ars'], 2) ?></td>
                            <td style="color: var(--usd-dark); font-weight: bold;">US$<?= number_format($info['total_usd'], 2) ?></td>
                            <td>
                                <a href="ver_usuario.php?username=<?= urlencode($info['username']) ?>" class="btn" style="padding: 8px 15px; font-size: 12px;">Ver Detalles</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Script para lógica de Cambio de Divisas -->
    <script>
        // Datos de saldos pasados desde PHP
        const saldos = <?= json_encode($saldos_js); ?>;

        function actualizarSaldo() {
            const usuario = document.getElementById('ex-usuario').value;
            const moneda = document.getElementById('ex-moneda').value;
            const inputSalida = document.getElementById('ex-monto-salida');
            const labelSaldo = document.getElementById('saldo-disponible');

            if (saldos[usuario]) {
                const saldoActual = saldos[usuario][moneda];
                // Precargar el monto de salida con el total
                inputSalida.value = saldoActual;
                labelSaldo.textContent = `Total actual disponible: ${moneda.toUpperCase() === 'ARS' ? '$' : 'US$'}${saldoActual.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                
                // Recalcular si hay datos
                calcularEntrada();
            }
        }

        function toggleModoCalculo() {
            const usarTasa = document.getElementById('check-tasa').checked;
            const divTasa = document.getElementById('div-tasa');
            const inputEntrada = document.getElementById('ex-monto-entrada');

            if (usarTasa) {
                divTasa.style.display = 'block';
                inputEntrada.readOnly = true; // El usuario no escribe el total, se calcula
                inputEntrada.style.backgroundColor = '#e9ecef';
            } else {
                divTasa.style.display = 'none';
                inputEntrada.readOnly = false;
                inputEntrada.style.backgroundColor = '#fff';
            }
            calcularEntrada();
        }

        function calcularEntrada() {
            const usarTasa = document.getElementById('check-tasa').checked;
            if (!usarTasa) return; // Si es manual, no hacemos nada

            const montoSalida = parseFloat(document.getElementById('ex-monto-salida').value) || 0;
            const tasa = parseFloat(document.getElementById('ex-tasa').value) || 0;
            const monedaOrigen = document.getElementById('ex-moneda').value;
            const inputEntrada = document.getElementById('ex-monto-entrada');

            if (tasa > 0) {
                let resultado = 0;
                if (monedaOrigen === 'ars') {
                    // Salgo de Pesos a Dólares (Divido por tasa)
                    // Ej: 1200 ARS / 1200 Tasa = 1 USD
                    resultado = montoSalida / tasa;
                } else {
                    // Salgo de Dólares a Pesos (Multiplico por tasa)
                    // Ej: 1 USD * 1200 Tasa = 1200 ARS
                    resultado = montoSalida * tasa;
                }
                inputEntrada.value = resultado.toFixed(2);
            } else {
                inputEntrada.value = '';
            }
        }

        // Inicializar al cargar
        window.onload = function() {
            actualizarSaldo();
        };
    </script>
</body>
</html>