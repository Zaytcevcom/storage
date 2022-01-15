<?php

use api\entities\Audio;

if (!$controller->freeSpaceCheck()) {
    return $controller->freeSpaceError();
}

// Params
$type = $controller->getToStringOrNull('type');

$files = $controller->getUploadedFiles();
$requestParams = $controller->getParams();

// Upload file
$data = $model->upload($files, 'upload_file', $type, $requestParams);

if ($data === Audio::ERROR_REQUIRED_FIELDS) {
    return $controller->error(1, 'Missing a required field!');
}

if ($data === Audio::ERROR_TYPE) {
    return $controller->error(2, 'Error type!');
}

if ($data === Audio::ERROR_FAIL_UPLOAD) {
    return $controller->error(3, 'Fail file upload!');
}

if ($data === Audio::ERROR_FAIL_MOVE) {
    return $controller->error(4, 'Fail file move!');
}

if ($data === Audio::ERROR_MIN_SIZE) {
    return $controller->error(5, 'Error min file size!');
}

if ($data === Audio::ERROR_MAX_SIZE) {
    return $controller->error(6, 'Error max file size!');
}

if ($data === Audio::ERROR_ALLOW_TYPES) {
    return $controller->error(7, 'Error allow types!');
}

if ($data === Audio::ERROR_OPTIMIZE) {
    return $controller->error(8, 'Error optimize file!');
}

if ($data === Audio::ERROR_SAVE) {
    return $controller->error(9, 'Error save file!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/audio.upload",
 *  summary="Загрузка аудиозаписи",
 *  description="Загрузка аудиозаписи
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
 *  tags={"Audio"},
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */