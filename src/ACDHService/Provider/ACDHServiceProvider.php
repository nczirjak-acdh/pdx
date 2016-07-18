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

class ACDHServiceProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    /**
   * Part of ServiceProviderInterface
   */
    public function register(Application $app)
    {
        
        //
        // Define controller services
        //
        //This is the base path for the application. Used to change the location
        //of yaml config files when registerd somewhere else
        if (!isset($app['islandora.BasePath'])) {
            $app['islandora.BasePath'] = __DIR__.'/..';
        }
    
        // If nobody registered a UuidGenerator first?
        if (!isset($app['UuidGenerator'])) {
            $app['UuidGenerator'] = $app->share(
                $app->share(
                    function () use ($app) {
                        return new UuidGenerator();
                    }
                )
            );
        }
        $app['islandora.acdhcontroller'] = $app->share(
            function () use ($app) {
                return new ACDHController($app, $app['UuidGenerator']);
            }
        );
        
        if (!isset($app['twig'])) {
            $app['twig'] = $app->share(
                $app->extend(
                    'twig',
                    function (
                        $twig,
                        $app
                    ) {
                        return $twig;
                    }
                )
            );
        } else {
            # Add our templates to the existing twig instance.
            $app['twig.loader']->addLoader(new \Twig_Loader_Filesystem(__DIR__ . '/../templates'));
        }
        if (!isset($app['api'])) {
            $app['api'] =  $app->share(
                function () use ($app) {
                    return FedoraApi::create(
                        $app['config']['islandora']['fedoraProtocol']
                        .'://'.$app['config']['islandora']['fedoraHost']
                        .$app['config']['islandora']['fedoraPath']
                    );
                }
            );
        }
        if (!isset($app['triplestore'])) {
            $app['triplestore'] = $app->share(
                function () use ($app) {
                    return TriplestoreClient::create(
                        $app['config']['islandora']['tripleProtocol']
                        .'://'.$app['config']['islandora']['tripleHost']
                        .$app['config']['islandora']['triplePath']
                    );
                }
            );
        }
        /**
    * Ultra simplistic YAML settings loader.
    */
        if (!isset($app['config'])) {
            $app['config'] = $app->share(
                function () use ($app) {
                    {
                    if ($app['debug']) {
                        $configFile = $app['islandora.BasePath'].'/../config/settings.dev.yml';
                    } else {
                        $configFile = $app['islandora.BasePath'].'/../config/settings.yml';
                    }
                    }
                    $settings = Yaml::parse(file_get_contents($configFile));
                    return $settings;
                }
            );
        }
    }

    public function boot(Application $app)
    {
    }

    /**
   * Part of ControllerProviderInterface
   */
    public function connect(Application $app)
    {
        $ACDHControllers = $app['controllers_factory'];
        //
        // Define routing referring to controller services
        //

        $ACDHControllers->post("/acdh/{id}", "islandora.acdhcontroller:create")
            ->value('id', "")
            ->bind('islandora.acdhCreate');
        $ACDHControllers->post("/acdh/{id}/member/{member}", "islandora.acdhcontroller:addMember")
            ->bind('islandora.acdhAddMember');
        $ACDHControllers->delete(
            "/acdh/{id}/member/{member}",
            "islandora.acdhcontroller:removeMember"
        )
            ->bind('islandora.acdhRemoveMember');
        return $ACDHControllers;
    }
}
