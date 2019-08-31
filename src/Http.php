<?php
namespace Zodream\Http;

use Zodream\Disk\File;
use Zodream\Disk\Stream;
use Zodream\Helpers\Arr;
use Zodream\Helpers\Json;
use Zodream\Helpers\Xml;
use Exception;
use Zodream\Validate\Validator;

/**
 * Class Http
 * @package Zodream\Http
 */
class Http {

    const XML = 'xml';
    const JSON = 'json';

    const GET = 'get';
    const POST = 'post';
    const DELETE = 'delete';
    const HEAD = 'head';
    const PATCH = 'patch';
    const SEARCH = 'search';
    const PUT = 'put';
    const OPTIONS = 'options';

    private $_jsonPattern = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';
    private $_xmlPattern = '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i';

    /**
     * 原始数据
     * @var array|mixed
     */
    protected $parameters = [];
    protected $method = self::GET;
    /**
     * @var resource
     */
    protected $curl;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * 网址参数地图
     * @var array
     */
    protected $uriMaps = [];

    /**
     * 编码
     * @var mixed
     */
    protected $uriEncodeFunc;

    /**
     * post 参数地图
     * @var array
     */
    protected $formMaps = [];

    /**
     * https 是否验证 ssl 证书
     * @var bool
     */
    protected $verifySSL = false;

    /**
     * 针对post 数据的编码
     * @var array
     */
    protected $encodeFunc = [];

    /**
     * 解码方法
     * @var array
     */
    protected $decodeFunc = [];

    /**
     * @var array 响应头
     */
    protected $responseHeaders;
    /**
     * @var string 响应正文
     */
    protected $responseText;

    protected $isMulti = false;

    /**
     * Http constructor.
     * @param null $url
     */
    public function __construct($url = null) {
        $this->curl = curl_init();
        if (!empty($url)) {
            $this->url($url);
        }
    }

    /**
     * 设置网址
     * @param string|Uri $url
     * @param array $maps
     * @param null $func
     * @param bool $verifySSL
     * @return Http
     */
    public function url($url, array $maps = [], $func = null, $verifySSL = false) {
        if (!empty($url)) {
            $this->uri = !$url instanceof Uri ? new Uri((string)$url) : $url;
        }
        $this->uriMaps = $maps;
        $this->uriEncodeFunc = $func;
        $this->verifySSL = $verifySSL;
        return $this;
    }

    /**
     * 根据参数自动转换
     * @param array $maps
     * @param array $parameters
     * @return Http
     */
    public function maps(array $maps, $parameters = []) {
        // 更改请求方式
        if ($this->method == self::GET) {
            $this->method = self::POST;
        }
        $this->parameters($parameters);
        $this->formMaps = $maps;
        return $this;
    }

    /**
     * 追加转换地图
     * @param array $maps
     * @return Http
     */
    public function appendMaps(array $maps) {
        $this->formMaps = array_merge($this->formMaps, $maps);
        return $this;
    }

    /**
     * 设置参数
     * @param $parameters
     * @return Http
     */
    public function parameters($parameters) {
        if (empty($parameters)) {
            return $this;
        }
        $this->parameters = !is_array($parameters)
            ? $parameters
            : array_merge($this->parameters, $parameters);
        return $this;
    }

    /**
     * 编码
     * @param string|callable $func
     * @param bool $is_clear 清空原有的d
     * @return Http
     */
    public function encode($func = self::JSON, $is_clear = false) {
        if ($is_clear) {
            $this->encodeFunc = [];
        }
        $this->encodeFunc[] = $func;
        return $this;
    }

    /**
     * 设置请求方法
     * @param string $method
     * @return Http
     */
    public function method($method = self::GET) {
        $this->method = strtolower($method);
        return $this;
    }

    /**
     * Progress
     *
     * @access public
     * @param  $callback
     * @return Http
     */
    public function progress($callback) {
        $this->setOption(CURLOPT_PROGRESSFUNCTION, $callback);
        $this->setOption(CURLOPT_NOPROGRESS, false);
        return $this;
    }

    /**
     * 设置cookie
     * @param $key
     * @param null $value
     * @return Http
     */
    public function cookie($key, $value = null) {
        if (strpos($key, '@') === 0
            && is_file(substr($key, 1))) {
            return $this->setCookieFile(substr($key, 1));
        }
        if (is_array($key)
            || strpos($key, '=') !== false) {
            return $this->setCookie($key);
        }
        return $this->setCookie([
            $key => $value
        ]);
    }

    /**
     * 设置请求头
     * @param $key
     * @param null $value
     * @return Http
     */
    public function header($key, $value = null) {
        if (is_array($key)) {
            return $this->setHeader($key);
        }
        return $this->setHeader([
            $key => $value
        ]);
    }

    /**
     * get 方法请求
     * @return mixed|null
     * @throws \Exception
     */
    public function get() {
        return $this->method()->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function post() {
        return $this->method(self::POST)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function delete() {
        return $this->method(self::DELETE)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function patch() {
        return $this->method(self::PATCH)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function put() {
        return $this->method(self::PUT)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function head() {
        return $this->method(self::HEAD)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function options() {
        return $this->method(self::OPTIONS)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function search() {
        return $this->method(self::SEARCH)->text();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function text() {
        return $this->setCommonOption()->execute();
    }

    /**
     * @return array|mixed|object
     * @throws \Exception
     */
    public function xml() {
        return $this->decode(self::XML, true)->text();
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function json() {
        return $this->decode(self::JSON, true)->text();
    }

    /**
     * 保存
     * @param $file
     * @return string
     * @throws \Exception
     */
    public function save($file) {
        if (!$file instanceof Stream) {
            $file = new Stream($file);
        }
        $file->open('w');
        $this->setCommonOption()
            ->setOption(CURLOPT_FILE, $file->getStream())
            ->execute();
        $file->close();
        return $this->responseText;
    }

    /**
     * 显示
     * @return mixed|null
     * @throws \Exception
     */
    public function show() {
        return $this->execute();
    }

    /**
     * 解码相应内容
     * @param null $func
     * @param bool $is_clear  清空原有的
     * @return $this
     */
    public function decode($func = null, $is_clear = false) {
        if ($is_clear) {
            $this->decodeFunc = [];
        }
        $this->decodeFunc[] = $func;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function error() {
        return $this->responseHeaders['error'];
    }

    /**
     * EXECUTE AND CLOSE
     * @return mixed|null
     * @throws \Exception
     */
    public function execute() {
        if ($this->isMulti) {
            return;
        }
        $this->applyMethod();
        $this->responseText = curl_exec($this->curl);
        self::log('HTTP RESPONSE: '.$this->responseText);
        return $this->parseResponse();
    }

    /**
     * @return array|mixed|object
     * @throws Exception
     */
    public function executeWithBatch() {
        $this->responseText = HttpBatch::getHttpContent($this->curl);
        self::log('HTTP RESPONSE: '.$this->responseText);
        return $this->parseResponse();
    }

    /**
     * @return array|mixed|object
     * @throws Exception
     */
    protected function parseResponse() {
        $this->responseHeaders = curl_getinfo($this->curl);
        $this->responseHeaders['error'] = curl_error($this->curl);
        $this->responseHeaders['errorNo'] = curl_errno($this->curl);
        if ($this->responseText === false) {
            throw new \Exception($this->responseHeaders['error']);
        }
        return $this->decodeResponse($this->responseText);
    }

    /**
     * @param null $key
     * @return array| string
     */
    public function getResponseHeader($key = null) {
        if (empty($key)) {
            return $this->responseHeaders;
        }
        return isset($this->responseHeaders[$key]) ? $this->responseHeaders[$key] : null;
    }

    /**
     * GET STATUS
     * @return mixed
     */
    public function getStatusCode() {
        return $this->responseHeaders['http_code'];
    }

    /**
     * 获取响应内容
     * @return mixed
     */
    public function getContentType() {
        return $this->responseHeaders['content_type'];
    }

    /**
     * @return resource
     */
    public function getHandle() {
        return $this->curl;
    }

    /**
     * GET RESULT
     * @return mixed|null
     * @throws \Exception
     */
    public function getResponseText() {
        if (is_resource($this->curl)
            && is_null($this->responseText)) {
            return $this->execute();
        }
        return $this->responseText;
    }

    public function setHeaderOption($hasHeader = false) {
        return $this->setOption(CURLOPT_HEADER, $hasHeader);   // 是否输出包含头部
    }

    /**
     * @param bool $isMulti
     * @return Http
     */
    public function setIsMulti(bool $isMulti = true) {
        $this->isMulti = $isMulti;
        return $this;
    }

    /**
     * SET COMMON OPTION
     * @return $this
     */
    public function setCommonOption() {
        return $this->setHeaderOption()
            ->setOption(CURLOPT_RETURNTRANSFER, 1) // 返回不直接输出
            ->setOption(CURLOPT_FOLLOWLOCATION, 1)  // 允许重定向
            ->setOption(CURLOPT_AUTOREFERER, 1);  // 自动设置 referrer
    }

    /**
     * 设置代理
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $pwd
     * @return $this|Http
     */
    public function setProxy($host, $port = null, $user = null, $pwd = null) {
        $this->setOption(CURLOPT_PROXY, $host);
        if (!empty($port)) {
            $this->setOption(CURLOPT_PROXYPORT, $port);
        }
        if (empty($user)) {
            return $this->setOption(CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        }
        return $this->setOption(CURLOPT_PROXYAUTH, CURLAUTH_ANY)
            ->setOption(CURLOPT_PROXYUSERPWD, sprintf('%s:%s', $user, $pwd));
    }

    /**
     * SET USER AGENT
     * @param string $args
     * @return Http
     */
    public function setUserAgent($args) {
        return $this->setOption(CURLOPT_USERAGENT, $args);
    }

    /**
     * SET REFERRER URL
     * @param string|Uri $url
     * @return Http
     */
    public function setReferrer($url) {
        return $this->setOption(CURLOPT_REFERER, (string)$url);
    }

    /**
     * NOT OUTPUT BODY
     * @return Http
     */
    public function setNoBody() {
        return $this->setOption(CURLOPT_NOBODY, true);
    }

    /**
     * SET COOKIE
     * @param string|array $cookie
     * @return Http
     */
    public function setCookie($cookie) {
        if (empty($cookie)) {
            return $this;
        }
        if (is_array($cookie)) {
            $cookie = http_build_query($cookie);
        }
        return $this->setOption(CURLOPT_COOKIE, $cookie);
    }

    /**
     * SET COOKIE FILE
     * @param string|File $file
     * @return $this
     */
    public function setCookieFile($file) {
        $file = (string)$file;
        return $this->setOption(CURLOPT_COOKIEJAR, $file)
            ->setOption(CURLOPT_COOKIEFILE, $file);
    }

    /**
     * SET HEADER
     * @param array $args
     * @return $this
     */
    public function setHeader(array $args) {
        $header = [];
        foreach ($args as $key => $item) {
            $key = implode('-', array_map('ucfirst', explode('-', strtolower($key))));
            if (empty($item)) {
                continue;
            }
            if (is_array($item)) {
                $item = implode(',', $item);
            }
            $header[] = $key. ':'. $item;
        }
        if (empty($header)) {
            return $this;
        }
        return $this->setOption(CURLOPT_HTTPHEADER, $header);
    }

    /**
     * @param string|array $option
     * @param mixed $value
     * @return $this
     */
    public function setOption($option, $value = null) {
        if (is_array($option)) {
            curl_setopt_array($this->curl, $option);
        } else {
            curl_setopt($this->curl, $option, $value);
        }
        return $this;
    }

    /**
     * CLOSE
     * @return $this
     */
    public function close() {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        return $this;
    }

    public function __destruct() {
        if (is_resource($this->curl)) {
            $this->close();
        }
    }

    public function getPostSource() {
        if (!is_array($this->parameters)) {
            return $this->parameters;
        }
        if (empty($this->formMaps)) {
            return '';
        }
        $parameters = empty($this->parameters) && !Arr::isAssoc($this->formMaps)
            ? $this->formMaps
            : $this->getParametersByMaps($this->formMaps);
        foreach ($this->encodeFunc as $func) {
            if (is_callable($func)) {
                $parameters = call_user_func($func, $parameters);
                continue;
            }
            if ($func == self::JSON) {
                $parameters = Json::encode($parameters);
                continue;
            }
            if ($func == self::XML) {
                $parameters = Xml::encode($parameters);
                continue;
            }
        }
        return $parameters;
    }

    /**
     * 生成post 提交数据
     * @return array|string
     * @throws Exception
     */
    public function buildPostParameters() {
        $parameters = $this->getPostSource();
        if (!is_array($parameters)) {
            return $parameters;
        }
        $binary_data = false;
        foreach ($parameters as $key => $value) {
            if (is_string($value) && strpos($value, '@')
                === 0 && is_file(substr($value, 1))) {
                $binary_data = true;
                if (class_exists('CURLFile')) {
                    $parameters[$key] = new \CURLFile(substr($value, 1));
                }
            } elseif ($value instanceof \CURLFile) {
                $binary_data = true;
            }
        }
        if ($binary_data) {
            return $parameters;
        }
        return http_build_query($parameters, '', '&');
    }

    /**
     * 自动解码结果
     * @param $data
     * @return array|mixed|object
     * @throws \Exception
     */
    protected function decodeResponse($data) {
        foreach ($this->decodeFunc as $func) {
            if (is_callable($func)) {
                $data = call_user_func($func, $data);
                continue;
            }
            if ($func == self::JSON) {
                $data = Json::decode($data);
                continue;
            }
            if ($func == self::XML) {
                $data = Xml::decode($data);
                continue;
            }
            if (!is_null($func)) {
                continue;
            }
            if (preg_match($this->_jsonPattern, $this->getContentType())) {
                $data = Json::decode($data);
                continue;
            }
            if (preg_match($this->_xmlPattern, $this->getContentType())) {
                $data = Xml::decode($data);
                continue;
            }
        }
        return $data;
    }

    /**
     * 获取链接
     * @return Uri
     * @throws \Exception
     */
    public function getUrl() {
        if (!empty($this->uriMaps)
            && is_array($this->parameters)) {
            $this->uri->addData($this->getUriParameters());
        }
        self::log('HTTP URL: '.(string)$this->uri);
        return $this->uri;
    }

    /**
     * 获取uri参数
     * @return array|mixed|object
     * @throws \Exception
     */
    public function getUriParameters() {
        $data = $this->getParametersByMaps($this->uriMaps);
        if (empty($this->uriEncodeFunc)) {
            return $data;
        }
        if (is_callable($this->uriEncodeFunc)) {
            return call_user_func($this->uriEncodeFunc, $data);
        }
        if ($this->uriEncodeFunc == self::JSON) {
            return Json::encode($data);
        }
        if ($this->uriEncodeFunc == self::XML) {
            return Xml::encode($data);
        }
        return $data;
    }

    /**
     * 应用请求方式
     * @throws Exception
     */
    public function applyMethod() {
        if (!$this->verifySSL && $this->uri->isSSL()) {
            $this->setOption(CURLOPT_SSL_VERIFYPEER, FALSE)
                ->setOption(CURLOPT_SSL_VERIFYHOST, FALSE)
                ->setOption(CURLOPT_SSLVERSION, 1);
        }
        $this->setOption(CURLOPT_URL, (string)$this->getUrl());
        if ($this->method == self::GET) {
            return;
        }
        if ($this->method == self::HEAD) {
            $this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($this->method))
                ->setNoBody();
            return;
        }
        if (in_array($this->method,
            [self::OPTIONS, self::DELETE])) {
            $this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($this->method));
            return;
        }
        $parameters = $this->buildPostParameters();
        self::log('HTTP DATA: '.var_export($parameters, true));
        if ($this->method == self::POST) {
            $this->setOption(CURLOPT_POST, true)
                ->setOption(CURLOPT_POSTFIELDS, $parameters);
            return;
        }
        $this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($this->method))
            ->setOption(CURLOPT_POSTFIELDS, $parameters);
    }

    /**
     * 根据对应关系做转化
     * @param array $maps
     * @return array
     * @throws Exception
     */
    public function getParametersByMaps(array $maps) {
        return static::getMapParameters($maps, $this->parameters);
    }


    /**
     * 获取值 根据 #区分必须  $key => $value 区分默认值
     * 支持多选 键必须为 数字， 支持多级 键必须为字符串
     * @param array $maps
     * @param array $args
     * @return array
     * @throws Exception
     */
    public static function getMapParameters(array $maps, array $args) {
        $data = array();
        foreach ($maps as $key => $item) {
            $data = array_merge($data,
                static::getParametersByKey($key, $item, $args));
        }
        return $data;
    }

    /**
     * 获取一个值
     * @param $key
     * @param $item
     * @param array $args
     * @return array
     * @throws Exception
     */
    protected static function getParametersByKey($key, $item, array $args) {
        if (is_array($item) && is_integer($key)) {
            // 多选多
            return static::chooseParameters($item, $args);
        }
        if (is_integer($key)) {
            list($key, $item) = [$item, null];
        }
        if (isset($args[$key])) {
            // 增加含特殊标记的键判断
            return [$key => $args[$key]];
        }
        $need = false;
        if (strpos($key, '#') === 0) {
            $key = substr($key, 1);
            $need = true;
        }
        $oldKey = $key;
        if (strpos($key, ':') > 0) {
            // 更改键 新:旧
            list($key, $oldKey) = explode(':', $key, 2);
        }
        if (isset($args[$oldKey]) && !static::isEmpty($args[$oldKey])) {
            // 只要存在值就马上返回 不进行后面的推算
            return [$key => $args[$oldKey]];
        }
        if (is_array($item)) {
            // 不进行里面的报错
            try {
                $item = static::getMapParameters($item, $args);
            } catch (Exception $ex) {
                $item = null;
            }
        }
        if (!static::isEmpty($item)) {
            return [$key => $item];
        }
        if ($need) {
            throw new Exception($key.' IS NEED!');
        }
        return [];

    }

    /**
     * 验证值是否为空
     * @param $value
     * @return bool
     */
    public static function isEmpty($value) {
        return !Validator::required()->validate($value);
    }

    /**
     * MANY CHOOSE ONE
     * @param array $item
     * @param array $args
     * @return array
     * @throws Exception
     */
    protected static function chooseParameters(array $item, array $args) {
        $data = static::getMapParameters($item, $args);
        if (empty($data)) {
            throw new Exception('ONE OF MANY IS NEED!');
        }
        return $data;
    }

    protected static function log($message) {
        if (!defined('DEBUG') || !DEBUG) {
            return;
        }
        if (!function_exists('logger')) {
            return;
        }
        logger($message);
    }



}