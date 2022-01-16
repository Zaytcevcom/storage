<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Audio;
use api\models\protect\AudioProtectModel;

/**
 * AudioModel
 */
class AudioModel extends AudioProtectModel
{
    /**
     * Get file info
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function get(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['audio']['secret_key'] != $secret_key) {
            return Audio::ERROR_SECRET_KEY;
        }

        $model = Audio::getByFileId($file_id);

        if (empty($model)) {
            return Audio::ERROR_NOT_FOUND;
        }

        // File cover sizes
        $cover_sizes = (!empty($model->cover_sizes)) ? json_decode($model->cover_sizes, true) : [];

        foreach ($cover_sizes as $key => $value) {
            $cover_sizes[$key] = $config['scheme'] . '://' . $model->host . $value;
        }

        // File cover crop square
        $cover_crop_square = (!empty($model->cover_crop_square)) ? json_decode($model->cover_crop_square, true) : [];

        foreach ($cover_crop_square as $key => $value) {
            $cover_crop_square[$key] = $config['scheme'] . '://' . $model->host . $value;
        }

        // File cover crop custom
        $cover_crop_custom = (!empty($model->cover_crop_custom)) ? json_decode($model->cover_crop_custom, true) : [];

        foreach ($cover_crop_custom as $key => $value) {
            $cover_crop_custom[$key] = $config['scheme'] . '://' . $model->host . $value;
        }

        $data = [
            'file_id'           => $model->file_id,
            'fields'            => (!empty($model->fields)) ? json_decode($model->fields, true) : null,
            'src'               => $config['scheme'] . '://' . $model->host . $model->dir . $model->name . '.' . $model->ext,
            'cover'             => $config['scheme'] . '://' . $model->host . $model->cover_dir . $model->cover_name . '.' . $model->cover_ext,
            'cover_sizes'       => (!empty($cover_sizes)) ? $cover_sizes : null,
            'cover_crop_square' => (!empty($cover_crop_square)) ? $cover_crop_square : null,
            'cover_crop_custom' => (!empty($cover_crop_custom)) ? $cover_crop_custom : null,
            'duration'          => $model->duration,
            'type'              => $model->type,
            'hash'              => $model->hash,
            'size'              => $model->size,
            'time'              => $model->time,
            'is_use'            => $model->is_use
        ];

        return $data;
    }

    /**
     * Upload file
     * @param array $files
     * @param string $field
     * @param string|null $type
     * @param array $requestParams
     * @return mixed
     */
    public function upload(array $files = [], string $field = 'upload_file', string $type = null, array $requestParams = [])
    {
        if (!isset($files[$field]) || !isset($_FILES[$field]['tmp_name'])) {
            return Audio::ERROR_FAIL_UPLOAD;
        }

        return $this->uploadByTempPath(
            $_FILES[$field]['tmp_name'],
            $type,
            $requestParams
        );
    }

    /**
     * Mark as use
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function markUse(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['audio']['secret_key'] != $secret_key) {
            return Audio::ERROR_SECRET_KEY;
        }

        $model = Audio::getByFileId($file_id);

        if (empty($model)) {
            return 0;
        }

        $model->is_use = 1;
        return $model->save() ? 1 : 0;
    }

    /**
     * Mark as delete
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function markDelete(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['audio']['secret_key'] != $secret_key) {
            return Audio::ERROR_SECRET_KEY;
        }

        $model = Audio::getByFileId($file_id);

        if (empty($model)) {
            return 0;
        }

        $time = time();
        $new_name = '_' . $time . '.' . $model->name;

        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;
        $new_path = ROOT_DIR . $model->dir . $new_name . '.' . $model->ext;

        if (file_exists($path)) {
            rename($path, $new_path);
        }

        $model->name = $new_name;
        $model->hide = $time;

        return $model->save() ? 1 : 0;
    }


    // MARK: - CRON

    /**
     * Delete not use files
     * @return int
     */
    public function delete() : int
    {
        global $config;

        $count = 0;

        $time = time() - $config['audio']['timeStorageNoUse'];

        $models = Audio::where('is_use', 0)->where('time', '<=', $time)->take(50)->get();

        foreach ($models as $model) {

            // Delete audio
            $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

            $success = (file_exists($path)) ? unlink($path) : true;

            if (!$success) {
                return -1;
            }

            // Delete cover
            $cover_path = ROOT_DIR . $model->cover_dir . $model->cover_name . '.' . $model->cover_ext;

            $success = (file_exists($cover_path)) ? unlink($cover_path) : true;

            if (!$success) {
                return -1;
            }

            // Delete covers
            $cover_sizes = (!empty($model->cover_sizes)) ? json_decode($model->cover_sizes, true) : [];

            foreach ($cover_sizes as $size) {
                
                $path = ROOT_DIR . $size;
                
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            if ($success) {
                $model->delete();
                $count++;
            }
        }

        return $count;
    }
}