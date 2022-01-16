<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Photo;
use api\models\protect\PhotoProtectModel;

/**
 * PhotoModel
 */
class PhotoModel extends PhotoProtectModel
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

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

        if (empty($model)) {
            return Photo::ERROR_NOT_FOUND;
        }

        return Photo::getInfo([$model])[0];
    }

    /**
     * Upload file
     * @param array $files
     * @param string $field
     * @param string|null $type
     * @param int|null $rotate
     * @param array|null $params
     * @param array $requestParams
     * @return mixed
     */
    public function upload(array $files = [], string $field = 'upload_file', string $type = null, int $rotate = null, array $params = null, array $requestParams = [])
    {
        if (!isset($files[$field]) || !isset($_FILES[$field]['tmp_name'])) {
            return Photo::ERROR_FAIL_UPLOAD;
        }

        return $this->uploadByTempPath(
            $_FILES[$field]['tmp_name'],
            $type,
            $rotate,
            $params,
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

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

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

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

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

        $sizes = ($model->sizes != '' && !is_null($model->sizes)) ? json_decode($model->sizes, true) : [];

        foreach ($sizes as $key => $value) {

            $path = ROOT_DIR . $value;
            $path_parts = pathinfo($path);
            $_new_name = '_' . $time . '.' . $path_parts['basename'];
            $new_path = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $_new_name;

            $sizes[$key] = $model->dir . $_new_name;

            rename($path, $new_path);
        }

        $model->name = $new_name;
        $model->sizes = json_encode($sizes);
        $model->hide = $time;

        return $model->save() ? 1 : 0;
    }

    /**
     * Resize
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function resize(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

        if (empty($model)) {
            return Photo::ERROR_NOT_FOUND;
        }

        // Check type
        if (
            !isset($config['photo']) ||
            !isset($config['photo']['type']) ||
            !isset($config['photo']['type'][$model->type])
        ) {
            return Photo::ERROR_TYPE;
        }

        $fileSettings = $config['photo']['type'][$model->type];

        // Path to original file
        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

        // Get old files
        $old_files = (!empty($model->sizes)) ? json_decode($model->sizes, true) : null;

        // Resize
        $sizesResult = $this->fileResizeBySettings($fileSettings, $path, $this->quality, $old_files);

        if (!$sizesResult) {
            return Photo::ERROR_CROP;
        }

        // Save info
        $model->sizes = (!empty($sizesResult)) ? json_encode($sizesResult) : null;
        $model->save();

        return Photo::getInfo([$model])[0];
    }

    /**
     * Crop square image
     * @param string|null $file_id
     * @param string|null $secret_key
     * @param array|null $params
     * @return int
     */
    public function cropSquare(string $file_id = null, string $secret_key = null, array $params = null)
    {
        global $config;

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

        if (empty($model)) {
            return Photo::ERROR_NOT_FOUND;
        }

        // Check type
        if (
            !isset($config['photo']) ||
            !isset($config['photo']['type']) ||
            !isset($config['photo']['type'][$model->type])
        ) {
            return Photo::ERROR_TYPE;
        }

        $fileSettings = $config['photo']['type'][$model->type];

        // Path to original file
        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

        // Get old crop square files
        $old_files = (!empty($model->crop_square)) ? json_decode($model->crop_square, true) : null;

        // Crop square and resize
        $cropResult = $this->fileCropBySettings($fileSettings, 0, $path, $params, $this->quality, $old_files);

        if (!$cropResult) {
            return Photo::ERROR_CROP;
        }

        // Save info
        $model->crop_square = (!empty($cropResult)) ? json_encode($cropResult) : null;
        $model->save();

        return Photo::getInfo([$model])[0];
    }

    /**
     * Crop image
     * @param string|null $file_id
     * @param string|null $secret_key
     * @param array|null $params
     * @return int
     */
    public function crop(string $file_id = null, string $secret_key = null, array $params = null)
    {
        global $config;

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

        if (empty($model)) {
            return Photo::ERROR_NOT_FOUND;
        }

        // Check type
        if (
            !isset($config['photo']) ||
            !isset($config['photo']['type']) ||
            !isset($config['photo']['type'][$model->type])
        ) {
            return Photo::ERROR_TYPE;
        }

        $fileSettings = $config['photo']['type'][$model->type];

        // Path to original file
        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

        // Get old crop custom files
        $old_files = (!empty($model->crop_custom)) ? json_decode($model->crop_custom, true) : null;

        // Crop custom and resize
        $cropResult = $this->fileCropBySettings($fileSettings, 1, $path, $params, $this->quality, $old_files);

        if (!$cropResult) {
            return Photo::ERROR_CROP;
        }

        // Save info
        $model->crop_custom = (!empty($cropResult)) ? json_encode($cropResult) : null;
        $model->save();

        return Photo::getInfo([$model])[0];
    }

    /**
     * Processing file by default settings
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function processingDefault(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['photo']['secret_key'] != $secret_key) {
            return Photo::ERROR_SECRET_KEY;
        }

        $model = Photo::getByFileId($file_id);

        if (empty($model)) {
            return Photo::ERROR_NOT_FOUND;
        }

        // Check type
        if (
            !isset($config['photo']) ||
            !isset($config['photo']['type']) ||
            !isset($config['photo']['type'][$model->type])
        ) {
            return Photo::ERROR_TYPE;
        }

        // Crop and resize by settings
        $processing = $this->processingModelByDefaultSettings($model, $config['photo']['type'][$model->type]);

        // Save info
        $model->sizes       = (isset($processing['sizes']) && $processing['sizes']) ? json_encode($processing['sizes']) : null;
        $model->crop_square = (isset($processing['crop_square']) && $processing['crop_square']) ? json_encode($processing['crop_square']) : null;
        $model->crop_custom = (isset($processing['crop_custom']) && $processing['crop_custom']) ? json_encode($processing['crop_custom']) : null;
        $model->save();

        return Photo::getInfo([$model])[0];
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

        $time = time() - $config['photo']['timeStorageNoUse'];

        $models = Photo::where('is_use', 0)->where('time', '<=', $time)->take(50)->get();

        foreach ($models as $model) {

            $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

            $success = (file_exists($path)) ? unlink($path) : true;

            if (!$success) {
                return -1;
            }

            $sizes = (!empty($model->sizes)) ? json_decode($model->sizes, true) : [];

            foreach ($sizes as $size) {
                
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