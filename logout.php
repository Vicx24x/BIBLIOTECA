<?php
// cerrar_sesion.php
session_start();      // Inicia la sesión para poder acceder a ella
$_SESSION = array();  // Vacía el arreglo de variables de sesión
session_destroy();    // Destruye la sesión en el servidor

// Redirige al usuario a la página de login (index.php)
header("Location: index.php");
exit();
?>
