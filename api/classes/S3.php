<?php

declare(strict_types=1);

namespace api\classes;

use Aws\Sdk;
use Exception;

class S3
{
    private $args = [];
    private $s3Client;
    private $bucket = '';

    public function __construct($args)
    {
        $this->args = [
            'credentials' => [
                'key'       => $args['key'],
                'secret'    => $args['secret'],
            ],
            'region'    => $args['region'],
            'endpoint'  => $args['endpoint'],
            'version'   => $args['version'],
        ];

        $this->bucket = $args['bucket'];

        $sdk = new Sdk($this->args);
        $this->s3Client = $sdk->createS3();
    }

    public function getBuckets()
    {
        $buckets = $this->s3Client->listBuckets();

        $result = [];

        foreach ($buckets as $bucket) {
            $result[] = $bucket;
        }

        return $result;
    }

    public function putObject($file_name, $path_source_file, $params = [])
    {
        $file_name = ltrim($file_name, '/');

        if (!isset($params['ACL'])) {
            $params['ACL'] = 'public-read';
        }

        try {

            $result = $this->s3Client
                ->putObject(array_merge([
                    'Key'           => $file_name,
                    'Body'          => $this->fileContent($path_source_file),
                    'Bucket'        => (string)$this->bucket
                ], $params))
                ->toArray();

            if (isset($result['ObjectURL'])) {
                return parse_url($result['ObjectURL']);
            }

        } catch (Exception $e) {
            return null;
        }        
    }

    private function fileContent($path)
    {
        $result = '';

        if (isset($path)) {
            if ($stream = fopen($path, 'r')) {
                while (!feof($stream)) {
                    $result .= fread($stream, 1024);
                }
                fclose($stream);
            }
        }

        return $result;
    }
}