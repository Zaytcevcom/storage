<?php

declare(strict_types=1);

namespace api\models;

use api\entities\Photo;
use api\entities\Cover;
use api\classes\Model;
use api\classes\Image;
use getID3;

/**
 * PhotoModel
 */
class PhotoModel extends Model
{
    private $algo = 'sha1';
    private $mode = 0755; // mkdir mode
    private $quality = 90;

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

        $sizes = (!empty($model->sizes)) ? json_decode($model->sizes, true) : [];

        foreach ($sizes as $key => $value) {
            $sizes[$key] = $config['scheme'] . '://' . $model->host . $value;
        }

        $data = [
            'file_id'   => $model->file_id,
            'fields'    => (!empty($model->fields)) ? json_decode($model->fields, true) : null,
            'original'  => $config['scheme'] . '://' . $model->host . $model->dir . $model->name . '.' . $model->ext,
            'sizes'     => $sizes,
            'type'      => $model->type,
            'hash'      => $model->hash,
            'size'      => $model->size,
            'time'      => $model->time,
            'is_use'    => $model->is_use
        ];

        return $data;
    }

    /**
     * Upload file
     * @param array $files
     * @param string $field
     * @param string|null $type
     * @param int|null $rotate
     * @param array|null $crop
     * @param array $params
     * @return mixed
     */
    public function upload(array $files = [], string $field = 'upload_file', string $type = null, int $rotate = null, array $crop = null, array $params = [])
    {
        if (!isset($files[$field]) || !isset($_FILES[$field]['tmp_name'])) {
            return Photo::ERROR_FAIL_UPLOAD;
        }

        return $this->uploadByTempPath(
            $_FILES[$field]['tmp_name'],
            $type,
            $rotate,
            $crop,
            $params
        );
    }

    /**
     * Upload file by temp path
     * @param string|null $file_temp_path
     * @param string|null $type
     * @param int|null $rotate
     * @param array|null $crop
     * @param array $params
     * @return mixed
     */
    public function uploadByTempPath(string $file_temp_path = null, string $type = null, int $rotate = null, array $crop = null, array $params = [])
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
                if (!isset($params[$value])) {
                    return Photo::ERROR_REQUIRED_FIELDS;
                }

                $fields[$value] = $params[$value];
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

        while (true) {

            try {
                $modelPhoto = new Photo();
                $modelPhoto->file_id    = $this->uniqid();
                $modelPhoto->type       = $type;
                $modelPhoto->host       = $config['domain'];
                $modelPhoto->dir        = $result['dir'];
                $modelPhoto->name       = $result['name'];
                $modelPhoto->ext        = $ext;
                $modelPhoto->fields     = json_encode($fields);
                $modelPhoto->size       = $size;
                $modelPhoto->hash       = $hash;
                $modelPhoto->sizes      = json_encode([]);
                $modelPhoto->time       = time();
                $modelPhoto->is_use     = 0;
                $modelPhoto->hide       = 0;
                
                if ($modelPhoto->save()) {
                    break;
                }

            } catch (\Exception $exception) {
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

        $sizes = $this->processing($typeInfo, $path, $crop);

        if (!is_array($sizes)) {
            return $sizes;
        }

        $modelPhoto->size = filesize($path);
        $modelPhoto->sizes = json_encode($sizes);
        $modelPhoto->save();

        foreach ($sizes as $key => $value) {
            $sizes[$key] = $config['scheme'] . '://' . $modelPhoto->host . $value;
        }

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
        if (!isset($config[$media_type]) || !isset($config[$media_type]['type']) || !isset($config[$media_type]['type'][$type])) {
            return Photo::ERROR_TYPE;
        }

        $typeInfo = $config[$media_type]['type'][$type];

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

        while (true) {

            try {
                $modelCover = new Cover();
                $modelCover->file_id    = $this->uniqid();
                $modelCover->media_type = $media_type;
                $modelCover->type       = $type;
                $modelCover->host       = $config['domain'];
                $modelCover->dir        = $result['dir'];
                $modelCover->name       = $result['name'];
                $modelCover->ext        = $ext;
                $modelCover->size       = $size;
                $modelCover->hash       = $hash;
                $modelCover->sizes      = null;
                $modelCover->time       = time();
                $modelCover->hide       = 0;
                
                if ($modelCover->save()) {
                    break;
                }

            } catch (\Exception $exception) {
                continue;
            }
        }

        return [
            'file_id'   => $modelCover->file_id,
            'dir'       => $modelCover->dir,
            'name'      => $modelCover->name,
            'ext'       => $modelCover->ext,
            'size'      => (int)$modelCover->size,
            'sizes'     => !(empty($modelCover->sizes)) ? json_decode($modelCover->sizes, true) : null,
        ];
    }

    /**
     * Processing
     * @param array $typeInfo
     * @param string|null $path
     * @param array|null $crop
     * @return mixed
     */
    public function processing($typeInfo, $path, $crop = null)
    {
        // Сrop image
        if ($typeInfo['cropped']) {

            $is_auto = 0;

            $crop_auto = [
                'width'     => $typeInfo['sizes'][0][0],
                'height'    => $typeInfo['sizes'][0][1],
            ];

            if (!empty($crop)) {
                foreach (['left', 'top', 'width', 'height'] as $value) {
                    if (!isset($crop[$value]) || is_null($crop[$value])) {
                        $is_auto = 1;
                        break;
                    }
                }
            }

            $cropImage = $this->fileCrop($path, $this->quality, $is_auto ? $crop_auto : $crop, $is_auto);

            if (!$cropImage) {
                return Photo::ERROR_CROP;
            }
        }

        // Resize image
        foreach ($typeInfo['sizes'] as $size) {
            
            $img_path = ($typeInfo['cropped']) ? ROOT_DIR . $cropImage : $path;

            $resize = $this->fileResize($img_path, $size[0], $size[1], $this->quality);

            $sizes[$size[0]] = ($resize !== false) ? $resize : $path;
        }

        ksort($sizes);

        if (isset($cropImage)) {
            unlink(ROOT_DIR . $cropImage);
        }

        return $sizes;
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
     * Crop image
     * @param string|null $file_id
     * @param string|null $secret_key
     * @param array $params
     * @return int
     */
    public function crop(string $file_id = null, string $secret_key = null, array $params = [])
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
        if (!isset($config['photo']['type'][$model->type])) {
            return Photo::ERROR_TYPE;
        }

        $typeInfo = $config['photo']['type'][$model->type];

        // Check crop image
        if (!$typeInfo['cropped']) {
            return Photo::ERROR_CROP;
        }

        $old_sizes = ($model->sizes != '' && !is_null($model->sizes)) ? json_decode($model->sizes, true) : [];

        foreach ($old_sizes as $key => $value) {
            $old_sizes[$key] = ROOT_DIR . $value;
        }

        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;

        $is_auto = 0;

        $crop_auto = [
            'width'     => $typeInfo['sizes'][0][0],
            'height'    => $typeInfo['sizes'][0][1],
        ];

        foreach (['left', 'top', 'width', 'height'] as $value) {
            if (!isset($params[$value]) || is_null($params[$value])) {
                $is_auto = 1;
                break;
            }
        }

        // Сrop image
        $cropImage = $this->fileCrop($path, $this->quality, $is_auto ? $crop_auto : $params, $is_auto);

        if (!$cropImage) {
            return Photo::ERROR_CROP;
        }

        $sizes = [];

        // Resize image
        foreach ($typeInfo['sizes'] as $size) {
            $img_path = ROOT_DIR . $cropImage;
            $sizes[$size[0]] = $this->fileResize($img_path, $size[0], $size[1], $this->quality);
        }

        ksort($sizes);

        if (isset($cropImage)) {

        	$is_use = 0;

        	foreach ($sizes as $key => $value) {
        		if ($value == $cropImage) {
        			$is_use = 1;
        			break;
        		}
        	}

        	if (!$is_use) {
            	unlink(ROOT_DIR . $cropImage);
            }
        }

        $time = time();

        foreach ($sizes as $key => $value) {
            $sizes[$key] = $config['scheme'] . '://' . $model->host . $value . '?time=' . $time;
        }

        $sizes['original'] = $config['scheme'] . '://' . $model->host . $model->dir . $model->name . '.' . $model->ext;

        return $sizes;
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
    private function fileOptimize(string $path, int $quality, int $rotate)
    {
        $image = new Image($path);
        return $image->optimize($quality, $rotate);
    }

    /**
     * Crop image
     * @param string $path
     * @param int $quality
     * @param array $params
     * @param int $is_auto
     * @return mixed
     */
    private function fileCrop(string $path, int $quality, array $params = null, int $is_auto = 1)
    {
        if (empty($params)) {
            return false;
        }

        $image = new Image($path);
        $path = $image->crop($params, $quality, null, $is_auto);

        if ($path) {
            return Image::withoutRootDir(ROOT_DIR, $path);
        }

        return false;
    }

    /**
     * Resize image
     * @param string $path
     * @param int $width
     * @param int $height
     * @param int $quality
     * @return mixed
     */
    private function fileResize(string $path, int $width, int $height, int $quality)
    {
        $image = new Image($path);

        $resize_path = $image->resize($path, $width, $height, $quality);
        
        return ($resize_path) ? Image::withoutRootDir(ROOT_DIR, $resize_path) : Image::withoutRootDir(ROOT_DIR, $path);
    }
}