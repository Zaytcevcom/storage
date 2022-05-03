<?php

use api\entities\Cover;
use api\entities\Photo;
use api\entities\Video;
use api\models\protect\PhotoProtectModel;
use api\models\protect\VideoProtectModel;

$PhotoProtectModel = new PhotoProtectModel();
$VideoProtectModel = new VideoProtectModel();

global $config;

// Photo
$offset = 0;
$count = 100;

while(true) 
{
    $models = Photo::where('hide', 0)
        ->whereNull('host_s3')
        ->orderBy('id', 'ASC')
        ->take($count)
        ->get();

    if (count($models) == 0) {
        echo PHP_EOL . PHP_EOL . 'Photo done!' . PHP_EOL;
        break;
    }

    $offset += count($models);

    foreach ($models as $model) {
        $PhotoProtectModel->loadToS3($config['s3'], $model);
    }

    echo PHP_EOL . '[Photo] Offset: ' . $offset . PHP_EOL;
}

// Cover
$offset = 0;
$count = 100;

while(true) 
{
    $models = Cover::where('hide', 0)
        ->whereNull('host_s3')
        ->orderBy('id', 'ASC')
        ->take($count)
        ->get();

    if (count($models) == 0) {
        echo PHP_EOL . PHP_EOL . 'Cover done!' . PHP_EOL;
        break;
    }

    $offset += count($models);

    foreach ($models as $model) {
        $PhotoProtectModel->loadToS3($config['s3'], $model);
    }

    echo PHP_EOL . '[Cover] Offset: ' . $offset . PHP_EOL;
}

// Video
$offset = 0;
$count = 100;

while(true) 
{
    $models = Video::where('hide', 0)
        ->whereNull('host_s3')
        ->orderBy('id', 'ASC')
        ->take($count)
        ->get();

    if (count($models) == 0) {
        echo PHP_EOL . PHP_EOL . 'Video done!' . PHP_EOL;
        break;
    }

    $offset += count($models);

    foreach ($models as $model) {
        $VideoProtectModel->loadToS3($config['s3'], $model);
    }

    echo PHP_EOL . '[Video] Offset: ' . $offset . PHP_EOL;
}