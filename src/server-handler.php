<?php declare(strict_types=1);

use Bref\DevServer\Handler;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../../../autoload.php';
}

return (new Handler)->handleRequest();
