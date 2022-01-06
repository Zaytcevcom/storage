<?php

use api\entities\Photo;
use api\models\PhotoModel;

$i = 0;

$resize_status = (int)Photo::where('type', 2)->max('resize_status') + 1;

$PhotoModel = new PhotoModel();

while (true) {

    $count = 100;

    $models = Photo::where('resize_status', '<', $resize_status)
        ->where('type', 2)
        ->take($count)
        ->get();

    if (empty($models)) {
        break;
    }

    foreach ($models as $model) {

        echo PHP_EOL . $model->id;

        if (!isset($config['photo']['type'][$model->type])) {
            $model->resize_status = $resize_status;
            $model->save();
        }

        $typeInfo = $config['photo']['type'][$model->type];

        $path = ROOT_DIR . $model->dir . $model->name . '.' . $model->ext;
        $sizes = $PhotoModel->processing($path, $typeInfo);

        if (!is_array($sizes)) {
            $model->resize_status = $resize_status;
            $model->save();
        }

        $model->sizes = json_encode($sizes);
        $model->save();

        $old_sizes = (!empty($model->sizes)) ? json_decode($model->sizes, true) : [];

        foreach ($old_sizes as $size => $path) {
            unlink(ROOT_DIR . $path);
        }
        
    }

    break;
}
