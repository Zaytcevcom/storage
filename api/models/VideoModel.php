<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Video;
use api\models\protect\PhotoProtectModel;
use api\models\protect\VideoProtectModel;

/**
 * VideoModel
 */
class VideoModel extends VideoProtectModel
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

        if ($config['video']['secret_key'] != $secret_key) {
            return Video::ERROR_SECRET_KEY;
        }

        $model = Video::getByFileId($file_id);

        if (empty($model)) {
            return Video::ERROR_NOT_FOUND;
        }

        return Video::getInfo([$model])[0];
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
            return Video::ERROR_FAIL_UPLOAD;
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

        if ($config['video']['secret_key'] != $secret_key) {
            return Video::ERROR_SECRET_KEY;
        }

        $model = Video::getByFileId($file_id);

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

        if ($config['video']['secret_key'] != $secret_key) {
            return Video::ERROR_SECRET_KEY;
        }

        $model = Video::getByFileId($file_id);

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

    /**
     * Processing cover file by default settings
     * @param string|null $file_id
     * @param string|null $secret_key
     * @return mixed
     */
    public function coverProcessing(string $file_id = null, string $secret_key = null)
    {
        global $config;

        if ($config['video']['secret_key'] != $secret_key) {
            return Video::ERROR_SECRET_KEY;
        }

        $model = Video::getByFileId($file_id);

        if (empty($model)) {
            return Video::ERROR_NOT_FOUND;
        }

        // Check type
        if (
            !isset($config['photo']) ||
            !isset($config['photo']['type']) ||
            !isset($config['photo']['type'][$model->type])
        ) {
            return Video::ERROR_TYPE;
        }

        // Crop and resize by settings
        $PhotoProtectedModel = new PhotoProtectModel();
        $processing = $PhotoProtectedModel->processingModelCoverByDefaultSettings($model, $config['video']['type'][$model->type]['cover']);

        // Save info
        $model->cover_sizes       = (isset($processing['sizes']) && $processing['sizes']) ? json_encode($processing['sizes']) : null;
        $model->cover_crop_square = (isset($processing['crop_square']) && $processing['crop_square']) ? json_encode($processing['crop_square']) : null;
        $model->cover_crop_custom = (isset($processing['crop_custom']) && $processing['crop_custom']) ? json_encode($processing['crop_custom']) : null;
        $model->save();

        return Video::getInfo([$model])[0];
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

        $time = time() - $config['video']['timeStorageNoUse'];

        $models = Video::where('is_use', 0)->where('time', '<=', $time)->take(50)->get();

        return 0;
    }
}