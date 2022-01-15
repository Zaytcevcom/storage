<?php

declare(strict_types=1);

namespace api\entities;

use api\classes\Entity;

/**
 * @property int $id
 * @property string $file_id
 * @property int $type
 * @property string $host
 * @property string $dir
 * @property string $name
 * @property string $ext
 * @property string|null $fields
 * @property double $size
 * @property string $hash
 * @property string|null $sizes
 * @property string|null $crop_square
 * @property string|null $crop_custom
 * @property int $time
 * @property int $is_use
 * @property int $hide
 * @property int $resize_status
 */
class Photo extends Entity
{
    protected $table = 'photo';

    protected $fillable = [
        'file_id',
        'type',
        'host',
        'dir',
        'name',
        'ext',
        'fields',
        'size',
        'hash',
        'sizes',
        'crop_square',
        'crop_custom',
        'time',
        'is_use',
        'hide',
        'resize_status'
    ];

    protected $casts = [
        'id'            => 'integer',
        'file_id'       => 'string',
        'type'          => 'integer',
        'host'          => 'string',
        'dir'           => 'string',
        'name'          => 'string',
        'ext'           => 'string',
        'fields'        => 'string',
        'size'          => 'double',
        'hash'          => 'string',
        'sizes'         => 'string',
        'crop_square'   => 'string',
        'crop_custom'   => 'string',
        'time'          => 'integer',
        'is_use'        => 'integer',
        'hide'          => 'integer',
        'resize_status' => 'integer'
    ];

    const ERROR_REQUIRED_FIELDS = self::class . 1;
    const ERROR_SECRET_KEY      = self::class . 2;
    const ERROR_TYPE            = self::class . 3;
    const ERROR_NOT_FOUND       = self::class . 4;
    const ERROR_FAIL_UPLOAD     = self::class . 5;
    const ERROR_FAIL_MOVE       = self::class . 6;
    const ERROR_MIN_SIZE        = self::class . 7;
    const ERROR_MAX_SIZE        = self::class . 8;
    const ERROR_ALLOW_TYPES     = self::class . 9;
    const ERROR_OPTIMIZE        = self::class . 10;
    const ERROR_CROP            = self::class . 11;
    const ERROR_SAVE            = self::class . 12;

    const SALT = 'photo';
}