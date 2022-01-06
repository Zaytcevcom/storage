<?php

declare(strict_types=1);

namespace api\entities;

use api\classes\Entity;

/**
 * @property int $id
 * @property string $controller
 * @property string $action
 * @property double $duration
 * @property double $memory
 * @property string $ip
 * @property int $time
 */
class Statistics extends Entity
{
    protected $table = '_statistics';

    protected $fillable = [
        'controller',
        'action',
        'duration',
        'memory',
        'ip',
        'time'
    ];

    protected $casts = [
        'id'            => 'integer',
        'controller'    => 'string',
        'action'        => 'string',
        'duration'      => 'double',
        'memory'        => 'double',
        'ip'            => 'string',
        'time'          => 'integer',
    ];
}