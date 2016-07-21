<?php

namespace Islandora\PDX;

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Http\Message\ResponseInterface;
use Islandora\PDX\CollectionService\Provider\CollectionServiceProvider;
use Islandora\Crayfish\Provider\CrayfishProvider;
use Islandora\PDX\ACDHService\Provider\ACDHServiceProvider;

date_default_timezone_set('UTC');

$app = new Application();

$app['debug'] = true;
$app['islandora.BasePath'] = __DIR__;
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
// TODO: Not register all template directories right now.
$app->register(new \Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => array(
    __DIR__ . '/CollectionService/templates',
  ),
));
// Cache for TransactionService
$app->register(new \Moust\Silex\Provider\CacheServiceProvider(), array(
    'caches.options' => array(
        'filesystem' => array(
            'driver' => 'file',
            'cache_dir' => '/tmp',
        ),
    ),
));


$islandoraCollectionServiceProvider = new CollectionServiceProvider;
$islandoraCrayfishProvider = new CrayfishProvider;
$ACDHServiceProvider = new ACDHServiceProvider;

$app->register($islandoraCrayfishProvider);
$app->register($ACDHServiceProvider);

$app->mount("/islandora", $islandoraCrayfishProvider);
$app->mount("/islandora", $ACDHServiceProvider);

/**
 * Convert returned Guzzle responses to Symfony responses, type hinted.
 */
$app->after(
    function (Request $request, Response $response) use ($app) {
        $response->headers->set('X-Powered-By', 'Islandora Collection REST API v'
        .$app['config']['islandora']['apiVersion'], true); //Nice
    }
);

$app->view(function (ResponseInterface $psr7) {
    return new Response($psr7->getBody(), $psr7->getStatusCode(), $psr7->getHeaders());
});

//Common error Handling
$app->error(
    function (\Exception $e, $code) use ($app) {
        return new response(
            sprintf(
                'Islandora Collection Service uncatched exception: %s %d response',
                $e->getMessage(),
                $code
            ),
            $code
        );
    }
);

$app->run();
