<?php

namespace Islandora\PDX;

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Application;
use Islandora\Chullo\Uuid\UuidGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Psr\Http\Message\ResponseInterface;
use Silex\Provider\TwigServiceProvider;
use Islandora\PDX\CollectionService\Provider\CollectionServiceProvider;
use Islandora\Crayfish\Provider\CrayfishProvider;

date_default_timezone_set('UTC');

$app = new Application();

$app['debug'] = true;
$app['islandora.BasePath'] = __DIR__;
$app->register(new \Silex\Provider\ServiceControllerServiceProvider());
// TODO: Not register all template directories right now.
$app->register(new \Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => array(
    __DIR__ . 'CollectionService/templates',
  ),
));

$islandoraCollectionServiceProvider = new CollectionServiceProvider;
$islandoraCrayfishProvider = new CrayfishProvider;

$app->register(
    $islandoraCollectionServiceProvider,
    array(
        'UuidGenerator' => new UuidGenerator(),
    )
);
$app->register($islandoraCrayfishProvider);

$app->mount("/islandora", $islandoraCollectionServiceProvider);
$app->mount("/islandora", $islandoraCrayfishProvider);

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
    function (\EasyRdf_Exception $e, $code) use ($app) {
        if ($app['debug']) {
            return;
        }
        return new response(sprintf('RDF Library exception', $e->getMessage(), $code), $code);
    }
);
$app->error(
    function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $code) use ($app) {
        if ($app['debug']) {
            return;
        }
        return new response(
            sprintf(
                'Islandora Collection Service exception: %s / HTTP %d response',
                $e->getMessage(),
                $code
            ),
            $code
        );
    }
);
$app->error(
    function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
        if ($app['debug']) {
            return;
        }
        //Not sure what the best "verbose" message is
        return new response(
            sprintf(
                'Islandora Collection Service exception: %s / HTTP %d response',
                $e->getMessage(),
                $code
            ),
            $code
        );
    }
);
$app->error(
    function (\Exception $e, $code) use ($app) {
        if ($app['debug']) {
            return;
        }
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
