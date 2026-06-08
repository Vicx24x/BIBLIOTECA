<?php
// cerrar_sesion.php

// 1. Reanudar la sesión existente
session_start();

// 2. Vaciar todas las variables de sesión
$_SESSION = array();

// 3. Destruir la sesión completamente en el servidor
session_destroy();

// 4. Redirigir al usuario de vuelta al login (index.php)
header("Location: index.php");
exit();
?>
