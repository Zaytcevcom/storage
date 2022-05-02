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
        'host_s3',
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
        'host_s3'       => 'string',
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

    /**
     * @param array $data
     * @return array
     */
    public static function getInfo($data) : array
    {
        global $config;

        $items = [];

        $time = time();

        foreach ($data as $elem) {

            $host   = (!empty($elem->host_s3)) ? $elem->host_s3 : $elem->host;
            $scheme = (!empty($elem->host_s3)) ? 'https' : $config['scheme'];

            // File sizes
            $sizes = (!empty($elem->sizes)) ? json_decode($elem->sizes, true) : [];

            foreach ($sizes as $key => $value) {
                $sizes[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            // File crop square
            $crop_square = (!empty($elem->crop_square)) ? json_decode($elem->crop_square, true) : [];

            foreach ($crop_square as $key => $value) {
                $crop_square[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            // File crop custom
            $crop_custom = (!empty($elem->crop_custom)) ? json_decode($elem->crop_custom, true) : [];

            foreach ($crop_custom as $key => $value) {
                $crop_custom[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            $items[] = [
                'file_id'       => $elem->file_id,
                'fields'        => (!empty($elem->fields)) ? json_decode($elem->fields, true) : null,
                'original'      => $scheme . '://' . $host . $elem->dir . $elem->name . '.' . $elem->ext,
                'sizes'         => (!empty($sizes)) ? $sizes : null,
                'crop_square'   => (!empty($crop_square)) ? $crop_square : null,
                'crop_custom'   => (!empty($crop_custom)) ? $crop_custom : null,
                'type'          => $elem->type,
                'hash'          => $elem->hash,
                'size'          => $elem->size,
                'time'          => $elem->time,
                'is_use'        => $elem->is_use
            ];
        }

        return $items;
    }
}