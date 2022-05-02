<?php

declare(strict_types=1);

namespace api\entities;

use api\classes\Entity;

/**
 * @property int $id
 * @property string $file_id
 * @property int $type
 * @property string $host
 * @property string|null $host_s3
 * @property string $dir
 * @property string $name
 * @property string $ext
 * @property string|null $fields
 * @property double $size
 * @property int $duration
 * @property string $hash
 * @property string|null $sizes
 * @property string|null $cover_dir
 * @property string|null $cover_name
 * @property string|null $cover_ext
 * @property double|null $cover_size
 * @property string|null $cover_sizes
 * @property string|null $cover_crop_square
 * @property string|null $cover_crop_custom
 * @property int $time
 * @property int $is_use
 * @property int $hide
 */
class Audio extends Entity
{
    protected $table = 'audio';

    protected $fillable = [
        'file_id',
        'type',
        'host',
        'host_s3',
        'dir',
        'name',
        'ext',
        'fields',
        'size',
        'duration',
        'hash',
        'sizes',
        'cover_dir',
        'cover_name',
        'cover_ext',
        'cover_size',
        'cover_sizes',
        'cover_crop_square',
        'cover_crop_custom',
        'time',
        'is_use',
        'hide',
    ];

    protected $casts = [
        'id'                    => 'integer',
        'file_id'               => 'string',
        'type'                  => 'integer',
        'host'                  => 'string',
        'host_s3'               => 'string',
        'dir'                   => 'string',
        'name'                  => 'string',
        'ext'                   => 'string',
        'fields'                => 'string',
        'size'                  => 'double',
        'duration'              => 'integer',
        'hash'                  => 'string',
        'sizes'                 => 'string',
        'cover_dir'             => 'string',
        'cover_name'            => 'string',
        'cover_ext'             => 'string',
        'cover_size'            => 'double',
        'cover_sizes'           => 'string',
        'cover_crop_square'     => 'string',
        'cover_crop_custom'     => 'string',
        'time'                  => 'integer',
        'is_use'                => 'integer',
        'hide'                  => 'integer',
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
    const ERROR_SAVE            = self::class . 11;

    const SALT = 'audio';
}