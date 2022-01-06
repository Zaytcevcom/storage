<?php

declare(strict_types=1);

namespace api\classes;

class Image
{
    private $dir;
    private $filename;
    private $ext;
    private $path;
    private $type;

    function __construct($path)
    {
        $this->path = $path;

        $basename = basename($path);

        $this->ext      = end(explode('.', $basename));
        $this->filename = str_replace('.' . $this->ext, '', $basename);
        $this->dir      = str_replace($basename, '', $path);

        $info = getimagesize($this->path);

        if (!$info) {
            return false;
        }

        $this->type = $info[2];
    }

    public static function withoutRootDir($dir, $path)
    {
        return str_replace($dir, '', $path);
    }

    public static function createSource(string $path)
    {
        $info = getimagesize($path);

        if (!$info) {
            return false;
        }

        $type = $info[2];

        if ($type == IMAGETYPE_JPEG) {
            return @imagecreatefromjpeg($path);
        } else if ($type == IMAGETYPE_PNG) {
            return @imagecreatefrompng($path);
        } else if ($type == IMAGETYPE_GIF) {
            return @imagecreatefromgif($path);
        }

        return false;
    }

    public static function getInfo(string $path)
    {
        $info = getimagesize($path);

        if (!$info) {
            return false;
        }

        return [
            'width'  => $info[0],
            'height' => $info[1],
            'type'   => $info[2]
        ];
    }

    public function optimize(int $quality = 90, int $rotate = 0)
    {
        try {

            if (!file_exists($this->path)) {
                return false;
            }

            $source = self::createSource($this->path);

            if (!$source) {
                return false;
            }

            // Check image orientation
            if ($rotate == 0) {
                $rotate = $this->getRotate();
            }

            // Rotate image
            if ($rotate != 0) {
                $source = imagerotate($source, $rotate, 0);
            }

            if ($this->type == IMAGETYPE_JPEG) {
            
                imagejpeg($source, $this->path, $quality);

            } else if ($this->type == IMAGETYPE_PNG) {

                imagealphablending($source, false);
                imagesavealpha($source, true);
                imagepng($source, $this->path);

            } else if ($this->type == IMAGETYPE_GIF) {
                
                imagegif($source, $this->path);

            }

            imagedestroy($source);

            return true;

        } catch (\Exception $exception) {

            return false;

        } 
    }

    public function getRotate()
    {
        $exif = exif_read_data($this->path);

        $rotate = 0;

        if (!empty($exif['Orientation'])) {

            switch ($exif['Orientation']) {
                case 3:
                    $rotate = 180;
                    break;

                case 6:
                    $rotate = -90;
                    break;

                case 8:
                    $rotate = 90;
                    break;
            }
        }

        return $rotate;
    }

    public function crop(array $params, int $quality = 90, string $new_filename = null, int $is_auto = 1)
    {
        try {

            $source = self::createSource($this->path);

            if (!$source) {
                return false;
            }

            $sourceInfo = self::getInfo($this->path);

            if (isset($params['width']) && isset($params['height']) && !empty($params['width']) && !empty($params['height'])) {

	            $delta_height = $sourceInfo['height'] / $params['height'];
	            $possible_width = (int)($delta_height * $params['width']);

	            if ($possible_width <= $sourceInfo['width']) {

	            	$max_width 	= $possible_width;
	            	$max_height = $sourceInfo['height'];

	            } else {

	            	$delta_width = $sourceInfo['width'] / $params['width'];

	            	$max_width 	= $sourceInfo['width'];
	            	$max_height = (int)($delta_width * $params['height']);

	            }

	        } else {

	        	$max_width = $max_height = ($sourceInfo['width'] < $sourceInfo['height']) ? $sourceInfo['width'] : $sourceInfo['height'];

	        }


            if ($is_auto) {

            	$left   = (int)(($sourceInfo['width'] - $max_width) / 2);
                $top    = (int)(($sourceInfo['height'] - $max_height) / 2);
                $width  = $max_width;
                $height = $max_height;

            } else {

                $left   = (int)$params['left'];
                $top    = (int)$params['top'];
                $width  = ($max_width > $params['width']) ? $params['width'] : $max_width;
                $height = ($max_height > $params['height']) ? $params['height'] : $max_height;

            }

            if ($new_filename == '' || is_null($new_filename)) {
                $new_filename = $this->filename . '_' . $width . 'x' . $height . '.' . $this->ext;
            }

            $image = imagecreatetruecolor($width, $height);

            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, $left, $top, $width, $height, $width, $height);

            $path = $this->dir . $new_filename;

            if ($this->type == IMAGETYPE_JPEG) {
            
                imagejpeg($image, $path, $quality);

            } else if ($this->type == IMAGETYPE_PNG) {

                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path);

            } else if ($this->type == IMAGETYPE_GIF) {
                
                imagegif($image, $path);

            } else {

                return false;

            }

            imagedestroy($image);

            return $path;

        } catch (\Exception $exception) {

            return false;

        }
    }

    public function resize(string $path, int $width, int $height, int $quality = 90, string $new_filename = null)
    {
        try {

            if ($new_filename == '' || is_null($new_filename)) {
                $arr = explode('_', $this->filename);
                $new_filename = $arr[0] . '_' . $width . 'x' . $height . '.' . $this->ext;
            }

            $source = self::createSource($path);

            if (!$source) {
                return false;
            }

            $info = self::getInfo($path);

            if (!$info) {
                return false;
            }

            $image = imagecreatetruecolor($width, $height);

            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info['width'], $info['height']);

            $path = $this->dir . $new_filename;

            if ($info['type'] == IMAGETYPE_JPEG) {
            
                imagejpeg($image, $path, $quality);

            } else if ($info['type'] == IMAGETYPE_PNG) {

                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path);

            } else if ($info['type'] == IMAGETYPE_GIF) {
                
                imagegif($image, $path);

            } else {

                return false;

            }

            imagedestroy($image);

            return $path;
            
        } catch (\Exception $exception) {

            return false;

        }
    }
}