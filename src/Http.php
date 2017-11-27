<?php
namespace Zodream\Http;

use Zodream\Disk\File;
use Zodream\Disk\Stream;
use Zodream\Helpers\Json;
use Zodream\Helpers\Xml;

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


    protected $parameters = [];
    protected $method = self::GET;
    /**
     * @var resource
     */
    protected $curl;
    /**
     * @var array 响应头
     */
    protected $responseHeaders;
    /**
     * @var string 响应正文
     */
    protected $responseText;

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
     * @param array $parameters
     * @param bool $verifySSL
     * @return Http
     */
    public function url($url, $parameters = [], $verifySSL = false) {
        if (!$url instanceof Uri) {
            $url = new Uri($url);
        }
        $url->addData($parameters);
        if (!$verifySSL && $url->isSSL()) {
            $this->setOption(CURLOPT_SSL_VERIFYPEER, FALSE)
                ->setOption(CURLOPT_SSL_VERIFYHOST, FALSE)
                ->setOption(CURLOPT_SSLVERSION, 1);
        }
        return $this->setOption(CURLOPT_URL, (string)$url);
    }

    /**
     * 根据参数自动转换
     * @param array $map
     * @param array $parameters
     * @return $this
     */
    public function maps(array $map, $parameters = []) {
        $parameters = array_merge($this->parameters, $parameters);
        $this->parameters = $this->getData($map, $parameters);
        return $this;
    }

    /**
     * 设置参数
     * @param $parameters
     * @return $this
     */
    public function parameters($parameters) {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * 编码
     * @param string|callable $func
     * @return $this|Http
     */
    public function encode($func = self::JSON) {
        if (is_callable($func)) {
            return $this->parameters(call_user_func($func, $this->parameters));
        }
        if ($func == self::JSON) {
            return $this->parameters(Json::encode($this->parameters));
        }
        if ($func == self::XML) {
            return $this->parameters(Xml::encode($this->parameters));
        }
        return $this;
    }

    /**
     * 设置请求方法
     * @param string $method
     * @return $this
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
     * @return $this
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
     */
    public function get() {
        return $this->method()->text();
    }

    public function post() {
        return $this->method(self::POST)->text();
    }

    public function delete() {
        return $this->method(self::DELETE)->text();
    }

    public function patch() {
        return $this->method(self::PATCH)->text();
    }

    public function put() {
        return $this->method(self::PUT)->text();
    }

    public function head() {
        return $this->method(self::HEAD)->text();
    }

    public function options() {
        return $this->method(self::OPTIONS)->text();
    }

    public function search() {
        return $this->method(self::SEARCH)->text();
    }

    public function text() {
        return $this->setCommonOption()->execute();
    }

    public function xml() {
        return Xml::decode($this->text());
    }

    public function json() {
        return Json::decode($this->text());
    }

    /**
     * 保存
     * @param $file
     * @return string
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
     */
    public function show() {
        return $this->execute();
    }

    /**
     * 解码相应内容
     * @param null $func
     * @return $this|mixed
     */
    public function decode($func = null) {
        if (is_callable($func)) {
            return call_user_func($func, $this->text());
        }
        if ($func == self::JSON) {
            return $this->json();
        }
        if ($func == self::XML) {
            return $this->xml();
        }
        $text = $this->text();
        if (!is_null($func)) {
            return $text;
        }
        if (preg_match($this->_jsonPattern, $this->getContentType())) {
            return Json::decode($text);
        }
        if (preg_match($this->_xmlPattern, $this->getContentType())) {
            return Xml::decode($text);
        }
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
        $this->applyMethod();
        $this->responseText = curl_exec($this->curl);
        $this->responseHeaders = curl_getinfo($this->curl);
        $this->responseHeaders['error'] = curl_error($this->curl);
        $this->responseHeaders['errorNo'] = curl_errno($this->curl);
        if ($this->responseText === false) {
            throw new \Exception($this->responseHeaders['error']);
        }
        return $this->responseText;
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
     * GET RESULT
     * @return mixed|null
     */
    public function getResponseText() {
        if (is_resource($this->curl)
            && is_null($this->responseText)) {
            return $this->execute();
        }
        return $this->responseText;
    }

    /**
     * SET COMMON OPTION
     * @return $this
     */
    public function setCommonOption() {
        return $this->setOption(CURLOPT_HEADER, 0)   // 是否输出包含头部
        ->setOption(CURLOPT_RETURNTRANSFER, 1) // 返回不直接输出
        ->setOption(CURLOPT_FOLLOWLOCATION, 1)  // 允许重定向
        ->setOption(CURLOPT_AUTOREFERER, 1);  // 自动设置 referrer
    }

    /**
     * SET USER AGENT
     * @param string $args
     * @return Curl
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

    /**
     * 生成post 提交数据
     * @return array
     */
    public function buildPostParameters() {
        if (!is_array($this->parameters)) {
            return $this->parameters;
        }
        $parameters = $this->parameters;
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
     * 应用请求方式
     */
    protected function applyMethod() {
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
        if ($this->method == self::POST) {
            $this->setOption(CURLOPT_POST, true)
                ->setOption(CURLOPT_POSTFIELDS, $parameters);
            return;
        }
        $this->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($this->method))
            ->setOption(CURLOPT_POSTFIELDS, $parameters);
    }


    /**
     * 获取值 根据 #区分必须  $key => $value 区分默认值
     * 支持多选 键必须为 数字， 支持多级 键必须为字符串
     * @param array $keys
     * @param array $args
     * @return array
     */
    protected function getData(array $keys, array $args) {
        $data = array();
        foreach ($keys as $key => $item) {
            $data = array_merge($data,
                $this->getDataByKey($key, $item, $args));
        }
        return $data;
    }

    /**
     * 获取一个值
     * @param $key
     * @param $item
     * @param array $args
     * @return array
     */
    protected function getDataByKey($key, $item, array $args) {
        if (is_array($item)) {
            $item = $this->chooseData($item, $args);
        }
        if (is_integer($key)) {
            if (is_array($item)) {
                return $item;
            }
            $key = $item;
            $item = null;
        }
        $need = false;
        if (strpos($key, '#') === 0) {
            $key = substr($key, 1);
            $need = true;
        }
        $keyTemp = explode(':', $key, 2);
        if (array_key_exists($keyTemp[0], $args)) {
            $item = $args[$keyTemp[0]];
        }
        if ($this->isEmpty($item)) {
            if ($need) {
                throw  new \InvalidArgumentException($keyTemp[0].' IS NEED!');
            }
            return [];
        }
        if (count($keyTemp) > 1) {
            $key = $keyTemp[1];
        }
        return [$key => $item];
    }

    /**
     * MANY CHOOSE ONE
     * @param array $item
     * @param array $args
     * @return array
     */
    protected function chooseData(array $item, array $args) {
        $data = $this->getData($item, $args);
        if (empty($data)) {
            throw new \InvalidArgumentException('ONE OF MANY IS NEED!');
        }
        return $data;
    }

}