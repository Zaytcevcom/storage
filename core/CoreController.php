<?php

declare(strict_types=1);

namespace core;

use Illuminate\Database\Capsule\Manager as DB;

class CoreController
{
    protected $request  = null;
    protected $response = null;
    protected $args     = null;
    protected $params   = [];

    private $scriptStart    = null;
    private $scriptMemory   = null;

    public function __construct($request, $response, $args)
    {
        $this->scriptStart  = microtime(true);
        $this->scriptMemory = memory_get_usage();

        $this->request  = $request;
        $this->response = $response;
        $this->args     = $args;
        
        // Set params
        $this->setParams();
    }

    public function __destruct()
    {
        // Api statistics
        $this->statistics();
    }

    protected function setParams()
    {
        if (!empty($this->request->getQueryParams())) {
            foreach($this->request->getQueryParams() as $key => $value) {
                $this->params[$key] = $value;
            }
        }

        if (!empty($this->request->getParsedBody())) {
            foreach($this->request->getParsedBody() as $key => $value) {
                $this->params[$key] = $value;
            }
        }
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getUploadedFiles()
    {
        return $this->request->getUploadedFiles();
    }

    public function statistics()
    {
        if (!isset($this->args['controller']) || !isset($this->args['action'])) {
            return;
        }

        $className = '\api\models\Statistics';

        if (class_exists($className)) {
            $model = new $className();
            $model->controller  = $this->args['controller'];
            $model->action      = $this->args['action'];
            $model->duration    = microtime(true) - $this->scriptStart;
            $model->memory      = memory_get_usage() - $this->scriptMemory;
            $model->ip          = $this->request->getAttribute('ip_address');
            $model->time        = time();
            $model->save();
        }
    }

    /* *** Check request params ********************************* */

    public function checkRequireFields($fields)
    {
        $data = [];

        $params = $this->getParams();

        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                $data[] = $field;
            }    
        }

        return $data;
    }

    public function needFields($fields)
    {
        $message = 'Missing a required field: `' . $fields[0] . '`';

        return $this->error(1, $message);
    }  

    public function getToStringOrNull(string $param)
    {
        $params = $this->getParams();

        if (isset($params[$param])) {
            return (string)trim($params[$param]);
        }

        return null;
    }

    public function getToString(string $param) : string
    {
        $data = $this->getToStringOrNull($param);

        if ($data === null) {
            return (string)'';
        }

        return (string)$data;
    }

    public function getToIntOrNull(string $param)
    {
        $params = $this->getParams();

        if (isset($params[$param])) {
            return (int)$params[$param];
        }

        return null;
    }

    public function getToInt(string $param) : int
    {
        $data = $this->getToIntOrNull($param);

        if ($data === null) {
            return (int)0;
        }

        return (int)$data;
    }

    public function getToArrayInt(string $param) : array
    {
        $data = [];

        $params = $this->getParams();

        if (isset($params[$param])) {

            $arr = explode(',', trim($params[$param]));

            foreach ($arr as $value) {
                $data[] = (int)$value;
            }
        }

        return array_unique($data);
    }

    public function getToArrayString(string $param) : array
    {
        $data = [];

        $params = $this->getParams();

        if (isset($params[$param])) {

            $arr = explode(',', trim($params[$param]));

            foreach ($arr as $value) {

                $value = (string)trim($value);

                if ($value == '') {
                    continue;
                }
                
                $data[] = $value;
            }
        }

        return array_unique($data);
    }


    /* *** Response ********************************************* */

    /**
     * @param mixed $data
     * @param int $status
     * @param string $reason
     */
    public function response($data = null, int $status = 200, string $reason = '')
    {
        $response = $this->response;

        if ($data !== null) {

            $response
                ->getBody()
                ->write(json_encode($data));
        }
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Origin, Accept, Authorization')
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status, $reason);
    }

    /**
     * @param int $code
     * @param string $message
     */
    public function error(int $code, string $message = '')
    {
        $data = [
            'code'    => $code,
            'message' => $message
        ];

        return $this->failure($data, 405, $data['message']);
    }

    /**
     * Return success response
     */
    public function success($data)
    {
        return $this->response(['response' => $data], 200);
    }

    /**
     * Return error response
     */
    public function failure($data = null, int $status = 405, string $reason = '')
    {
        return $this->response(['error' => $data], $status, $reason);
    }

    /**
     * Return response - Method not allowed
     */
    public function methodNotAllowed()
    {
        $data = [
            'code'    => 405,
            'message' => 'Method not allowed'
        ];

        return $this->failure($data, 405, $data['message']);
    }

    /**
     * Return response - Method Authorization required
     */
    public function methodAuthorizationRequired()
    {
        $data = [
            'code'    => 403,
            'message' => 'Method Authorization required'
        ];

        return $this->failure($data, 403, $data['message']);
    }

    /* *** Space ************************************************ */

    public function freeSpaceCheck()
    {
        global $config;

        $space = disk_free_space(ROOT_DIR);

        if (!$space || ($space - $config['free_space']) < 0) {
            return 0;
        }

        return 1;
    }

    public function freeSpaceError()
    {
        $data = [
            'code'    => 405,
            'message' => 'No free space'
        ];

        return $this->failure($data, 405, $data['message']);
    }
}