<?php

use api\entities\Video;

// Check require params
$needFields = $controller->checkRequireFields(['file_id', 'secret_key']);

if ($needFields) {
    return $controller->needFields($needFields);
}

// Params
$file_id    = $controller->getToStringOrNull('file_id');
$secret_key = $controller->getToStringOrNull('secret_key');

// Mark as use
$data = $model->markUse($file_id, $secret_key);

if ($data === Video::ERROR_SECRET_KEY) {
    return $controller->error(2, 'Invalid secret key!');
}

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/video.markUse",
 *  summary="Помечает файл как используемый",
 *  description="Помечает файл как используемый
 *  **Коды ошибок:**
 *  1 - Missing a required field
 *  2 - Invalid secret key",
 *  tags={"Video"},
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