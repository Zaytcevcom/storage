<?php

function getDirContents($dir, &$results = [])
{
    $files = scandir($dir);

    foreach ($files as $key => $value) {

        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);

        if(!is_dir($path)) {
            $results[] = $path;
        } else if($value != '.' && $value != '..') {
            getDirContents($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}

$path = ROOT_DIR . '/files';
$count = 0;

foreach (getDirContents($path) as $file_path) {

    $temp = explode('/', $file_path);
    $name = $temp[count($temp) - 1];

    $temp_ext = explode('.', $name);
    $ext = $temp_ext[count($temp_ext) - 1];

    if (!in_array($ext, ['png', 'jpg'])) {
        continue;
    }

    if (strpos($name, '_') === false) {
        continue;
    }

    echo PHP_EOL . 'DELETE: ' . $file_path . PHP_EOL;
    unlink($file_path);
    $count++;
    
}

echo PHP_EOL . PHP_EOL . 'DONE! Count files: ' .  $count . PHP_EOL;