<?php

declare(strict_types=1);

use Slim\App;
use Slim\Handlers\ErrorHandler;

return function (App $app): void {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $debug           = (bool) getenv('APP_DEBUG');
    $errorMiddleware = $app->addErrorMiddleware($debug, true, true);

    // ошибки API всегда отдаём в JSON
    $handler = $errorMiddleware->getDefaultErrorHandler();
    if ($handler instanceof ErrorHandler) {
        $handler->forceContentType('application/json');
    }
};
