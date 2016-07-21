<?php

namespace Islandora\PDX\ACDHService\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Islandora\Chullo\FedoraApi;
use Islandora\Chullo\TriplestoreClient;
use Islandora\Chullo\Uuid\UuidGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;
use Islandora\PDX\ACDHService\Controller\ACDHController;

class ACDHServiceProvider implements ServiceProviderInterface, ControllerProviderInterface {

    /**
     * Part of ServiceProviderInterface
     */
    public function register(Application $app) {

        //
        // Define controller services
        //
        $app['islandora.acdhcontroller'] = $app->share(function () use ($app) {
            return new ACDHController($app);
        });

        // This is the base path for the application. Used to change the location
        // of yaml config files when registerd somewhere else
        if (!isset($app['islandora.BasePath'])) {
            $app['islandora.BasePath'] = __DIR__ . '/..';
        }

        /**
         * Ultra simplistic YAML settings loader.
         */
        if (!isset($app['config'])) {
            $app['config'] = $app->share(function () use ($app) {
                if ($app['debug']) {
                    $configFile = $app['islandora.BasePath'] . '/../config/settings.dev.yml';
                } else {
                    $configFile = $app['islandora.BasePath'] . '/../config/settings.yml';
                }

                $settings = Yaml::parse(file_get_contents($configFile));
                return $settings;
            });
        }
    }

    public function boot(Application $app) {
        
    }

    /**
     * Part of ControllerProviderInterface
     */
    public function connect(Application $app) {
        $ACDHControllers = $app['controllers_factory'];
        //
        // Define routing referring to controller services
        //
        $ACDHControllers->post("/acdh/{id}", "islandora.acdhcontroller:post")
            ->value('id', "")
            ->bind('islandora.acdhCreate');
        return $ACDHControllers;
    }

}
