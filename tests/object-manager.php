<?php

declare(strict_types=1);

/*
 * Entity manager for PHPStan (phpstan.dist.neon, doctrine.objectManagerLoader), as in the example of
 * vendor/phpstan/phpstan-doctrine/README.md. Uses the dev kernel, like containerXmlPath.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
