<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Audio;
use api\classes\Model;
use getID3;

/**
 * AudioModel
 */
class AudioModel extends Model
{
    private $algo = 'sha1';
    private $mode = 0755; // mkdir mode

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
     * @param array $params
     * @return mixed
     */
    public function upload(array $files = [], string $field = 'upload_file', string $type = null, array $params = [])
    {
        if (!isset($files[$field]) || !isset($_FILES[$field]['tmp_name'])) {
            return Audio::ERROR_FAIL_UPLOAD;
        }

        return $this->uploadByTempPath(
            $_FILES[$field]['tmp_name'],
            $type,
            $params
        );
    }

    /**
     * Upload file by temp path
     * @param string|null $file_temp_path
     * @param string|null $type
     * @param array $params
     * @return array
     */
    public function uploadByTempPath(string $file_temp_path = null, string $type = null, array $params = [])
    {
        global $config;
        
        if (empty($file_temp_path)) {
            return Audio::ERROR_FAIL_UPLOAD;
        }

        // Check type
        if (!isset($config['audio']['type'][$type])) {
            return Audio::ERROR_TYPE;
        }

        $typeInfo = $config['audio']['type'][$type];

        $fields = [];

        // Check fields
        if (isset($typeInfo['fields'])) {
            foreach ($typeInfo['fields'] as $value) {
                if (!isset($params[$value])) {
                    return Audio::ERROR_REQUIRED_FIELDS;
                }

                $fields[$value] = $params[$value];
            }
        }

        // Get file info
        $getID3 = new getID3();
        $audioInfo = $getID3->analyze($file_temp_path);

        if (!isset($audioInfo['filesize']) || !isset($audioInfo['fileformat'])) {
            return Audio::ERROR_FAIL_UPLOAD;
        }
        
        $size   = $audioInfo['filesize'];
        $ext    = $audioInfo['fileformat'];

        // Check min file size
        if ($size < $config['audio']['minSize']) {
            return Audio::ERROR_MIN_SIZE;
        }

        // Check max file size
        if ($config['audio']['maxSize'] < $size) {
            return Audio::ERROR_MAX_SIZE;
        }

        // Check file type
        if (!in_array($ext, $config['audio']['allowTypes'])) {
            return Audio::ERROR_ALLOW_TYPES;
        }

        $hash = hash_file($this->algo, $file_temp_path);

        $result = $this->fileMove($config['audio']['dir'], $file_temp_path, $hash);

        if (!isset($result['status']) || $result['status'] != true) {
            return Audio::ERROR_FAIL_MOVE;
        }

        $path = ROOT_DIR . $result['dir'] . $result['name'] . '.' . $ext;

        // Get audio cover info
        $coverInfo = $this->createCover($path, $type);

        $modelAudio = null;

        $countAttempt = 50;

        while ($countAttempt > 0) {

            try {
                $modelAudio                     = new Audio();
                $modelAudio->file_id            = $this->uniqid();
                $modelAudio->type               = $type;
                $modelAudio->host               = $config['domain'];
                $modelAudio->dir                = $result['dir'];
                $modelAudio->name               = $result['name'];
                $modelAudio->ext                = $ext;
                $modelAudio->fields             = json_encode($fields);
                $modelAudio->size               = (int)$size;
                $modelAudio->duration           = (int)$audioInfo['playtime_seconds'];
                $modelAudio->hash               = $hash;
                $modelAudio->sizes              = null;
                $modelAudio->cover_dir          = $coverInfo['dir'];
                $modelAudio->cover_name         = $coverInfo['name'];
                $modelAudio->cover_ext          = $coverInfo['ext'];
                $modelAudio->cover_size         = $coverInfo['size'];
                $modelAudio->cover_sizes        = (!empty($coverInfo['sizes'])) ? json_encode($coverInfo['sizes']) : null;
                $modelAudio->cover_crop_square  = (!empty($coverInfo['crop_square'])) ? json_encode($coverInfo['crop_square']) : null;
                $modelAudio->cover_crop_custom  = (!empty($coverInfo['crop_custom'])) ? json_encode($coverInfo['crop_custom']) : null;
                $modelAudio->time               = time();
                $modelAudio->is_use             = 0;
                $modelAudio->hide               = 0;
                
                if ($modelAudio->save()) {
                    break;
                }

            } catch (\Exception $exception) {

                $countAttempt--;

                if ($countAttempt <= 0) {
                    
                    // todo: delete files

                    return Audio::ERROR_SAVE;
                }

                continue;
            }
        }

        return [
            'host'    => $config['scheme'] . '://' . $modelAudio->host,
            'file_id' => $modelAudio->file_id
        ];
    }

    /**
     * Create cover
     * @param string|null $path
     * @param string|null $type
     * @return array
     */
    private function createCover($path = null, $type = null)
    {
        global $config;

        $result = [
            'dir'           => null,
            'name'          => null,
            'ext'           => null,
            'size'          => null,
            'sizes'         => null,
            'crop_square'   => null,
            'crop_custom'   => null
        ];

        $ext            = 'jpg';
        $temp_path_dir  = ROOT_DIR . $config['temp']['dir'];
        $temp_path_name = $temp_path_dir . '/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $ext;

        // todo: get cover from file...

        return $result;
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


    // MARK - CRON

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


    // MARK - private file methods

    /**
     * Move uploaded file file
     * @param string $directory
     * @param string $file_temp_path
     * @param string $hash
     * @return array
     */
    private function fileMove(string $directory, $file_temp_path, string $hash)
    {
        global $config;

        $levelDefault = 4;

        $level = (isset($config['audio']['level'])) ? $config['audio']['level'] : $levelDefault;

        try {

            // Get file info
            $getID3 = new getID3();
            $fileInfo = $getID3->analyze($file_temp_path);
            $extension = $fileInfo['fileformat'];

            if (strlen($hash) < $level * 2) {
                $level = $levelDefault;
            }
            
            $month = floor(time() / 30 / 24 / 60 / 60);
            $_level = mb_substr($hash, 0, $level * 2, 'UTF-8');

            $basename = $month . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, str_split($_level, 2)) . DIRECTORY_SEPARATOR . str_replace($_level, '', $hash);

            $dir = ROOT_DIR . $directory . DIRECTORY_SEPARATOR . $basename;
            
            if (!file_exists($dir)) {
                if (!mkdir($dir, $this->mode, true)) {
                    return [
                        'status'    => false,
                        'message'   => 'Can not create dir!'
                    ];
                }
            }

            for ($i = 0; $i <= 100; $i++) {

                if ($i == 100) {
                    return [
                        'status'    => false,
                        'message'   => 'More iterations!'
                    ];
                }

                $filename = $this->uniqid();
                $path = $dir . DIRECTORY_SEPARATOR . $filename . '.' . $extension;

                if (!file_exists($path)) {
                    break;
                }
            }

            rename($file_temp_path, $path);

            return [
                'status'     => true,
                'dir'        => $directory . '/' . $basename . '/',
                'name'       => $filename,
            ];

        } catch (\Exception $exception) {

            return [
                'status'    => false,
                'message'   => 'Exception!'
            ];

        }
    }
}