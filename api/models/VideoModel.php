<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Video;
use api\classes\Model;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use getID3;

/**
 * VideoModel
 */
class VideoModel extends Model
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

        if ($config['video']['secret_key'] != $secret_key) {
            return Video::ERROR_SECRET_KEY;
        }

        $model = Video::getByFileId($file_id);

        if (empty($model)) {
            return Video::ERROR_NOT_FOUND;
        }

        // File sizes
        $src_sizes = (!empty($model->sizes)) ? json_decode($model->sizes, true) : [];

        foreach ($src_sizes as $key => $value) {
            $src_sizes[$key] = $config['scheme'] . '://' . $model->host . $value;
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
            'src_sizes'         => $src_sizes,
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
            return Video::ERROR_FAIL_UPLOAD;
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
     * @return mixed
     */
    public function uploadByTempPath(string $file_temp_path = null, string $type = null, array $params = [])
    {
        global $config;

        if (empty($file_temp_path)) {
            return Video::ERROR_FAIL_UPLOAD;
        }

        // Check type
        if (!isset($config['video']['type'][$type])) {
            return Video::ERROR_TYPE;
        }

        $typeInfo = $config['video']['type'][$type];

        $fields = [];

        // Check fields
        if (isset($typeInfo['fields'])) {
            foreach ($typeInfo['fields'] as $value) {
                if (!isset($params[$value])) {
                    return Video::ERROR_REQUIRED_FIELDS;
                }

                $fields[$value] = $params[$value];
            }
        }

        // Get file info
        $getID3 = new getID3();
        $videoInfo = $getID3->analyze($file_temp_path);

        if (!isset($videoInfo['filesize']) || !isset($videoInfo['fileformat'])) {
            return Video::ERROR_FAIL_UPLOAD;
        }
        
        $size   = $videoInfo['filesize'];
        $ext    = $videoInfo['fileformat'];

        // Check min file size
        if ($size < $config['video']['minSize']) {
            return Video::ERROR_MIN_SIZE;
        }

        // Check max file size
        if ($config['video']['maxSize'] < $size) {
            return Video::ERROR_MAX_SIZE;
        }

        // Check file type
        if (!in_array($ext, $config['video']['allowTypes'])) {
            return Video::ERROR_ALLOW_TYPES;
        }

        $hash = hash_file($this->algo, $file_temp_path);

        $result = $this->fileMove($config['video']['dir'], $file_temp_path, $hash);

        if (!isset($result['status']) || $result['status'] != true) {
            return Video::ERROR_FAIL_MOVE;
        }

        $path = ROOT_DIR . $result['dir'] . $result['name'] . '.' . $ext;
        
        // Get video cover info
        $coverInfo = $this->createCover($path, $type);
        
        $modelVideo = null;

        $countAttempt = 50;

        while ($countAttempt > 0) {

            try {
                $modelVideo                     = new Video();
                $modelVideo->file_id            = $this->uniqid();
                $modelVideo->type               = $type;
                $modelVideo->host               = $config['domain'];
                $modelVideo->dir                = $result['dir'];
                $modelVideo->name               = $result['name'];
                $modelVideo->ext                = $ext;
                $modelVideo->fields             = json_encode($fields);
                $modelVideo->size               = (int)$size;
                $modelVideo->duration           = (int)$videoInfo['playtime_seconds'];
                $modelVideo->hash               = $hash;
                $modelVideo->sizes              = null;
                $modelVideo->cover_dir          = $coverInfo['dir'];
                $modelVideo->cover_name         = $coverInfo['name'];
                $modelVideo->cover_ext          = $coverInfo['ext'];
                $modelVideo->cover_size         = $coverInfo['size'];
                $modelVideo->cover_sizes        = (!empty($coverInfo['sizes'])) ? json_encode($coverInfo['sizes']) : null;
                $modelVideo->cover_crop_square  = (!empty($coverInfo['crop_square'])) ? json_encode($coverInfo['crop_square']) : null;
                $modelVideo->cover_crop_custom  = (!empty($coverInfo['crop_custom'])) ? json_encode($coverInfo['crop_custom']) : null;
                $modelVideo->time               = time();
                $modelVideo->is_use             = 0;
                $modelVideo->hide               = 0;
                
                if ($modelVideo->save()) {
                    break;
                }

            } catch (\Exception $exception) {

                $countAttempt--;

                if ($countAttempt <= 0) {
                    
                    // todo: delete files

                    return Video::ERROR_SAVE;
                }

                continue;
            }
        }

        return [
            'host'    => $config['scheme'] . '://' . $modelVideo->host,
            'file_id' => $modelVideo->file_id
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

        if (
            !isset($config['video']) ||
            !isset($config['video']['type']) ||
            !isset($config['video']['type'][$type])
        ) {
            return $result;
        }

        $typeInfo = $config['video']['type'][$type];

        // No need create cover
        if (
            !isset($typeInfo['cover']) ||
            !isset($typeInfo['cover']['is_need']) ||
            !$typeInfo['cover']['is_need']
        ) {
            return $result;
        }

        $ext            = 'jpg';
        $temp_path_dir  = ROOT_DIR . $config['temp']['dir'];
        $temp_path_name = $temp_path_dir . '/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $ext;
        
        if (!$this->checkDirIsExists($temp_path_dir)) {
            return $result;
        }
        
        // Create video preview
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'   => ROOT_DIR . '/api/classes/ffmpeg/ffmpeg',
            'ffprobe.binaries'  => ROOT_DIR . '/api/classes/ffmpeg/ffprobe'
        ]);
        
        // PHP settings -> disable_functions -> delete "proc_open"
        $video = $ffmpeg->open($path);
        $video->frame(TimeCode::fromSeconds(1))->save($temp_path_name);
        
        // Upload video cover
        $PhotoModel = new PhotoModel();
        $response = $PhotoModel->uploadCoverByTempPath($temp_path_name, 'video', $type);

        if (is_array($response) && isset($response['file_id'])) {
            $result = [
                'dir'           => $response['dir'],
                'name'          => $response['name'],
                'ext'           => $response['ext'],
                'size'          => $response['size'],
                'sizes'         => $response['sizes'],
                'crop_square'   => $response['crop_square'],
                'crop_custom'   => $response['crop_custom'],
            ];
        }

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


    // MARK - CRON

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


    // MARK - private file methods

    /**
     * Check dir is exists
     * @param string $path
     * @return bool
     */
    private function checkDirIsExists($path) : bool
    {
        if (!file_exists($path)) {
            if (!mkdir($path, $this->mode, true)) {
                return false;
            }
        }
        
        return true;
    }
    
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

        $level = (isset($config['video']['level'])) ? $config['video']['level'] : $levelDefault;

        try {

            $extension = pathinfo($file_temp_path, PATHINFO_EXTENSION);

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
            
            if (!$this->checkDirIsExists($dir)) {
                return [
                    'status'    => false,
                    'message'   => 'Can not create dir!'
                ];
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