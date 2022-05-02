<?php

declare(strict_types=1);

namespace api\models\protect;

use api\entities\Audio;
use api\classes\Model;
use getID3;

/**
 * AudioProtectModel
 */
class AudioProtectModel extends Model
{
    private $algo = 'sha1';
    private $mode = 0755; // mkdir mode

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
            'host'    => $config['scheme'] . '://' . $config['domain'],
            'file_id' => $modelAudio->file_id
        ];
    }


    // MARK: - protected file methods

    /**
     * Create cover
     * @param string|null $path
     * @param string|null $type
     * @return array
     */
    protected function createCover($path = null, $type = null)
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