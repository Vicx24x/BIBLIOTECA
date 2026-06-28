<?php
/**
 * guardar_libro.php
 * Backend de procesamiento para el alta de libros del inventario.
 * Recibe el POST de inventario.php, valida/sanitiza, inserta en BD
 * y redirige de vuelta a inventario.php usando el patrón
 * Post/Redirect/Get con notificación vía parámetros de URL.
 */

session_start();
require_once 'config/db.php';

// --------------------------------------------------------------------
// 1) SEGURIDAD DE SESIÓN Y ACCESO (whitelist de roles)
// --------------------------------------------------------------------
$rolesPermitidos = ['Administrador', 'Bibliotecario'];
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
    header("Location: index.php");
    exit();
}

// Solo se acepta POST con la acción esperada
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['accion'] ?? '') !== 'agregar') {
    header("Location: inventario.php");
    exit();
}

// --------------------------------------------------------------------
// 2) PROTECCIÓN CSRF
// --------------------------------------------------------------------
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: inventario.php?error=1&msg=" . urlencode("Token de seguridad inválido. Recarga la página e intenta de nuevo."));
    exit();
}

/**
 * Redirige a inventario.php con un mensaje de error y termina la ejecución.
 */
function redirigirError(string $mensaje): void
{
    header("Location: inventario.php?error=1&msg=" . urlencode($mensaje));
    exit();
}

// --------------------------------------------------------------------
// 3) SANITIZACIÓN DE TEXTO
//    Se aplica trim + strip_tags al guardar (integridad de datos).
//    El escape para salida en HTML (htmlspecialchars) se hace en la
//    vista (inventario.php) al momento de imprimir, para no duplicar
//    el escape y evitar datos corruptos en la base de datos.
// --------------------------------------------------------------------
$isbn      = trim(strip_tags($_POST['isbn']      ?? ''));
$titulo    = trim(strip_tags($_POST['titulo']    ?? ''));
$autor     = trim(strip_tags($_POST['autor']     ?? ''));
$editorial = trim(strip_tags($_POST['editorial'] ?? ''));
$categoria = trim(strip_tags($_POST['categoria'] ?? ''));

// --------------------------------------------------------------------
// 4) VALIDACIÓN ESTRICTA DE CAMPOS DE TEXTO
// --------------------------------------------------------------------
if ($titulo === '' || mb_strlen($titulo) > 255) {
    redirigirError("El título es obligatorio y debe tener máximo 255 caracteres.");
}
if ($autor === '' || mb_strlen($autor) > 150) {
    redirigirError("El autor es obligatorio y debe tener máximo 150 caracteres.");
}
if ($editorial === '' || mb_strlen($editorial) > 150) {
    redirigirError("La editorial es obligatoria.");
}
if ($categoria === '' || mb_strlen($categoria) > 100) {
    redirigirError("La categoría es obligatoria.");
}

// --------------------------------------------------------------------
// 5) VALIDACIÓN DE ISBN (10 o 13 dígitos; admite guiones y X final)
// --------------------------------------------------------------------
$isbn_limpio = str_replace(['-', ' '], '', $isbn);
if (!preg_match('/^(?:\d{9}[\dXx]|\d{13})$/', $isbn_limpio)) {
    redirigirError("El ISBN no tiene un formato válido (10 o 13 dígitos).");
}
$isbn = strtoupper($isbn_limpio);

// --------------------------------------------------------------------
// 6) VALIDACIÓN NUMÉRICA CON filter_var
// --------------------------------------------------------------------
$anioActual = (int)date('Y');
$anio = filter_var($_POST['anio_publicacion'] ?? '', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1450, 'max_range' => $anioActual + 1],
]);
if ($anio === false) {
    redirigirError("El año de publicación no es válido (debe estar entre 1450 y " . ($anioActual + 1) . ").");
}

$cantidad_copias = filter_var($_POST['cantidad_copias'] ?? '', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 50],
]);
if ($cantidad_copias === false) {
    redirigirError("La cantidad de copias debe ser un número entero entre 1 y 50.");
}

// --------------------------------------------------------------------
// 7) MANEJO SEGURO DE LA PORTADA (extensión + MIME real + tamaño + contenido)
// --------------------------------------------------------------------
$portada_path = '';

if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
    $file_tmp  = $_FILES['portada']['tmp_name'];
    $file_size = $_FILES['portada']['size'];
    $file_ext  = strtolower(pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION));

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $mimesPermitidos       = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes              = 3 * 1024 * 1024; // 3 MB

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    if (!in_array($file_ext, $extensionesPermitidas, true)) {
        redirigirError("Formato de imagen no válido. Usa JPG, PNG o WEBP.");
    } elseif (!in_array($mimeReal, $mimesPermitidos, true)) {
        redirigirError("El contenido del archivo no corresponde a una imagen válida.");
    } elseif ($file_size > $maxBytes) {
        redirigirError("La imagen de portada no debe superar 3MB.");
    } elseif (@getimagesize($file_tmp) === false) {
        redirigirError("El archivo de portada está dañado o no es una imagen procesable.");
    } else {
        $nuevo_nombre = $isbn . '.' . $file_ext;
        $dirPortadas  = __DIR__ . '/portadas';
        if (!is_dir($dirPortadas)) {
            mkdir($dirPortadas, 0755, true);
        }
        $destino = $dirPortadas . '/' . $nuevo_nombre;
        if (move_uploaded_file($file_tmp, $destino)) {
            $portada_path = 'portadas/' . $nuevo_nombre; // ruta relativa para portabilidad
        } else {
            redirigirError("No se pudo guardar la imagen de portada en el servidor.");
        }
    }
} elseif (isset($_FILES['portada']) && $_FILES['portada']['error'] !== UPLOAD_ERR_NO_FILE) {
    redirigirError("Ocurrió un error al subir la imagen de portada.");
}

// --------------------------------------------------------------------
// 8) INSERCIÓN EN BASE DE DATOS (transacción + sentencias preparadas PDO)
// --------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO libros (isbn, titulo, autor, anio_publicacion, editorial, categoria, portada)
         VALUES (:isbn, :titulo, :autor, :anio, :editorial, :categoria, :portada)"
    );
    $stmt->execute([
        ':isbn'      => $isbn,
        ':titulo'    => $titulo,
        ':autor'     => $autor,
        ':anio'      => $anio,
        ':editorial' => $editorial,
        ':categoria' => $categoria,
        ':portada'   => $portada_path,
    ]);

    $id_libro_nuevo = $pdo->lastInsertId();

    $stmt_ej = $pdo->prepare(
        "INSERT INTO ejemplares (id_libro, codigo_activo, estado) VALUES (:id_libro, :codigo, 'Disponible')"
    );
    for ($i = 1; $i <= $cantidad_copias; $i++) {
        $stmt_ej->execute([
            ':id_libro' => $id_libro_nuevo,
            ':codigo'   => $isbn . '-' . sprintf('%03d', $i),
        ]);
    }

    $pdo->commit();

    // El token CSRF se invalida tras un uso exitoso
    unset($_SESSION['csrf_token']);

    header("Location: inventario.php?update=exito&msg=" . urlencode(
        "Libro registrado con $cantidad_copias ejemplar(es) listo(s) para préstamo."
    ));
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();

    // No se expone el mensaje crudo de la BD al usuario final (evita fuga de info interna)
    $mensaje = ($e->getCode() == 23000)
        ? "El ISBN ya existe en el sistema."
        : "Error de base de datos al registrar el libro. Contacta al administrador.";

    redirigirError($mensaje);
}
