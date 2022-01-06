<?php

// Not found Handler
$notFoundHandler = function (
    Psr\Http\Message\ServerRequestInterface $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    return $response->withHeader('Location', '/')->withStatus(302);
};

$errorMiddleware->setErrorHandler(Slim\Exception\HttpNotFoundException::class, $notFoundHandler);