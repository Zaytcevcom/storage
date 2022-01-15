<?php

use api\entities\Photo;

// Check require params
$needFields = $controller->checkRequireFields(['file_id', 'secret_key']);

if ($needFields) {
    return $controller->needFields($needFields);
}

// Params
$file_id    = $controller->getToStringOrNull('file_id');
$secret_key = $controller->getToStringOrNull('secret_key');

// Resize
$data = $model->resize($file_id, $secret_key);

if ($data === Photo::ERROR_SECRET_KEY) {
    return $controller->error(2, 'Invalid secret key!');
}

if ($data === Photo::ERROR_NOT_FOUND) {
    return $controller->error(3, 'File not found!');
}

if ($data === Photo::ERROR_OPTIMIZE) {
    return $controller->error(4, 'Error optimize file!');
}

if ($data === Photo::ERROR_CROP) {
    return $controller->error(5, 'Error crop file!');
}

if ($data === Photo::ERROR_SAVE) {
    return $controller->error(6, 'Error save file!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/photo.resize",
 *  summary="Генерирует и возвращает новые форматы изображений",
 *  description="Генерирует и возвращает новые форматы изображений
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Invalid secret key
 *  3 - File not found
 *  4 - Error optimize file!
 *  5 - Error crop file!
 *  6 - Error save file!",
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
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=403, description="Authorization required"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */