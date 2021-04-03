<?php
declare(strict_types=1);
namespace Zodream\Http;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/6
 * Time: 10:07
 */

class Uri {

    protected string $scheme = 'http';

    protected string $host = '';

    protected int $port = 80;

    protected string $username = '';

    protected string $password = '';

    protected string $path = '';

    protected array $data = [];

    protected string $fragment = '';

    public function __construct(string $url = '') {
        if (!empty($url)) {
            $this->decode($url);
        }
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function setScheme(string $arg) {
        $this->scheme = $arg;
        return $this;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return bool
     */
    public function isSSL(): bool {
        return 'https' === $this->scheme;
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function setHost(string $arg) {
        $args = explode(':', $arg);
        $this->host = $args[0];
        if (count($args) > 1) {
            $this->setPort($args[1]);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param int $arg
     * @return $this
     */
    public function setPort($arg = 80) {
        $this->port = intval($arg);
        return $this;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function setUsername(string $arg) {
        $this->username = $arg;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function setPassword(string $arg) {
        $this->password = $arg;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     *
     * @param string $arg
     * @return $this
     */
    public function setPath(string $arg) {
        $this->path = $arg;
        return $this;
    }

    /**
     * 追加路径，请注意清除原有查询
     * @param $path
     * @return Uri
     */
    public function addPath(string $path) {
        $src = parse_url($path);
        if(isset($src['scheme']) || isset($src['host'])) {
            return $this->decode($path);
        }
        if (isset($src['query'])) {
            $this->setData($src['query']);
        }
        if (substr($src['path'], 0, 1) == '/') {
            return $this->setPath($src['path']);
        }
        $path = dirname($this->path).'/'.$src['path'];
        $rst = array();
        $path_array = explode('/', $path);
        if(!$path_array[0]) {
            $rst[] = '';
        }
        foreach ($path_array AS $key => $dir) {
            if ($dir == '..') {
                if (end($rst) == '..') {
                    $rst[] = '..';
                    continue;
                }
                if(!array_pop($rst)) {
                    $rst[] = '..';
                }
                continue;
            }
            if($dir && $dir != '.') {
                $rst[] = $dir;
            }
        }
        if (!end($path_array)) {
            $rst[] = '';
        }
        return $this->setPath(str_replace('\\', '/', implode('/', $rst)));
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 设置
     * @param string|array $arg
     * @return $this
     */
    public function setData(array|string $arg) {
        if (is_string($arg)) {
            $str = str_replace('&amp;', '&', $arg);
            $arg = [];
            parse_str($str, $arg);
        }
        $this->data = $arg;
        return $this;
    }

    /**
     * 添加
     * @param string|array $key
     * @param string $value
     * @return $this
     */
    public function addData(string|array $key, $value = null) {
        if (empty($key)) {
            return $this;
        }
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
            return $this;
        }
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * 移除键
     * @param $keys
     * @return $this
     */
    public function removeData($keys) {
        if (!is_array($keys)) {
            $keys = func_get_args();
        }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                continue;
            }
            unset($this->data[$key]);
        }
        return $this;
    }

    /**
     * @param string $key
     * @return array|bool
     */
    public function getData($key = null) {
        if (is_null($key)) {
            return $this->data;
        }
        return $this->data[$key] ?: false;
    }

    /**
     * 判断是否有值
     * @return bool
     */
    public function hasData() {
        return !empty($this->data);
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function setFragment($arg) {
        $this->fragment = $arg;
        return $this;
    }

    /**
     * @param string $arg
     * @return $this
     */
    public function addFragment($arg) {
        if (empty($this->fragment)) {
            return $this->setFragment($arg);
        }
        $this->fragment .= '&'.$arg;
        return $this;
    }

    /**
     * ID
     * @return string
     */
    public function getFragment() {
        return $this->fragment;
    }

    /**
     * STRING TO
     * @param $url
     * @return $this
     */
    public function decode(string $url) {
        $this->decodeUrl($url, false);
        return $this;
    }

    public function merge(string $url) {
        $this->decodeUrl($url, true);
        return $this;
    }

    /**
     * TO STRING
     * @param bool $hasRoot
     * @return string
     */
    public function encode($hasRoot = true) {
        $arg = $hasRoot && !empty($this->host) ? $this->getRoot() : null;
        $arg .= '/'.ltrim($this->path, '/');
        if (!empty($this->data)) {
            $arg .= '?'. http_build_query($this->data);
        }
        if (!empty($this->fragment)) {
            return $arg.'#'.$this->fragment;
        }
        return $arg;
    }

    /**
     * GET URL ROOT
     * @return string
     */
    public function getRoot() {
        $arg = $this->scheme.'://';
        if (!empty($this->username) && !empty($this->password)) {
            $arg .= $this->username.':'.$this->password.'@';
        }
        $arg .= $this->host;
        if (!empty($this->port) && $this->port != 80) {
            return $arg. ':'. $this->port;
        }
        return $arg;
    }

    public function __toString() {
        return $this->encode();
    }

    /**
     * @param $url
     * @param bool $isAppend
     */
    protected function decodeUrl(string $url, bool $isAppend = false) {
        $args = parse_url($url);
        if (isset($args['scheme'])) {
            $this->scheme = $args['scheme'];
        }

        if (isset($args['host'])) {
            $this->host = $args['host'];
        }

        if (isset($args['port'])) {
            $this->port = $args['port'];
        }

        if (isset($args['user'])) {
            $this->username = $args['user'];
        }

        if (isset($args['pass'])) {
            $this->password = $args['pass'];
        }
        if (isset($args['fragment'])) {
            $this->fragment = $args['fragment'];
        }
        if ($isAppend) {
            if (isset($args['path'])) {
                $this->addPath($args['path']);
            }
            if (isset($args['query']) && !empty($args['query'])) {
                parse_str($args['query'], $data);
                $this->addData($data);
            }
            return;
        }
        if (isset($args['path'])) {
            $this->path = $args['path'];
        }
        if (isset($args['query']) && !empty($args['query'])) {
            parse_str($args['query'], $data);
            $this->setData($data);
        }
    }
}