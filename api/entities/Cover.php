<?php

declare(strict_types=1);

namespace api\entities;

use api\classes\Entity;

/**
 * @property int $id
 * @property string $file_id
 * @property string $media_type
 * @property int $type
 * @property string $host
 * @property string|null $host_s3
 * @property string $dir
 * @property string $name
 * @property string $ext
 * @property double $size
 * @property string $hash
 * @property string|null $sizes
 * @property string|null $crop_square
 * @property string|null $crop_custom
 * @property int $time
 * @property int $hide
 * @property int $resize_status
 */
class Cover extends Entity
{
    protected $table = 'cover';

    protected $fillable = [
        'file_id',
        'media_type',
        'type',
        'host',
        'host_s3',
        'dir',
        'name',
        'ext',
        'size',
        'hash',
        'sizes',
        'crop_square',
        'crop_custom',
        'time',
        'hide',
        'resize_status'
    ];

    protected $casts = [
        'id'            => 'integer',
        'file_id'       => 'string',
        'media_type'    => 'string',
        'type'          => 'integer',
        'host'          => 'string',
        'host_s3'       => 'string',
        'dir'           => 'string',
        'name'          => 'string',
        'ext'           => 'string',
        'size'          => 'double',
        'hash'          => 'string',
        'sizes'         => 'string',
        'crop_square'   => 'string',
        'crop_custom'   => 'string',
        'time'          => 'integer',
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

    const SALT = 'cover';

        /**
     * @param array $data
     * @return array
     */
    public static function getInfoProtected($data) : array
    {
        $items = [];

        foreach ($data as $elem) {

            $items[] = [
                'file_id'       => $elem->file_id,
                'dir'           => $elem->dir,
                'name'          => $elem->name,
                'ext'           => $elem->ext,
                'size'          => (int)$elem->size,
                'sizes'         => (!empty($elem->sizes)) ? json_decode($elem->sizes, true) : null,
                'crop_square'   => (!empty($elem->crop_square)) ? json_decode($elem->crop_square, true) : null,
                'crop_custom'   => (!empty($elem->crop_custom)) ? json_decode($elem->crop_custom, true) : null,
            ];
        }

        return $items;
    }
}