<?php
// logout.php
session_start();      // Retoma la sesión actual
session_unset();      // Libera todas las variables de sesión
session_destroy();    // Destruye la sesión por completo

// Redirige al Login limpio
header("Location: index.php");
exit();
?>