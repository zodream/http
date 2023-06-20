<?php
declare(strict_types=1);
namespace Zodream\Http;


class HttpBatch {

    /**
     * @var resource
     */
    protected $handle;

    protected array $https = [];

    public function __construct(array $options = []) {
        $this->handle = curl_multi_init();
        $this->setOption($options);
    }

    public function setOption(array $options = []) {
        foreach ($options as $option => $value) {
            curl_multi_setopt($this->handle, $option, $value);
        }
        return $this;
    }

    public function addHttp(mixed ...$https) {
        foreach ($https as $http) {
            if ($http instanceof Http) {
                $this->addHandle($http->getHandle());
                $this->https[] = $http->setIsMulti(true);
                continue;
            }
            if (is_resource($http)) {
                $this->addHandle($http);
                $this->https[] = $http;
                continue;
            }
            if (!is_callable($http)) {
                continue;
            }
            call_user_func($http, $this);
        }
        return $this;
    }

    /**
     * @param resource|Http $curl
     * @return bool
     */
    protected function addHandle(mixed $curl) {
        return curl_multi_add_handle($this->handle,
                $curl instanceof Http ? $curl->getHandle() : $curl) === CURLM_OK;
    }

    /**
     * @param resource|Http $curl
     * @return int
     */
    public function removeHandle(mixed $curl) {
        return curl_multi_remove_handle($this->handle,
            $curl instanceof Http ? $curl->getHandle() : $curl);
    }

    /**
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function execute(array $options = []) {
        if (count($this->https) == 0) {
            return false;
        }
        // 应用参数
        foreach ($this->https as $http) {
            if ($http instanceof Http) {
                $http->applyMethod();
            }
        }
        //Default select timeout
        $selectTimeout = isset($options['selectTimeout']) ? $options['selectTimeout'] : 1.0;
        //The first curl_multi_select often times out no matter what, but is usually required for fast transfers
        $timeout = 0.001;
        $active = false;
        do {
            while (($mrc = curl_multi_exec($this->handle, $active)) === CURLM_CALL_MULTI_PERFORM) {
                ;
            }
            if ($active && curl_multi_select($this->handle, $timeout) === -1) {
                // Perform a usleep if a select returns -1: https://bugs.php.net/bug.php?id=61141
                usleep(150);
            }
            $timeout = $selectTimeout;
        } while ($active);
        //Clean to re-exec && check success
        $success = true;
        foreach ($this->https as $http) {
            if (!$http instanceof Http) {
                continue;
            }
            $http->executeWithBatch();
            if (!empty($http->getResponseHeader('errorNo'))) {
                $success = false;
            }
            $this->removeHandle($http);
        }
        return $success;
    }

    /**
     * 返回处理过的数组
     * @param callable $cb
     * @return array
     */
    public function map(callable $cb) {
        return array_map($cb, $this->https);
    }

    /**
     * 循环获取
     * @param callable $cb
     * @return $this
     */
    public function each(callable $cb) {
        array_map($cb, $this->https);
        return $this;
    }

    public function __destruct() {
        curl_multi_close($this->handle);
    }

    /**
     * @param $curl
     * @return string|null
     */
    public static function getHttpContent($curl): ?string {
        return curl_multi_getcontent($curl instanceof Http ? $curl->getHandle() : $curl);
    }

}