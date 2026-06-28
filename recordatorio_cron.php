<?php
// =============================================================================
// recordatorio_cron.php  —  TAREA PROGRAMADA DE RECORDATORIOS PREVENTIVOS
// =============================================================================
// Este script NO es una página web. Se ejecuta como tarea programada (cron).
//
// CONFIGURAR EN AZURE (App Service → WebJobs o Azure Functions Timer):
//   Frecuencia : Una vez al día, de preferencia a las 8:00 AM
//   Comando    : php /home/site/wwwroot/recordatorio_cron.php
//
// CONFIGURAR EN XAMPP LOCAL (para pruebas):
//   En Linux/Mac: crontab -e → agregar:
//     0 8 * * * php /ruta/al/proyecto/recordatorio_cron.php
//   En Windows: Usar el Programador de Tareas de Windows.
//
// El script busca todos los préstamos activos que vencen exactamente en
// 1 o 2 días y envía un correo de recordatorio a cada usuario.
// =============================================================================

// Bloquear acceso vía HTTP (solo permite ejecución desde CLI)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Acceso denegado. Este script solo puede ejecutarse desde línea de comandos.');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/notificaciones.php';

$hoy         = new DateTime('today');
$en_1_dia    = (new DateTime('today'))->modify('+1 day')->format('Y-m-d');
$en_2_dias   = (new DateTime('today'))->modify('+2 days')->format('Y-m-d');

echo "[" . date('Y-m-d H:i:s') . "] Iniciando envío de recordatorios...\n";

try {
    // Buscar préstamos activos que vencen en exactamente 1 o 2 días
    $sql = "SELECT p.id_prestamo,
                   p.fecha_devolucion_esperada,
                   u.nombre  AS nombre_usuario,
                   u.correo  AS correo_usuario,
                   l.titulo  AS titulo_libro,
                   DATEDIFF(p.fecha_devolucion_esperada, CURRENT_DATE) AS dias_restantes
            FROM prestamos p
            INNER JOIN usuarios  u ON p.id_usuario  = u.id_usuario
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros    l ON e.id_libro     = l.id_libro
            WHERE p.estado = 'Activo'
              AND p.fecha_devolucion_esperada IN (:en_1_dia, :en_2_dias)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['en_1_dia' => $en_1_dia, 'en_2_dias' => $en_2_dias]);
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($prestamos)) {
        echo "No hay préstamos próximos a vencer hoy. Nada que enviar.\n";
        exit(0);
    }

    $enviados = 0;
    $fallidos = 0;

    foreach ($prestamos as $p) {
        $dias = (int)$p['dias_restantes'];

        $ok = notificar_recordatorio_vencimiento(
            correo_usuario:   $p['correo_usuario'],
            nombre_usuario:   $p['nombre_usuario'],
            titulo_libro:     $p['titulo_libro'],
            fecha_devolucion: $p['fecha_devolucion_esperada'],
            dias_restantes:   $dias
        );

        if ($ok) {
            $enviados++;
            echo "  ✅ Recordatorio enviado → {$p['correo_usuario']} ({$p['titulo_libro']}, {$dias} día/s)\n";
        } else {
            $fallidos++;
            echo "  ❌ Fallo al enviar → {$p['correo_usuario']}\n";
        }

        // Pausa mínima entre correos para respetar rate limit de SendGrid
        usleep(200000); // 0.2 segundos
    }

    echo "\n[Resumen] Enviados: {$enviados} | Fallidos: {$fallidos}\n";

} catch (PDOException $e) {
    error_log("[BiblioMPS][recordatorio_cron] Error BD: " . $e->getMessage());
    echo "Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
?>
