<?php

declare(strict_types=1);

namespace core;

class CoreEntity extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    /**
     * Get the available fields
     */
    public static function getAvailableFields()
    {
        return [];
    }

    /**
     * Check model by ID
     * @param int $id
     * @return bool
     */
    public static function checkById($id)
    {
        $model = self::select(['id'])->where('id', $id)->first();

        return (!empty($model)) ? true : false;
    }

    /**
     * Get model by ID
     * @param int $id
     * @return mixed
     */
    public static function getById($id)
    {
        return self::where('id', $id)->first();
    }

    /**
     * Get models by ID
     * @param array $ids
     * @return mixed
     */
    public static function getByIds($ids)
    {
        return self::whereIn('id', $ids)->get();
    }

    /**
     * Get model by file_id
     * @param string|null $file_id
     * @return mixed
     */
    public static function getByFileId(string $file_id = null)
    {
        return self::where('file_id', $file_id)->where('hide', 0)->first();
    }
    
    /**
     * Get array keys
     * @param array $array
     * @return array
     */
    public static function getArrayKeys(array $array, array $field = []): array
    {
        $keys = [];

        foreach ($array as $key => $value)
        {
            if (is_array($array[$key])) {
                $keys = array_merge($keys, self::getArrayKeys($array[$key]));
            } else {
                $keys[] = $key;
            }
        }

        return array_unique($keys);
    }
}