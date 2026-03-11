<?php
// cron_backup_remoto.php
// Sistema de Backup Automatizado y Envío Remoto al Hub
// Se recomienda ejecutar esto vía CRON diariamente.

// --- 1. CONFIGURACIÓN ---
$api_url = 'https://troopsf.com/backupshub/api/receive.php';
$api_token = '78a7a8190faea397b4084cfcc40a2a73fa45e6bcd845d7261742676a8f413e61';

// Rutas base
$base_dir = __DIR__;
$backup_dir = $base_dir . '/backups';

// Asegurar que existe directorio de backups
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$timestamp = date('Ymd_His');
$zip_filename = "backup_banco_data_{$timestamp}.zip";
$zip_filepath = $backup_dir . '/' . $zip_filename;

echo "--- Inicio de Backup Remoto: " . date('Y-m-d H:i:s') . " ---\n";

// --- 2. GENERAR ZIP (Solo Datos y Configuración) ---
$zip = new ZipArchive();
if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Error CRITICO: No se pudo crear el archivo ZIP en $zip_filepath\n");
}

echo "1. Empaquetando archivos de datos...\n";

// A) Archivos específicos en raíz
$files_root = ['config.json', 'users/usuarios.json'];
foreach ($files_root as $file) {
    if (file_exists($base_dir . '/' . $file)) {
        $zip->addFile($base_dir . '/' . $file, $file);
    }
}

// B) Carpetas de datos (cuentas y logs)
$folders_data = ['cuentas', 'logs'];
foreach ($folders_data as $folder) {
    $path = $base_dir . '/' . $folder;
    if (is_dir($path)) {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            // Solo incluir JSON para evitar basura o archivos temporales
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $zip->addFile($path . '/' . $file, $folder . '/' . $file);
            }
        }
    }
}

$zip->close();

if (!file_exists($zip_filepath)) {
    die("Error: El archivo ZIP no se generó.\n");
}

echo "   > ZIP generado: $zip_filename (" . round(filesize($zip_filepath) / 1024, 2) . " KB)\n";

// --- 3. ENVIAR AL HUB ---
echo "2. Enviando al Hub de Backups ($api_url)...\n";

$cfile = new CURLFile($zip_filepath, 'application/zip', $zip_filename);
$post_data = ['backup_file' => $cfile];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $api_token"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ajustar según certificado del servidor remoto

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 200) {
    echo "   > ÉXITO: Backup enviado correctamente. Respuesta: $response\n";
    
    // --- 4. LIMPIEZA POST-ENVÍO ---
    // Como ya está en la nube, podemos borrar el zip local para no llenar el servidor
    unlink($zip_filepath);
    echo "   > Archivo local temporal eliminado.\n";
} else {
    echo "   > FALLO: El envío falló (Código $http_code). Error: $curl_error. Respuesta: $response\n";
}
echo "--- Fin del proceso ---\n";
?>