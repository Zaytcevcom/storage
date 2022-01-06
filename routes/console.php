<?php

if (!isset($argv[1])) {
    exit('No arguments!' . PHP_EOL . PHP_EOL);
}

$filename = ROOT_DIR . '/api/console/' . $argv[1] . '.php';

if (!file_exists($filename)) {
    exit('Method not found!' . PHP_EOL . PHP_EOL);
}

require $filename;