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

        $data = [
            'file_id'       => $model->file_id,
            'fields'        => (!empty($model->fields)) ? json_decode($model->fields, true) : null,
            'src'           => $config['scheme'] . '://' . $model->host . $model->dir . $model->name . '.' . $model->ext,
            'src_sizes'     => $src_sizes,
            'cover'         => $config['scheme'] . '://' . $model->host . $model->cover_dir . $model->cover_name . '.' . $model->cover_ext,
            'cover_sizes'   => $cover_sizes,
            'duration'      => $model->duration,
            'type'          => $model->type,
            'hash'          => $model->hash,
            'size'          => $model->size,
            'time'          => $model->time,
            'is_use'        => $model->is_use
        ];

        return $data;
    }

    /**
     * Upload file
     * @param array $files
     * @param string $field
     * @param string|null $type
     * @param array $requestParams
     * @return array
     */
    public function upload(array $files = [], string $field = 'upload_file', string $type = null, array $requestParams = [])
    {
        global $config;
        
        if (!isset($files[$field])) {
            return Video::ERROR_FAIL_UPLOAD;
        }

        $file = $files[$field];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return Video::ERROR_FAIL_UPLOAD;
        }        

        $size = $file->getSize();
        $ext = strtolower(end(explode('.', $file->getClientFilename())));

        // Check type
        if (!isset($config['video']['type'][$type])) {
            return Video::ERROR_TYPE;
        }

        $typeInfo = $config['video']['type'][$type];

        $fields = [];

        // Check fields
        if (isset($typeInfo['fields'])) {
            foreach ($typeInfo['fields'] as $value) {
                if (!isset($requestParams[$value])) {
                    return Video::ERROR_REQUIRED_FIELDS;
                }

                $fields[$value] = $requestParams[$value];
            }
        }

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

        $hash = hash_file($this->algo, $_FILES[$field]['tmp_name']);
        $sizes = [];

        $result = $this->fileMove($config['video']['dir'], $file, $hash);

        if (!isset($result['status']) || $result['status'] != true) {
            return Video::ERROR_FAIL_MOVE;
        }

        $path = ROOT_DIR . $result['dir'] . $result['name'] . '.' . $result['ext'];

        // Get video info
        $getID3 = new getID3();
        $videoInfo = $getID3->analyze($path);
        
        $temp_path_dir = ROOT_DIR . $config['temp']['dir'];
        $temp_path_name = $temp_path_dir . '/' . $result['name'] . '.jpg';
        
        if (!$this->checkDirIsExists($temp_path_dir)) {
            return Video::ERROR_FAIL_MOVE;
        }
        
        // Create video preview
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => ROOT_DIR . '/api/classes/ffmpeg/ffmpeg',
            'ffprobe.binaries' => ROOT_DIR . '/api/classes/ffmpeg/ffprobe'
        ]);
        
        // https://stackoverflow.com/questions/29916963/laravel-unable-to-load-ffprobe
        // PHP -> disable_functions -> delete "proc_open"
        $video = $ffmpeg->open($path);
        $video
            ->frame(TimeCode::fromSeconds(1))
            ->save($temp_path_name);
        
        // Upload video preview
        $PhotoModel = new PhotoModel();
        //$PhotoModel->upload(['upload_file' => ]);

        while (true) {

            try {
                $modelVideo = new Video();
                $modelVideo->file_id        = $this->uniqid();
                $modelVideo->type           = $type;
                $modelVideo->host           = $config['domain'];
                $modelVideo->dir            = $result['dir'];
                $modelVideo->name           = $result['name'];
                $modelVideo->ext            = $result['ext'];
                $modelVideo->fields         = json_encode($fields);
                $modelVideo->size           = $result['size'];
                $modelVideo->duration       = (int)$videoInfo['playtime_seconds'];
                $modelVideo->hash           = $hash;
                $modelVideo->sizes          = (!empty($sizes)) ? json_encode($sizes) : null;
                $modelVideo->cover_dir      = null;
                $modelVideo->cover_name     = null;
                $modelVideo->cover_ext      = null;
                $modelVideo->cover_size     = null;
                $modelVideo->cover_sizes    = null;
                $modelVideo->time           = time();
                $modelVideo->is_use         = 0;
                $modelVideo->hide           = 0;
                
                if ($modelVideo->save()) {
                    break;
                }

            } catch (\Exception $exception) {
                continue;
            }
        }

        return [
            'host'    => $config['scheme'] . '://' . $modelVideo->host,
            'file_id' => $modelVideo->file_id
        ];
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
     * @param object $uploadedFile
     * @param string $hash
     * @return array
     */
    private function fileMove(string $directory, $uploadedFile, string $hash)
    {
        global $config;

        $levelDefault = 4;

        $level = (isset($config['video']['level'])) ? $config['video']['level'] : $levelDefault;

        try {

            $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);

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

            $uploadedFile->moveTo($path);

            return [
                'status'     => true,
                'dir'        => $directory . '/' . $basename . '/',
                'name'       => $filename,
                'ext'        => $extension,
                'size'       => $uploadedFile->getSize(),
            ];

        } catch (\Exception $exception) {

            return [
                'status'    => false,
                'message'   => 'Exception!'
            ];

        }
    }
}