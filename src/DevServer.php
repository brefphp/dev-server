<?php declare(strict_types=1);

namespace Bref\DevServer;

use Symfony\Component\Process\Process;

class DevServer
{
    public function run(): void
    {
        $handler = __DIR__ . '/server-handler.php';
        $assetsDirectory = getcwd();

        $server = new Process(['php', '-S', '0.0.0.0:8000', $handler, '-t', $assetsDirectory]);
        $server->setTimeout(null);
        $server->setTty(true);
        $server->setEnv([
            'PHP_CLI_SERVER_WORKERS' => 2,
            Handler::ASSETS_DIRECTORY_VARIABLE => $assetsDirectory,
        ]);

        $server->run();

        exit($server->getExitCode());
    }
}
