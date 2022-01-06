<?php

use api\entities\Photo;

// Check require params
$needFields = $controller->checkRequireFields(['file_id', 'secret_key', 'left', 'top', 'width', 'height']);

if ($needFields) {
    return $controller->needFields($needFields);
}

// Params
$file_id    = $controller->getToStringOrNull('file_id');
$secret_key = $controller->getToStringOrNull('secret_key');

// Crop params
$params['left']   = $controller->getToIntOrNull('left');
$params['top']    = $controller->getToIntOrNull('top');
$params['width']  = $controller->getToIntOrNull('width');
$params['height'] = $controller->getToIntOrNull('height');

// Mark as use
$data = $model->crop($file_id, $secret_key, $params);

if ($data === Photo::ERROR_SECRET_KEY) {
    return $controller->error(2, 'Invalid secret key!');
}

if ($data === Photo::ERROR_NOT_FOUND) {
    return $controller->error(3, 'File not found!');
}

if ($data === Photo::ERROR_TYPE) {
    return $controller->error(4, 'Error type!');
}

if ($data === Photo::ERROR_CROP) {
    return $controller->error(5, 'Error crop file!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/photo.crop",
 *  summary="Создает новую миниатюру изображения",
 *  description="Создает новую миниатюру изображения
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Invalid secret key
 *  3 - File not found
 *  4 - File type!
 *  5 - File crop file!",
 *  tags={"Photo"},
 *  @OA\Parameter(
 *    name="file_id",
 *    description="Идентификатор файла",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="string",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="secret_key",
 *    description="Секретный ключ",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="string",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="left",
 *    description="Отступ слева",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="top",
 *    description="Отступ сверху",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="width",
 *    description="Ширина",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Parameter(
 *    name="height",
 *    description="Высота",
 *    in="query",
 *    required=true,
 *    @OA\Schema(
 *      type="integer",
 *      format="int64",
 *    )
 *  ),
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=403, description="Authorization required"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */