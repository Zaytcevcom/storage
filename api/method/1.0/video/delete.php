<?php

// Delete not use files
$data = $model->delete();

return $controller->success($data);

/**
 * @OA\Post(
 *  path="/video.delete",
 *  summary="Удаление неиспользуемых файлов",
 *  tags={"Video"},
 *  @OA\Response(response=200, description="Successful operation"),
 *  @OA\Response(response=403, description="Authorization required"),
 *  @OA\Response(response=405, description="Invalid input")
 * )
 */