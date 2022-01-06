<?php

$app->add(new RKA\Middleware\IpAddress(true, ['10.0.0.1', '10.0.0.2']));