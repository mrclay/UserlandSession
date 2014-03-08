<?php
spl_autoload_register(function ($class) {
    if (0 !== strpos($class, 'UserlandSession\\')) {
        return;
    }

    $classPaths = array(
        __DIR__ . '/src',
        __DIR__ . '/tests',
    );

    $pieces = explode('\\', ltrim($class, '\\'));
    $pieces[count($pieces) - 1] = strtr($pieces[count($pieces) - 1], '_', '/');
    $relativePath = '/' . implode('/', $pieces) . '.php';
    foreach ($classPaths as $classPath) {
        if (is_readable($classPath . $relativePath)) {
            require $classPath . $relativePath;
            return;
        }
    }
});
