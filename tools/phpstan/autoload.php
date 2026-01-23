<?php

// filepath: tools/phpstan/autoload.php

spl_autoload_register(function ($class) {
    $prefix = 'CustomPHPStanRules\\';
    $baseDir = __DIR__ . '/rules/';

    // Verifica si la clase usa el namespace esperado
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // Obtiene el nombre relativo de la clase
    $relativeClass = substr($class, strlen($prefix));

    // Construye la ruta al archivo
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Incluye el archivo si existe
    if (file_exists($file)) {
        require $file;
    }
});
