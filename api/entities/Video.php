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
class Video extends Entity
{
    protected $table = 'video';

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

    const SALT = 'video';

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
            $src_sizes = (!empty($elem->sizes)) ? json_decode($elem->sizes, true) : [];

            foreach ($src_sizes as $key => $value) {
                $src_sizes[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            // File cover sizes
            $cover_sizes = (!empty($elem->cover_sizes)) ? json_decode($elem->cover_sizes, true) : [];

            foreach ($cover_sizes as $key => $value) {
                $cover_sizes[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            // File cover crop square
            $cover_crop_square = (!empty($elem->cover_crop_square)) ? json_decode($elem->cover_crop_square, true) : [];

            foreach ($cover_crop_square as $key => $value) {
                $cover_crop_square[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            // File cover crop custom
            $cover_crop_custom = (!empty($elem->cover_crop_custom)) ? json_decode($elem->cover_crop_custom, true) : [];

            foreach ($cover_crop_custom as $key => $value) {
                $cover_crop_custom[$key] = $scheme . '://' . $host . $value . '?t=' . $time;
            }

            $items[] = [
                'file_id'           => $elem->file_id,
                'fields'            => (!empty($elem->fields)) ? json_decode($elem->fields, true) : null,
                'src'               => $scheme . '://' . $host . $elem->dir . $elem->name . '.' . $elem->ext,
                'src_sizes'         => $src_sizes,
                'cover'             => $scheme . '://' . $host . $elem->cover_dir . $elem->cover_name . '.' . $elem->cover_ext,
                'cover_sizes'       => (!empty($cover_sizes)) ? $cover_sizes : null,
                'cover_crop_square' => (!empty($cover_crop_square)) ? $cover_crop_square : null,
                'cover_crop_custom' => (!empty($cover_crop_custom)) ? $cover_crop_custom : null,
                'duration'          => $elem->duration,
                'type'              => $elem->type,
                'hash'              => $elem->hash,
                'size'              => $elem->size,
                'time'              => $elem->time,
                'is_use'            => $elem->is_use
            ];
        }

        return $items;
    }
}