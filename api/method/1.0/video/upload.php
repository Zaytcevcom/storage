<?php

use api\entities\Video;

if (!$controller->freeSpaceCheck()) {
    return $controller->freeSpaceError();
}

// Params
$type = $controller->getToStringOrNull('type');

$files = $controller->getUploadedFiles();
$requestParams = $controller->getParams();

// Upload file
$data = $model->upload($files, 'upload_file', $type, $requestParams);

if ($data === Video::ERROR_REQUIRED_FIELDS) {
    return $controller->error(1, 'Missing a required field!');
}

if ($data === Video::ERROR_TYPE) {
    return $controller->error(2, 'Error type!');
}

if ($data === Video::ERROR_FAIL_UPLOAD) {
    return $controller->error(3, 'Fail file upload!');
}

if ($data === Video::ERROR_FAIL_MOVE) {
    return $controller->error(4, 'Fail file move!');
}

if ($data === Video::ERROR_MIN_SIZE) {
    return $controller->error(5, 'Error min file size!');
}

if ($data === Video::ERROR_MAX_SIZE) {
    return $controller->error(6, 'Error max file size!');
}

if ($data === Video::ERROR_ALLOW_TYPES) {
    return $controller->error(7, 'Error allow types!');
}

if ($data === Video::ERROR_OPTIMIZE) {
    return $controller->error(8, 'Error optimize file!');
}

if ($data === Video::ERROR_SAVE) {
    return $controller->error(9, 'Error save file!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/video.upload",
 *  summary="Загрузка видеозаписи",
 *  description="Загрузка видеозаписи
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Error type
 *  3 - Fail file upload!
 *  4 - Fail file move!
 *  5 - Error min file size!
 *  6 - Error max file size!
 *  7 - Error allow types!
 *  8 - Error optimize file!
 *  9 - Error save file!",
 *  tags={"Video"},
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */