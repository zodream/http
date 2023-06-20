<?php
declare(strict_types=1);
namespace Zodream\Http;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/16
 * Time: 17:26
 */
use Traversable;
use IteratorAggregate;
use ArrayIterator;

class Header implements IteratorAggregate {

    const COOKIES_ARRAY = 'array';

    const COOKIES_FLAT = 'flat';

    protected array $headers = array();
    
    protected array $cacheControl = array();

    protected array $cookies = array();
    /**
     * @var array
     */
    protected array $headerNames = array();
    
    public function __construct() {
        if (!isset($this->headers['cache-control'])) {
            $this->set('Cache-Control', '');
        }
    }

    public function setCookie(
        string|Cookie $cookie,
        mixed $value = null,
        mixed $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true) {
        if (!$cookie instanceof Cookie) {
            $cookie = new Cookie(
                $cookie,
                $value,
                $expire,
                $path,
                $domain,
                $secure,
                $httpOnly
            );
        }
        $this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
        return $this;
    }

    public function removeCookie(string $name, string $path = '/', string $domain = '') {
        if (null === $path) {
            $path = '/';
        }

        unset($this->cookies[$domain][$path][$name]);

        if (empty($this->cookies[$domain][$path])) {
            unset($this->cookies[$domain][$path]);

            if (empty($this->cookies[$domain])) {
                unset($this->cookies[$domain]);
            }
        }
        return $this;
    }

    /**
     * Returns an array with all cookies.
     *
     * @param string $format
     *
     * @throws \InvalidArgumentException When the $format is invalid
     *
     * @return Cookie[]
     */
    public function getCookies(string $format = self::COOKIES_FLAT): array {
        if (!in_array($format, array(self::COOKIES_FLAT, self::COOKIES_ARRAY))) {
            throw new \InvalidArgumentException(sprintf('Format "%s" invalid (%s).', $format, implode(', ', array(self::COOKIES_FLAT, self::COOKIES_ARRAY))));
        }

        if (self::COOKIES_ARRAY === $format) {
            return $this->cookies;
        }

        $flattenedCookies = array();
        foreach ($this->cookies as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattenedCookies[] = $cookie;
                }
            }
        }

        return $flattenedCookies;
    }

    /**
     * Clears a cookie in the browser.
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @return Header
     */
    public function clearCookie(string $name,
                                string $path = '/', string $domain = '',
                                bool $secure = false, bool $httpOnly = true) {
        return $this->setCookie(new Cookie($name, null, 1, $path, $domain, $secure, $httpOnly));
    }

    public function __toString(): string {
        $cookies = '';
        foreach ($this->getCookies() as $cookie) {
            $cookies .= 'Set-Cookie: '.$cookie."\r\n";
        }

        ksort($this->headerNames);
        if (!$this->headers) {
            return $cookies;
        }

        $max = max(array_map('strlen', array_keys($this->headers))) + 1;
        $content = '';
        ksort($this->headers);
        foreach ($this->headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));
            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
            }
        }

        return $content.$cookies;
    }

    public function all(): array {
        return $this->headers;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys(): array {
        return array_keys($this->headers);
    }

    public function replace(array $headers = array()) {
        $this->headers = array();
        return $this->add($headers);
    }

    /**
     * Adds new headers the current HTTP headers set.
     *
     * @param array $headers An array of HTTP headers
     * @return $this
     */
    public function add(array $headers) {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
        return $this;
    }

    public function get(string $key, mixed $default = null, bool $first = true) {
        $key = $this->filterKey($key);

        if (!array_key_exists($key, $this->headers)) {
            if (null === $default) {
                return $first ? null : array();
            }

            return $first ? $default : array($default);
        }

        if ($first) {
            return count($this->headers[$key]) ? $this->headers[$key][0] : $default;
        }

        return $this->headers[$key];
    }

    public function set(string $key, mixed $values, bool $replace = true) {
        $key = $this->filterKey($key);

        $values = array_values((array) $values);

        if (true === $replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_merge($this->headers[$key], $values);
        }

        if ('cache-control' === $key) {
            $this->cacheControl = $this->parseCacheControl($values[0]);
        }
        return $this;
    }

    protected function filterKey(string $key): string {
        $key = str_replace(['_', '-'], [' ', ' '], strtolower($key));
        return str_replace(' ', '-', ucwords($key));
    }

    public function has(string $key): bool {
        return array_key_exists(str_replace('_', '-', strtolower($key)), $this->headers);
    }

    public function delete(string $tag) {
        $tag = str_replace('_', '-', strtolower($tag));
        unset($this->headers[$tag]);
        if ('cache-control' === $tag) {
            $this->cacheControl = array();
        }
        return $this;
    }

    protected function parseCacheControl(string $header): array {
        $cacheControl = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $cacheControl[strtolower($match[1])] = $match[3] ?? ($match[2] ?? true);
        }

        return $cacheControl;
    }

    public function setRedirect(string $url, int $time = 0) {
        if (empty($url)) {
            return $this;
        }
        if (empty($time)) {
            return $this->set('Location', (string)$url);
        }
        return $this->set('Refresh', $time.';url='.$url);
    }

    public function setXPoweredBy(string $name = 'PHP/8.0') {
        return $this->set('X-Powered-By', $name);
    }

    /**
     * WEB服务器名称
     * @param string $name
     * @return Header
     */
    public function setServer(string $name = 'Apache') {
        return $this->set('Server', $name);
    }

    public function setContentLanguage(string $language = 'zh-CN') {
        return $this->set('Content-language', $language);
    }

    /**
     * md5校验值
     * @param string $md5
     * @return Header
     */
    public function setContentMD5(string $md5) {
        return $this->set('Content-MD5', $md5);
    }

    /**
     * 缓存控制
     * @param string $option 默认禁止缓存
     * @return Header
     */
    public function setCacheControl(
        string $option = 'no-cache, no-store, max-age=0, must-revalidate') {
        return $this->set('Cache-Control', $option);
    }

    /**
     * 实现特定指令
     * @param string $option
     * @return Header
     */
    public function setPragma(string $option) {
        return $this->set('Pragma', $option);
    }

    /**
     * 如果实体不可取，指定时间重试
     * @param integer $time
     * @return Header
     */
    public function setRetryAfter(int $time) {
        return $this->set('Retry-After', $time);
    }

    /**
     * 原始服务器发出时间
     * @param integer $time
     * @return Header
     */
    public function setDate(int $time) {
        return $this->set('Date', gmdate('D, d M Y H:i:s', $time).' GMT');
    }

    /**
     * 响应过期的时间
     * @param integer $time
     * @return Header
     */
    public function setExpires(int $time) {
        return $this->set('Expires', gmdate('D, d M Y H:i:s', $time).' GMT');
    }

    /**
     * 最后修改时间
     * @param integer $time
     * @return Header
     */
    public function setLastModified(int $time) {
        return $this->set('Last-Modified', gmdate('D, d M Y H:i:s', $time).' GMT');
    }

    /**
     * 大小
     * @param int|string $length
     * @return Header
     */
    public function setContentLength(int|string $length) {
        return $this->set('Content-Length', $length);
    }

    /**
     * 文件流的范围
     * @param string|int $length
     * @param string $type
     * @return Header
     */
    public function setContentRange(string|int $length, string $type = 'bytes') {
        return $this->set('Content-Range', $type.' '.$length);
    }

    /**
     * 下载文件是指定接受的单位
     * @param string $type
     * @return Header
     */
    public function setAcceptRanges(string $type = 'bytes') {
        return $this->set('Accept-Ranges', $type);
    }

    /**
     * 下载文件的文件名
     * @param string $filename
     * @return Header
     * @throws \Exception
     */
    public function setContentDisposition(string $filename) {
        if (str_contains(app('request')->server('HTTP_USER_AGENT', ''), 'MSIE')) {     //如果是IE浏览器
            $filename = preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1);
        }
        return $this->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * 设置强迫客户端认证 根据后两个参数判断 Basic、 Digest
     * @param string $realm
     * @param string $qop
     * @param string $nonce
     * @param string $opaque
     * @return Header
     */
    public function setWWWAuthenticate(string $realm, string $qop = 'auth',
                                       string $nonce = '',
                                       string $opaque = '') {
        if (empty($nonce) && empty($opaque)) {
            $content = sprintf('Basic realm="%s"', $realm);
        } else {
            $content = sprintf('Digest realm="%s" qop="%s" nonce="%s" opaque="%s"',
                $realm, $qop, $nonce, $opaque);
        }
        return $this->set('WWW-Authenticate', $content);
    }

    /**
     * 文件传输编码
     * @param string $encoding
     * @return Header
     */
    public function setTransferEncoding(string $encoding = 'chunked') {
        return $this->set('Transfer-Encoding', $encoding);
    }

    /**
     * ajax 跨域响应，默认允许跨域
     * @param string $allowedOrigins
     * @param string $allowedMethods
     * @param string $allowedHeaders
     * @param int $maxAge
     * @param bool $supportsCredentials
     * @return Header
     */
    public function setCORS(
        string $allowedOrigins = '*',
        string $allowedMethods = '*',
        string $allowedHeaders = '*',//'Authorization, Content-Type, X-Requested-With',
        int $maxAge = 0,
        mixed $supportsCredentials = false,
        string $exposeHeaders = 'Content-Disposition',
    ) {
        return $this->add([
            'Access-Control-Allow-Origin' => $allowedOrigins,
            'Access-Control-Allow-Credentials' => $supportsCredentials,
            'Access-Control-Allow-Methods' => $allowedMethods,
            'Access-Control-Max-Age' => $maxAge,
            'Access-Control-Allow-Headers' => $allowedHeaders,
            'Access-Control-Expose-Headers' => $exposeHeaders
        ]);
    }

    /**
     * HTTP2 server push
     * @param $url
     * @param $as
     * @param null $type
     * @param bool $crossorigin
     * @param bool $nopush
     * @return Header
     */
    public function setLink(string $url,
                            string $as, ?string $type = null,
                            bool $crossorigin = false, bool $nopush = false) {
        $args = [
            sprintf('<%s>', $url),
            'rel=preload',
            'as='.$as
        ];
        if (!empty($type)) {
            $args[] = 'type='.$type;
        }
        if ($crossorigin) {
            $args[] = 'crossorigin';
        }
        if ($nopush) {
            $args[] = 'nopush';
        }
        return $this->set('Link', implode('; ', $args), false);
    }

    /**
     * 返回内容的MIME类型
     * @param string $type
     * @param string $option
     * @return Header
     */
    public function setContentType(string $type = 'html', string $option = 'utf-8') {
        $type = strtolower($type);
        if ($type == 'image' || $type == 'img') {
            return $this->set('Content-Type', 'image/'.$option);
        }
        $content = MIME::get($type);
        if (in_array($type, array('html', 'json', 'rss', 'xml'))) {
            $content .= ';charset='.$option;
        }
        return $this->set('Content-Type', $content);
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->all());
    }

    public function parse($args) {
        return $this->replace($args);
    }

    public function toArray(): array {
        return $this->all();
    }
}