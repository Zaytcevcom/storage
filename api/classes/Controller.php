<?php

declare(strict_types=1);

namespace api\classes;

use core\CoreController;

class Controller extends CoreController
{
    public function __destruct()
    {
        // Api statistics
        $this->statistics();
    }

    public function statistics()
    {
        if (!isset($this->args['model']) || !isset($this->args['action'])) {
            return;
        }

        $className = '\api\entities\Statistics';

        if (class_exists($className)) {
            $model = new $className();
            $model->model       = $this->args['model'];
            $model->action      = $this->args['action'];
            $model->duration    = microtime(true) - $this->scriptStart;
            $model->memory      = memory_get_usage() - $this->scriptMemory;
            $model->ip          = $this->getUserIP();
            $model->time        = time();
            $model->save();
        }
    }
}