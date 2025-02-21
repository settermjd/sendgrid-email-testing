<?php

declare(strict_types=1);

use App\Handler\SendEmailHandler;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\ServiceManager;
use Asgrim\MiniMezzio\AppFactory;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouterInterface;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();
$dotenv->required([
    "SENDGRID_API_KEY",
]);

$aggregateConfig = (new ConfigAggregator([
    \Mezzio\ConfigProvider::class,
    \Mezzio\Router\ConfigProvider::class,
]))->getMergedConfig();
$dependencies = $aggregateConfig["dependencies"];
$dependencies['services']['config'] = $aggregateConfig;

$container = new ServiceManager($dependencies);
$container->addAbstractFactory(ReflectionBasedAbstractFactory::class);
$container->setFactory(
    RouterInterface::class,
    static function () {
        return new FastRouteRouter();
    }
);
$app = AppFactory::create($container, $container->get(RouterInterface::class));

$app->pipe(RouteMiddleware::class);
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(MethodNotAllowedMiddleware::class);
$app->pipe(DispatchMiddleware::class);
$app->post('/', SendEmailHandler::class);

$app->run();
