<?php

declare(strict_types=1);

namespace api\models\protect;

use api\entities\Photo;
use api\entities\Cover;
use api\classes\Model;
use api\classes\Image;
use getID3;

/**
 * PhotoProtectModel
 */
class PhotoProtectModel extends Model
{
    private $algo       = 'sha1';
    private $mode       = 0755; // mkdir mode
    private $quality    = 90;
    
    /**
     * Upload file by temp path
     * @param string|null $file_temp_path
     * @param string|null $type
     * @param int|null $rotate
     * @param array|null $params
     * @param array $requestParams
     * @return mixed
     */
    public function uploadByTempPath(string $file_temp_path = null, string $type = null, int $rotate = null, array $params = null, array $requestParams = [])
    {
        global $config;

        if (empty($file_temp_path)) {
            return Photo::ERROR_FAIL_UPLOAD;
        }

        // Check type
        if (!isset($config['photo']['type'][$type])) {
            return Photo::ERROR_TYPE;
        }

        $typeInfo = $config['photo']['type'][$type];

        $fields = [];

        // Check fields
        if (isset($typeInfo['fields'])) {
            foreach ($typeInfo['fields'] as $value) {
                if (!isset($requestParams[$value])) {
                    return Photo::ERROR_REQUIRED_FIELDS;
                }

                $fields[$value] = $requestParams[$value];
            }
        }

        // Get file info
        $getID3 = new getID3();
        $imageInfo = $getID3->analyze($file_temp_path);

        if (!isset($imageInfo['filesize']) || !isset($imageInfo['fileformat'])) {
            return Photo::ERROR_FAIL_UPLOAD;
        }
        
        $size   = $imageInfo['filesize'];
        $ext    = $imageInfo['fileformat'];

        // Check min file size
        if ($size < $config['photo']['minSize']) {
            return Photo::ERROR_MIN_SIZE;
        }

        // Check max file size
        if ($config['photo']['maxSize'] < $size) {
            return Photo::ERROR_MAX_SIZE;
        }

        // Check file type
        if (!in_array($ext, $config['photo']['allowTypes'])) {
            return Photo::ERROR_ALLOW_TYPES;
        }

        $hash = hash_file($this->algo, $file_temp_path);

        $result = $this->fileMove($config['photo']['dir'], $file_temp_path, $hash);

        if (!isset($result['status']) || $result['status'] != true) {
            return Photo::ERROR_FAIL_MOVE;
        }

        $modelPhoto = null;

        $countAttempt = 50;

        while ($countAttempt > 0) {

            try {
                $modelPhoto                 = new Photo();
                $modelPhoto->file_id        = $this->uniqid();
                $modelPhoto->type           = $type;
                $modelPhoto->host           = $config['domain'];
                $modelPhoto->dir            = $result['dir'];
                $modelPhoto->name           = $result['name'];
                $modelPhoto->ext            = $ext;
                $modelPhoto->fields         = json_encode($fields);
                $modelPhoto->size           = $size;
                $modelPhoto->hash           = $hash;
                $modelPhoto->sizes          = null;
                $modelPhoto->crop_square    = null;
                $modelPhoto->crop_custom    = null;
                $modelPhoto->time           = time();
                $modelPhoto->is_use         = 0;
                $modelPhoto->hide           = 0;
                
                if ($modelPhoto->save()) {
                    break;
                }

            } catch (\Exception $exception) {

                $countAttempt--;

                if ($countAttempt <= 0) {
                    
                    // todo: delete files

                    return Photo::ERROR_SAVE;
                }

                continue;
            }
        }

        $path = ROOT_DIR . $result['dir'] . $result['name'] . '.' . $ext;

        // Optimize and orientation image
        if ($config['photo']['minSizeOptimize'] < $size) {
            if (!$this->fileOptimize($path, $this->quality, $rotate)) {
                return Photo::ERROR_OPTIMIZE;
            }
        }

        // Crop and resize by settings
        $processing = $this->processingModelByDefaultSettings($modelPhoto, $typeInfo, $params);

        // Save info
        $modelPhoto->sizes       = (isset($processing['sizes']) && $processing['sizes']) ? json_encode($processing['sizes']) : null;
        $modelPhoto->crop_square = (isset($processing['crop_square']) && $processing['crop_square']) ? json_encode($processing['crop_square']) : null;
        $modelPhoto->crop_custom = (isset($processing['crop_custom']) && $processing['crop_custom']) ? json_encode($processing['crop_custom']) : null;
        $modelPhoto->save();

        return [
            'host'    => $config['scheme'] . '://' . $modelPhoto->host,
            'file_id' => $modelPhoto->file_id
        ];
    }

    /**
     * Upload file cover by temp path
     * @param string|null $file_temp_path
     * @param string|null $media_type
     * @param string|null $type
     * @return mixed
     */
    public function uploadCoverByTempPath(string $file_temp_path = null, string $media_type = null, string $type = null)
    {
        global $config;

        if (empty($file_temp_path)) {
            return Photo::ERROR_FAIL_UPLOAD;
        }

        // Check type
        if (
            !isset($config[$media_type]) ||
            !isset($config[$media_type]['type']) ||
            !isset($config[$media_type]['type'][$type]) ||
            !isset($config[$media_type]['type'][$type]['cover']) ||
            !isset($config[$media_type]['type'][$type]['cover']['is_need']) ||
            $config[$media_type]['type'][$type]['cover']['is_need'] != 1
        ) {
            return Photo::ERROR_TYPE;
        }

        // Get file info
        $getID3 = new getID3();
        $imageInfo = $getID3->analyze($file_temp_path);

        if (!isset($imageInfo['filesize']) || !isset($imageInfo['fileformat'])) {
            return Photo::ERROR_FAIL_UPLOAD;
        }
        
        $size   = $imageInfo['filesize'];
        $ext    = $imageInfo['fileformat'];

        $hash = hash_file($this->algo, $file_temp_path);

        $result = $this->fileMove($config[$media_type]['dir_cover'], $file_temp_path, $hash);

        if (!isset($result['status']) || $result['status'] != true) {
            return Photo::ERROR_FAIL_MOVE;
        }

        $modelCover = null;

        $countAttempt = 50;

        while ($countAttempt > 0) {

            try {
                $modelCover                 = new Cover();
                $modelCover->file_id        = $this->uniqid();
                $modelCover->media_type     = $media_type;
                $modelCover->type           = $type;
                $modelCover->host           = $config['domain'];
                $modelCover->dir            = $result['dir'];
                $modelCover->name           = $result['name'];
                $modelCover->ext            = $ext;
                $modelCover->size           = $size;
                $modelCover->hash           = $hash;
                $modelCover->sizes          = null;
                $modelCover->crop_square    = null;
                $modelCover->crop_custom    = null;
                $modelCover->time           = time();
                $modelCover->hide           = 0;
                
                if ($modelCover->save()) {
                    break;
                }

            } catch (\Exception $exception) {

                $countAttempt--;

                if ($countAttempt <= 0) {
                    
                    // todo: delete files

                    return Photo::ERROR_SAVE;
                }

                continue;
            }
        }

        // Crop and resize by settings
        $processing = $this->processingModelCoverByDefaultSettings($modelCover, $config[$media_type]['type'][$type]['cover']);

        // Save info
        $modelCover->sizes       = (isset($processing['sizes']) && $processing['sizes']) ? json_encode($processing['sizes']) : null;
        $modelCover->crop_square = (isset($processing['crop_square']) && $processing['crop_square']) ? json_encode($processing['crop_square']) : null;
        $modelCover->crop_custom = (isset($processing['crop_custom']) && $processing['crop_custom']) ? json_encode($processing['crop_custom']) : null;
        $modelCover->save();

        return Cover::getInfoProtected([$modelCover])[0];
    }

    
    // MARK: - protected file methods

    /**
     * Move uploaded file file
     * @param string $directory
     * @param string $file_temp_path
     * @param string $hash
     * @return array
     */
    protected function fileMove(string $directory, $file_temp_path, string $hash)
    {
        global $config;

        $levelDefault = 4;

        $level = (isset($config['photo']['level'])) ? $config['photo']['level'] : $levelDefault;

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

    /**
     * Optimize image
     * @param string $path
     * @param int $quality
     * @param int $rotate
     * @return mixed
     */
    protected function fileOptimize(string $path, int $quality, int $rotate)
    {
        if (strpos($path, ROOT_DIR) === false) {
            $path = ROOT_DIR . $path;
        }

        $image = new Image($path);
        return $image->optimize($quality, $rotate);
    }

    /**
     * Resize image
     * @param string $path
     * @param int $width
     * @param int|null $quality
     * @param string|null $prefix
     * @return mixed
     */
    protected function fileResize(string $path, int $width, int $quality = null, string $prefix = null)
    {
        if (strpos($path, ROOT_DIR) === false) {
            $path = ROOT_DIR . $path;
        }

        $image = new Image($path);
        $resize_path = $image->resize($width, $quality, $prefix);
        
        return Image::withoutRootDir(ROOT_DIR, ($resize_path) ? $resize_path : $path);
    }

    /**
     * Crop image
     * @param string|null $path
     * @param array|null $default_params
     * @param array|null $params
     * @param int|null $quality
     * @return mixed
     */
    protected function fileCrop(string $path = null, array $default_params = null, array $params = null, int $quality = null)
    {
        if (empty($path)) {
            return false;
        }

        if (strpos($path, ROOT_DIR) === false) {
            $path = ROOT_DIR . $path;
        }

        $image  = new Image($path);
        $path   = $image->crop($default_params, $params, $quality);

        if ($path) {
            return Image::withoutRootDir(ROOT_DIR, $path);
        }

        return false;
    }

    /**
     * Crop square image
     * @param string|null $path
     * @param array|null $params
     * @param int|null $quality
     * @return mixed
     */
    protected function fileCropSquare(string $path = null, array $params = null, int $quality = null)
    {
        if (empty($path)) {
            return false;
        }

        if (strpos($path, ROOT_DIR) === false) {
            $path = ROOT_DIR . $path;
        }

        $image  = new Image($path);
        $path   = $image->cropSquare($params, $quality);

        if ($path) {
            return Image::withoutRootDir(ROOT_DIR, $path);
        }

        return false;
    }

    /**
     * Delete old files
     * @param array|null $files
     * @return int
     */
    protected function fileDeleteOld(array $files = null)
    {
        if (empty($files)) {
            return 1;
        }

        foreach ($files as $path) {
            
            if (strpos($path, ROOT_DIR) === false) {
                $path = ROOT_DIR . $path;
            }

            if (file_exists($path)) {
                unlink($path);
            }
        }

        return 1;
    }

    /**
     * Processing file cover by settings
     * @param Cover|null $model
     * @param array|null $settings
     * @param array|null $params
     * @return mixed
     */
    public function processingModelCoverByDefaultSettings(Cover $model, array $settings = null, array $params = null)
    {
        // Path to original file
        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;


        // [Resize] Get old files
        $old_files_resize = (!empty($model->cover_sizes)) ? json_decode($model->cover_sizes, true) : null;

        // [Resize] Resize
        $sizesResult = $this->fileResizeBySettings($settings, $path, $this->quality, $old_files_resize);

        
        // [Crop square] Get old crop square files
        $old_files_square = (!empty($model->cover_crop_square)) ? json_decode($model->cover_crop_square, true) : null;

        // [Crop square] Crop and resize
        $cropSquareResult = $this->fileCropBySettings($settings, 0, $path, $params, $this->quality, $old_files_square);


        // [Crop custom] Get old crop custom files
        $old_files_custom = (!empty($model->cover_crop_custom)) ? json_decode($model->cover_crop_custom, true) : null;

        // [Crop custom] Crop and resize
        $cropCustomResult = $this->fileCropBySettings($settings, 1, $path, $params, $this->quality, $old_files_custom);

        return [
            'sizes'         => $sizesResult,
            'crop_square'   => $cropSquareResult,
            'crop_custom'   => $cropCustomResult
        ];
    }

    /**
     * Processing file by settings
     * @param Photo|null $model
     * @param array|null $settings
     * @param array|null $params
     * @return mixed
     */
    protected function processingModelByDefaultSettings(Photo $model, array $settings = null, array $params = null)
    {
        // Path to original file
        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;


        // [Resize] Get old files
        $old_files_resize = (!empty($model->sizes)) ? json_decode($model->sizes, true) : null;

        // [Resize] Resize
        $sizesResult = $this->fileResizeBySettings($settings, $path, $this->quality, $old_files_resize);
        

        // [Crop square] Get old crop square files
        $old_files_square = (!empty($model->crop_square)) ? json_decode($model->crop_square, true) : null;

        // [Crop square] Crop and resize
        $cropSquareResult = $this->fileCropBySettings($settings, 0, $path, $params, $this->quality, $old_files_square);


        // [Crop custom] Get old crop custom files
        $old_files_custom = (!empty($model->crop_custom)) ? json_decode($model->crop_custom, true) : null;

        // [Crop custom] Crop and resize
        $cropCustomResult = $this->fileCropBySettings($settings, 1, $path, $params, $this->quality, $old_files_custom);

        return [
            'sizes'         => $sizesResult,
            'crop_square'   => $cropSquareResult,
            'crop_custom'   => $cropCustomResult
        ];
    }

    /**
     * Crop image by settings
     * @param array|null $settings
     * @param string|null $path
     * @param array|null $params
     * @param int|null $quality
     * @param array|null $files
     * @return mixed
     */
    protected function fileCropBySettings(array $settings = null, $is_custom = 0, string $path = null, array $params = null, int $quality = null, array $files = null)
    {
        $field = ($is_custom) ? 'crop_custom' : 'crop_square';
        
        if (empty($path)) {
            return false;
        }

        if (
            empty($settings) ||
            !isset($settings[$field]) ||
            !isset($settings[$field]['is_need']) ||
            $settings[$field]['is_need'] != 1
        ) {
            // Delete old files
            $this->fileDeleteOld($files);

            return false;
        }

        if (
            $is_custom &&
            (
                !isset($settings[$field]['default']) ||
                !isset($settings[$field]['default']['width']) ||
                !isset($settings[$field]['default']['height'])
            )
        ) {
            // Delete old files
            $this->fileDeleteOld($files);

            return false;
        }
        
        // Path to new crop file
        if ($is_custom) {
            
            $default_params = [
                'width'     => $settings[$field]['default']['width'],
                'height'    => $settings[$field]['default']['height'],
            ];

            $path = $this->fileCrop($path, $default_params, $params, $quality);

        } else {

            $path = $this->fileCropSquare($path, $params, $quality);

        }

        if (!$path) {
            return false;
        }

        $result = [];

        // Resize
        if (isset($settings[$field])) {

            $prefix = ($is_custom) ? 'c' : 's';

            foreach ($settings[$field]['resize'] as $width) {
                $result[$width] = $this->fileResize($path, $width, $quality, $prefix);
            }
        }

        // Delete max crop file
        $this->fileDeleteOld([$path]);

        // Delete old files
        if (!empty($files)) {
            foreach ($files as $item) {
                if (!in_array($item, $result)) {
                    $this->fileDeleteOld([$item]);
                }
            }
        }
        
        ksort($result);

        return $result;
    }

    /**
     * Resize image by settings
     * @param array|null $settings
     * @param string|null $path
     * @param int|null $quality
     * @param array|null $files
     * @return mixed
     */
    protected function fileResizeBySettings(array $settings = null, string $path = null, int $quality = null, array $files = null)
    {
        if (empty($path)) {
            return false;
        }

        if (
            empty($settings) ||
            !isset($settings['sizes']) ||
            !isset($settings['sizes']['is_need']) ||
            $settings['sizes']['is_need'] != 1
        ) {
            // Delete old files
            $this->fileDeleteOld($files);

            return false;
        }
        
        $result = [];

        // Resize
        if (isset($settings['sizes'])) {
            foreach ($settings['sizes']['resize'] as $width) {
                $result[$width] = $this->fileResize($path, $width, $quality);
            }
        }
        
        ksort($result);

        return $result;
    }
}