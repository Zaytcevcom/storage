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

// Processing file by default settings
$data = $model->processingDefault($file_id, $secret_key);

if ($data === Photo::ERROR_SECRET_KEY) {
    return $controller->error(2, 'Invalid secret key!');
}

if ($data === Photo::ERROR_NOT_FOUND) {
    return $controller->error(3, 'File not found!');
}

return $controller->success($data);

/**
 * @OA\Get(
 *  path="/photo.processingDefault",
 *  summary="Обновление размеров изображений и миниатюр с настройками по умолчанию",
 *  description="Обновление размеров изображений и миниатюр с настройками по умолчанию
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Invalid secret key
 *  3 - File not found",
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