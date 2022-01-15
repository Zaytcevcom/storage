<?php

use api\entities\Photo;

if (!$controller->freeSpaceCheck()) {
    return $controller->freeSpaceError();
}

// Params
$type   = $controller->getToStringOrNull('type');
$rotate = $controller->getToInt('rotate');

// Crop params
$params           = [];
$params['left']   = $controller->getToIntOrNull('left');
$params['top']    = $controller->getToIntOrNull('top');
$params['width']  = $controller->getToIntOrNull('width');
$params['height'] = $controller->getToIntOrNull('height');

$files = $controller->getUploadedFiles();
$requestParams = $controller->getParams();

// Upload file
$data = $model->upload($files, 'upload_file', $type, $rotate, $params, $requestParams);

if ($data === Photo::ERROR_REQUIRED_FIELDS) {
    return $controller->error(1, 'Missing a required field!');
}

if ($data === Photo::ERROR_TYPE) {
    return $controller->error(2, 'Error type!');
}

if ($data === Photo::ERROR_FAIL_UPLOAD) {
    return $controller->error(3, 'Fail file upload!');
}

if ($data === Photo::ERROR_FAIL_MOVE) {
    return $controller->error(4, 'Fail file move!');
}

if ($data === Photo::ERROR_MIN_SIZE) {
    return $controller->error(5, 'Error min file size!');
}

if ($data === Photo::ERROR_MAX_SIZE) {
    return $controller->error(6, 'Error max file size!');
}

if ($data === Photo::ERROR_ALLOW_TYPES) {
    return $controller->error(7, 'Error allow types!');
}

if ($data === Photo::ERROR_OPTIMIZE) {
    return $controller->error(8, 'Error optimize file!');
}

if ($data === Photo::ERROR_CROP) {
    return $controller->error(9, 'Error crop file!');
}

if ($data === Photo::ERROR_SAVE) {
    return $controller->error(10, 'Error save file!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/photo.upload",
 *  summary="Загрузка изображения",
 *  description="Загрузка изображения
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Error type
 *  3 - Fail file upload!
 *  4 - Fail file move!
 *  5 - Error min file size!
 *  6 - Error max file size!
 *  7 - Error allow types!
 *  8 - Error optimize file!
 *  9 - Error crop file!
 *  10 - Error save file!",
 *  tags={"Photo"},
 *  @OA\Parameter(
 *    name="rotate",
 *    description="Угол поворота в градусах",
 *    in="query",
 *    required=false,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="left",
 *    description="Отступ слева",
 *    in="query",
 *    required=false,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="top",
 *    description="Отступ сверху",
 *    in="query",
 *    required=false,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="width",
 *    description="Ширина",
 *    in="query",
 *    required=false,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="height",
 *    description="Высота",
 *    in="query",
 *    required=false,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */