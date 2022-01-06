<?php

$container = $app->getContainer();

// Service factory for the ORM
$container->set('db', function () {

    $db = (require ROOT_DIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'core.local.php')['db'];
    
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $db['host'],
        'port'      => $db['port'],
        'database'  => $db['database'],
        'username'  => $db['username'],
        'password'  => $db['password'],
        'charset'   => $db['charset'],
        'collation' => $db['collation'],
        'prefix'    => $db['prefix']
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
});

$container->get('db');