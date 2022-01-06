<?php

declare(strict_types=1);

namespace core;

class CoreModel
{
    protected $redis = null;

    const MAX_COUNT = 1000;

    public function __construct()
    {
        // Redis
        $this->connectRedis();
    }

    /* *** Check count and offset ******************************* */

    public function checkCount(int $count = null, int $max_value = null) : int
    {
        $count = (int)$count;

        if ($count <= 0) {
            $count = $max_value;
        }

        if ($max_value !== null && $count > $max_value) {
            $count = $max_value;
        }

        return (int)$count;
    }

    public function checkOffset(int $count = null) : int
    {
        $count = (int)$count;

        if ($count < 0) {
            $count = 0;
        }

        return (int)$count;
    }

    public function toLower($str)
    {
        return mb_strtolower($str, 'UTF-8');
    }

    /* *** Unique *********************************************** */

    /**
     * Return unique string
     */
    public function uniqid(string $str = '')
    {
        return time() . '.' . md5($str . 'z' . uniqid());
    }


    /* *** Find ************************************************* */
    
    /**
     * Find in items
     * @param array $items
     * @param int $id
     * @return int|bool
     */
    public function findIdInItems(array $items, int $id)
    {
        return self::findKeyInItems($items, 'id', $id);
    }

    /**
     * Find key in items
     * @param array $items
     * @param string $key
     * @param int $id
     * @return int|bool
     */
    public static function findKeyInItems(array $items, string $key, int $id)
    {
        foreach ($items as $key => $item) {
            if ($item[$key] == $id) {
                return $key;
            }
        }

        return false;
    }


    /* *** Cache ************************************************ */

    public function connectRedis()
    {
        global $config;

        if (!$config['redis']['enabled']) {
            $this->redis = null;
            return;
        }

        try {
            
            $this->redis = new \Predis\Client([
                'scheme' => $config['redis']['scheme'],
                'host'   => $config['redis']['host'],
                'port'   => $config['redis']['port'],
            ]);

            $this->redis->auth($config['redis']['password']);

        } catch (\Exception $e) {
            $this->redis = null;
        }
    }

    public function queryHash($query)
    {
        $sql = str_replace(['?'], ['\'%s\''], $query->toSql());
        $sql = vsprintf($sql, $query->getBindings());
        return md5($sql);
    }

    public function getFromCache($query)
    {
        if (empty($this->redis)) {
            return null;
        }

        $key = $this->queryHash($query);

        $data = $this->redis->get($key);

        if ($data === null || empty($data)) {
            return null;
        }

        return json_decode($data, true);
    }

    public function setToCache($query, $value)
    {
        if (empty($this->redis)) {
            return;
        }

        $key = $this->queryHash($query);

        $this->redis->set($key, json_encode($value));
    }

    public function getCountFromCache($query)
    {
        if (empty($this->redis)) {
            return null;
        }

        $key = $this->queryHash($query);

        $data = $this->redis->get('count:' . $key);

        if ($data === null || empty($data)) {
            return null;
        }

        return (int)$data;
    }

    public function setCountToCache($query, $count)
    {
        if (empty($this->redis)) {
            return;
        }

        $key = $this->queryHash($query);

        $this->redis->set('count:' . $key, $count);
    }
}