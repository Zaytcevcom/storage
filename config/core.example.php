<?php

/**
 * @OA\Info(title="File Storage API", version="1.0")
 * @OA\Server(url="https://storage-app.ru/method/")
 * @OA\Server(url="http://storage/method/")
 */
return [
    'version'       => '1.0',
    'domain'        => 'storage-app.ru',
    'scheme'        => 'https',
    'redirect'      => 'https://storage-app.ru/documentation/index.html',
    'statistics'    => 0, // 1 - вкл. сбор статистики обращения к методам API

    // Директории файлов
    'dirFiles' => '/files',

    // Минимально необходимое свободное место на сервере
    'free_space' => 2 * 1024 * 1024 * 1024, // (минимум 2 гб)

    // Используется облачное S3 хранилище, либо локальное на сервере
    's3' => [
        'enable'    => 1,
        'bucket'    => 'BUCKET_NAME',
        'key'       => 'KEY_YYYYYY',
        'secret'    => 'SECRET_ZZZZZZ',
        'region'    => 'us-east-1',
        'endpoint'  => 'https://storage.yandexcloud.net',
        'version'   => 'latest',
    ],

    // Настройка изображений
    'photo' => [
        'secret_key'        => 'SECRET_XXXXXXX',
        'dir'               => '/files/p',
        'level'             => 4,
        'allowTypes'        => ['jpg', 'jpeg', 'png', 'gif'],
        'minSizeOptimize'   => 0.5 * 1024 * 1024, // минимальный размер файла, меньше которого не происходит сжатие
        'minSize'           => 0.005 * 1024 * 1024, // 0.005 мб
        'maxSize'           => 100 * 1024 * 1024, // 20 мб
        'timeStorageNoUse'  => 1 * 60 * 60 * 24,
        'timeStorageDelete' => 3 * 30 * 60 * 60 * 24,

        'type' => [

            // 1 - photo
            1 => [
                'fields' => ['story_id'],

                // Ресайз
                'sizes' => [
                    'is_need'   => 1,
                    'resize'    => [480, 360],
                ],

                // Квадратная миниатюра
                'crop_square' => [
                    'is_need'   => 0,
                    'resize'    => [720, 480, 360],
                ],

                // Прямоугольная миниатюра
                'crop_custom' => [
                    'is_need' => 0,
                    'default' => [
                        'width'     => 1920,
                        'height'    => 1080,
                    ],
                    'resize' => [720, 480, 360],
                ],
            ],
        ]
    ],

    // Настройка аудио-файлов
    'audio' => [
        'secret_key'        => 'SECRET_XXXXXXX',
        'dir'               => '/files/a',
        'dir_cover'         => '/files/ac',
        'level'             => 4,
        'allowTypes'        => ['mp3'],
        'minSize'           => 0.005 * 1024 * 1024, // 0.01 мб
        'maxSize'           => 400 * 1024 * 1024, // 400 мб
        'timeStorageNoUse'  => 1 * 60 * 60 * 24,
        'timeStorageDelete' => 3 * 30 * 60 * 60 * 24,
        'type' => [
            1 => [
                'fields' => ['union_id']
            ],
        ]
    ],

    // Настройка видео-файлов
    'video' => [
        'secret_key'        => 'SECRET_XXXXXXX',
        'dir'               => '/files/v',
        'dir_cover'         => '/files/vc',
        'level'             => 4,
        'allowTypes'        => ['mp4'],
        'minSize'           => 0.005 * 1024 * 1024, // 0.01 мб
        'maxSize'           => 400 * 1024 * 1024, // 400 мб
        'timeStorageNoUse'  => 1 * 60 * 60 * 24,
        'timeStorageDelete' => 3 * 30 * 60 * 60 * 24,
        
        'type' => [
            
            // 101 - video
            101 => [
                'fields' => ['story_id'],

                'cover' => [

                    // Необходимо ли создавать обложку
                    'is_need' => 1,

                    // Ресайз
                    'sizes' => [
                        'is_need'   => 1,
                        'resize'    => [480, 360],
                    ],

                    // Квадратная миниатюра
                    'crop_square' => [
                        'is_need'   => 0,
                        'resize'    => [720, 480, 360],
                    ],

                    // Прямоугольная миниатюра
                    'crop_custom' => [
                        'is_need' => 0,
                        'default' => [
                            'width'     => 240,
                            'height'    => 240,
                        ],
                        'resize' => [720, 480, 360],
                    ],
                ]
            ],
        ]
    ],
    
    // Настройка временных файлов
    'temp' => [
        'dir'               => '/files/temp',
        'timeStorageDelete' => 3 * 30 * 60 * 60 * 24,
    ],
    
    // DataBase
    'db' => [
        'host'      => 'localhost',
        'port'      => 3306,
        'database'  => 'DATABASE',
        'username'  => 'USER',
        'password'  => 'PASSWORD',
        'charset'   => 'utf8',
        'collation' => 'utf8_general_ci',
        'prefix'    => ''
    ],
];
