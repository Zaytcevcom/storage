<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->map(['OPTIONS'], '/method/{model}.{action}', function (Request $request, Response $response, $args) {
    $controller = new api\classes\Controller($request, $response, $args);
    return $controller->response('OK');
});

$app->map(['GET', 'POST'], '/method/{model}.{action}', function (Request $request, Response $response, $args) use ($config) {
    
    $version = (isset($args['v'])) ? $args['v'] : $config['version'];

    $methodName = ROOT_DIR . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'method' . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . $args['model'] . DIRECTORY_SEPARATOR . $args['action'] . '.php';

    $modelName = '\api\models\\' . ucfirst($args['model']) . 'Model';

    // Method not allowed
    if (!file_exists($methodName) || !class_exists($modelName)) {
        $controller = new api\classes\Controller($request, $response, $args);
        return $controller->methodNotAllowed();
    }

    $controller = new api\classes\Controller($request, $response, $args);
    $model      = new $modelName();
    
    return require $methodName;
});