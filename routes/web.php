<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Creator
$app->get('/creator', function (Request $request, Response $response, $args) {
    return $response->withHeader('Location', 'https://zaytcev.com')->withStatus(302);
});

// Index
$app->get('/', function (Request $request, Response $response, $args) {
    global $config;
    return $response->withHeader('Location', $config['redirect'])->withStatus(302);
});

// OpenApi
$app->get('/openapi[/{format}]', function (Request $request, Response $response, $args) use ($config) {
    
    $openapi = \OpenApi\scan(['api', 'config']);

    $controller = new \api\classes\Controller($request, $response, $args);

    if (isset($args['format'])) {
        // Yaml
        if ($args['format'] == 'yaml') {
            
            $response->getBody()
                ->write($openapi->toYaml());

            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Content-Type', 'application/x-yaml')
                ->withStatus(200);
        }
    }

    // JSON
    return $controller->response($openapi);
});