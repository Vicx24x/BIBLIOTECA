<?php
// =============================================================================
// notificaciones.php  —  SERVICIO CENTRALIZADO DE CORREO ELECTRÓNICO
// =============================================================================
// Backend : PHPMailer + SendGrid SMTP (Azure compatible, 100 correos/día gratis)
// Uso     : require_once 'notificaciones.php'; y llamar a las funciones.
//
// CONFIGURACIÓN REQUERIDA (variables de entorno en Azure App Service):
//   SENDGRID_USER  → "apikey"  (literal, siempre este valor con SendGrid)
//   SENDGRID_PASS  → tu API Key de SendGrid (empieza con SG.)
//   MAIL_FROM      → correo remitente verificado en SendGrid
//   MAIL_FROM_NAME → nombre visible del remitente (ej. "Biblioteca UPIICSA")
//
// Para pruebas locales puedes poner los valores directamente abajo.
// =============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

// ── Configuración SMTP (lee de env o usa fallback local) ─────────────────────
define('MAIL_SMTP_HOST', 'smtp.sendgrid.net');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USER', getenv('SENDGRID_USER') ?: 'apikey');
define('MAIL_SMTP_PASS', getenv('SENDGRID_PASS') ?: 'TU_API_KEY_SENDGRID_AQUI');
define('MAIL_FROM',      getenv('MAIL_FROM')      ?: 'biblioteca@upiicsa.ipn.mx');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Biblioteca UPIICSA');

// ── Helper interno: crea y configura la instancia PHPMailer ──────────────────
function _mailer_base(): PHPMailer {
    $mail = new PHPMailer(true); // true = lanza excepciones
    $mail->isSMTP();
    $mail->Host       = MAIL_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_SMTP_USER;
    $mail->Password   = MAIL_SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    return $mail;
}

// ── Helper interno: plantilla HTML base ──────────────────────────────────────
function _html_template(string $titulo, string $cuerpo, string $color_borde = '#850021'): string {
    return <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f5f3ef;font-family:'Segoe UI',Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ef;padding:40px 0;">
        <tr><td align="center">
          <table width="600" cellpadding="0" cellspacing="0"
                 style="background:#ffffff;border-radius:12px;overflow:hidden;
                        box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <!-- Encabezado -->
            <tr>
              <td style="background:{$color_borde};padding:28px 36px;">
                <p style="margin:0;color:#ffffff;font-size:12px;letter-spacing:2px;
                           text-transform:uppercase;opacity:0.85;">
                  Instituto Politécnico Nacional · UPIICSA
                </p>
                <h1 style="margin:8px 0 0;color:#ffffff;font-size:22px;font-weight:700;">
                  {$titulo}
                </h1>
              </td>
            </tr>
            <!-- Cuerpo -->
            <tr>
              <td style="padding:36px;">
                {$cuerpo}
              </td>
            </tr>
            <!-- Pie -->
            <tr>
              <td style="background:#f8f8f8;padding:20px 36px;border-top:1px solid #eee;">
                <p style="margin:0;font-size:12px;color:#999;">
                  Este es un mensaje automático del Sistema BiblioMPS · UPIICSA IPN.<br>
                  Por favor no respondas a este correo.
                </p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

// =============================================================================
// FUNCIÓN 1 — CONFIRMACIÓN DE PRÉSTAMO EXITOSO
// Llamar desde: procesar_prestamo_v2.php tras el COMMIT
//
// @param string $correo_usuario   Correo del usuario
// @param string $nombre_usuario   Nombre completo
// @param string $titulo_libro     Título del libro prestado
// @param string $fecha_devolucion Fecha límite (Y-m-d)
// @return bool  true si el correo se envió, false si falló
// =============================================================================
function notificar_prestamo_exitoso(
    string $correo_usuario,
    string $nombre_usuario,
    string $titulo_libro,
    string $fecha_devolucion
): bool {
    try {
        $fecha_fmt = date('d/m/Y', strtotime($fecha_devolucion));
        $nombre_fmt = htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8');
        $titulo_fmt = htmlspecialchars($titulo_libro,   ENT_QUOTES, 'UTF-8');

        $cuerpo = <<<HTML
        <p style="font-size:16px;color:#1a1a2e;margin-top:0;">
          Hola, <strong>{$nombre_fmt}</strong>:
        </p>
        <p style="color:#444;line-height:1.7;">
          Tu préstamo ha sido registrado exitosamente en el sistema BiblioMPS.
        </p>
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0faf4;border-radius:8px;padding:20px;margin:20px 0;">
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📚 <strong>Libro:</strong> {$titulo_fmt}
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📅 <strong>Fecha límite de devolución:</strong>
              <span style="color:#850021;font-weight:700;">{$fecha_fmt}</span>
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              ⏱ <strong>Plazo:</strong> 7 días naturales
            </td>
          </tr>
        </table>
        <p style="color:#444;line-height:1.7;font-size:13px;">
          Recuerda que puedes solicitar <strong>una renovación</strong> antes de la fecha límite
          desde tu panel de usuario, siempre que el préstamo no esté vencido.
        </p>
        HTML;

        $mail = _mailer_base();
        $mail->addAddress($correo_usuario, $nombre_usuario);
        $mail->Subject = "✅ Préstamo registrado — {$titulo_libro}";
        $mail->isHTML(true);
        $mail->Body    = _html_template('Préstamo Registrado', $cuerpo, '#1a7a4a');
        $mail->AltBody = "Hola {$nombre_usuario}, tu préstamo del libro \"{$titulo_libro}\" fue registrado. Fecha límite: {$fecha_fmt}.";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[BiblioMPS][notificaciones] Préstamo exitoso: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNCIÓN 2 — AVISO DE DEVOLUCIÓN EXTEMPORÁNEA
// Llamar desde: procesar_devolucion_v2.php cuando $es_extemporanea === true
//
// @param string $correo_usuario
// @param string $nombre_usuario
// @param string $titulo_libro
// @param int    $dias_atraso       Días de retraso calculados
// @param string $fecha_limite      Fecha que se debió devolver (Y-m-d)
// =============================================================================
function notificar_devolucion_extemporanea(
    string $correo_usuario,
    string $nombre_usuario,
    string $titulo_libro,
    int    $dias_atraso,
    string $fecha_limite
): bool {
    try {
        $fecha_fmt  = date('d/m/Y', strtotime($fecha_limite));
        $hoy_fmt    = date('d/m/Y');
        $nombre_fmt = htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8');
        $titulo_fmt = htmlspecialchars($titulo_libro,   ENT_QUOTES, 'UTF-8');
        $plural     = $dias_atraso === 1 ? 'día' : 'días';

        $cuerpo = <<<HTML
        <p style="font-size:16px;color:#1a1a2e;margin-top:0;">
          Hola, <strong>{$nombre_fmt}</strong>:
        </p>
        <p style="color:#444;line-height:1.7;">
          Hemos registrado la devolución del libro que tenías en préstamo.
          Sin embargo, la entrega se realizó <strong>fuera del plazo establecido</strong>.
        </p>
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#fff4f4;border-left:4px solid #850021;
                      border-radius:4px;padding:20px;margin:20px 0;">
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📚 <strong>Libro devuelto:</strong> {$titulo_fmt}
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📅 <strong>Fecha límite original:</strong> {$fecha_fmt}
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📅 <strong>Fecha de entrega real:</strong> {$hoy_fmt}
            </td>
          </tr>
          <tr>
            <td style="padding:12px 0 6px;">
              <span style="background:#850021;color:#fff;padding:8px 18px;
                           border-radius:20px;font-size:14px;font-weight:700;">
                ⚠ Retraso: {$dias_atraso} {$plural}
              </span>
            </td>
          </tr>
        </table>
        <p style="color:#444;line-height:1.7;font-size:13px;">
          Este registro ha quedado en tu historial de morosidad dentro del sistema.
          Para evitar restricciones futuras, te recomendamos respetar las fechas
          de devolución o solicitar una renovación con anticipación.
        </p>
        HTML;

        $mail = _mailer_base();
        $mail->addAddress($correo_usuario, $nombre_usuario);
        $mail->Subject = "⚠ Devolución extemporánea registrada — {$dias_atraso} {$plural} de atraso";
        $mail->isHTML(true);
        $mail->Body    = _html_template('Devolución Extemporánea', $cuerpo, '#850021');
        $mail->AltBody = "Hola {$nombre_usuario}, la devolución del libro \"{$titulo_libro}\" se realizó con {$dias_atraso} {$plural} de atraso. Fecha límite era {$fecha_fmt}.";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[BiblioMPS][notificaciones] Extemporánea: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNCIÓN 3 — RECORDATORIO PREVENTIVO (1-2 DÍAS ANTES DEL VENCIMIENTO)
// Esta función NO se llama desde un controller HTTP, sino desde un script
// de cron/tarea programada: recordatorio_cron.php
//
// @param string $correo_usuario
// @param string $nombre_usuario
// @param string $titulo_libro
// @param string $fecha_devolucion   Fecha límite (Y-m-d)
// @param int    $dias_restantes     1 o 2
// =============================================================================
function notificar_recordatorio_vencimiento(
    string $correo_usuario,
    string $nombre_usuario,
    string $titulo_libro,
    string $fecha_devolucion,
    int    $dias_restantes
): bool {
    try {
        $fecha_fmt  = date('d/m/Y', strtotime($fecha_devolucion));
        $nombre_fmt = htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8');
        $titulo_fmt = htmlspecialchars($titulo_libro,   ENT_QUOTES, 'UTF-8');
        $urgencia   = $dias_restantes === 1 ? '¡Mañana es tu último día!' : 'Tienes 2 días para devolver';
        $color      = $dias_restantes === 1 ? '#c0392b' : '#e67e22';

        $cuerpo = <<<HTML
        <p style="font-size:16px;color:#1a1a2e;margin-top:0;">
          Hola, <strong>{$nombre_fmt}</strong>:
        </p>
        <div style="background:{$color};border-radius:8px;padding:16px 20px;margin:16px 0;">
          <p style="margin:0;color:#fff;font-size:15px;font-weight:700;">
            ⏰ {$urgencia}
          </p>
        </div>
        <p style="color:#444;line-height:1.7;">
          Te recordamos que tienes un libro pendiente de devolución en la Biblioteca UPIICSA.
        </p>
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#fefaf0;border-radius:8px;padding:20px;margin:20px 0;">
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📚 <strong>Libro:</strong> {$titulo_fmt}
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📅 <strong>Fecha límite:</strong>
              <span style="color:{$color};font-weight:700;">{$fecha_fmt}</span>
            </td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#555;padding:6px 0;">
              📌 <strong>Días restantes:</strong> {$dias_restantes}
            </td>
          </tr>
        </table>
        <p style="color:#444;line-height:1.7;font-size:13px;">
          Si no puedes devolver el libro a tiempo, ingresa a tu panel y solicita
          una <strong>renovación</strong> antes de que venza el plazo.
        </p>
        HTML;

        $mail = _mailer_base();
        $mail->addAddress($correo_usuario, $nombre_usuario);
        $mail->Subject = "⏰ Recordatorio — Tu préstamo vence en {$dias_restantes} día(s)";
        $mail->isHTML(true);
        $mail->Body    = _html_template('Recordatorio de Devolución', $cuerpo, $color);
        $mail->AltBody = "Hola {$nombre_usuario}, recuerda que el libro \"{$titulo_libro}\" debe devolverse el {$fecha_fmt}. Te quedan {$dias_restantes} día(s).";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[BiblioMPS][notificaciones] Recordatorio: " . $e->getMessage());
        return false;
    }
}
?>
