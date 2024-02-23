<?php
declare(strict_types=1);
namespace Zodream\Http;

class MIME {

    const EXTENSION_MIME_MAPS = [
        'ai'    => 'application/postscript',
        'aif'   => 'audio/x-aiff',
        'aifc'  => 'audio/x-aiff',
        'aiff'  => 'audio/x-aiff',
        'apk'   => 'application/vnd.android.package-archive',
        'atom'  => 'application/atom+xml',
        'avi'   => 'video/x-msvideo',
        'bin'   => 'application/macbinary',
        'bmp'   => 'image/bmp',
        'cpt'   => 'application/mac-compactpro',
        'css'   => 'text/css',
        'csv'   => 'text/x-comma-separated-values',
        'dcr'   => 'application/x-director',
        'dir'   => 'application/x-director',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dvi'   => 'application/x-dvi',
        'dxr'   => 'application/x-director',
        'eml'   => 'message/rfc822',
        'eps'   => 'application/postscript',
        'exe'   => 'application/octet-stream',
        'flv'   => 'video/x-flv',
        'flash' => 'application/x-shockwave-flash',
        'flac' => 'audio/mpeg',
        'gif'   => 'image/gif',
        'gtar'  => 'application/x-gtar',
        'gz'    => 'application/x-gzip',
        'hqx'   => 'application/mac-binhex40',
        'htm'   => 'text/html',
        'html'  => 'text/html',
        'ipa'   => 'application/iphone',
        'jpe'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'jpg'   => 'image/jpeg',
        'js'    => 'application/x-javascript',
        'json'  => 'application/json',
        'log'   => 'text/plain',
        'm3u8'  => 'application/x-mpegURL',
        'mid'   => 'audio/midi',
        'midi'  => 'audio/midi',
        'mif'   => 'application/vnd.mif',
        'mov'   => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mp2'   => 'audio/mpeg',
        'mp3'   => 'audio/mpeg',
        'mp4'   => 'video/mpeg',
        'mpe'   => 'video/mpeg',
        'mpeg'  => 'video/mpeg',
        'mpg'   => 'video/mpeg',
        'mpga'  => 'audio/mpeg',
        'oda'   => 'application/oda',
        'pdf'   => 'application/pdf',
        'php'   => 'application/x-httpd-php',
        'php3'  => 'application/x-httpd-php',
        'php4'  => 'application/x-httpd-php',
        'phps'  => 'application/x-httpd-php-source',
        'phtml' => 'application/x-httpd-php',
        'plist' => 'application/xml',
        'png'   => 'image/png',
        'webp' => 'image/webp',
        'ppt'   => 'application/powerpoint',
        'ps'    => 'application/postscript',
        'psd'   => 'application/x-photoshop',
        'qt'    => 'video/quicktime',
        'ra'    => 'audio/x-realaudio',
        'ram'   => 'audio/x-pn-realaudio',
        'rm'    => 'audio/x-pn-realaudio',
        'rpm'   => 'audio/x-pn-realaudio-plugin',
        'rss'   => 'application/rss+xml',
        'rtf'   => 'text/rtf',
        'rtx'   => 'text/richtext',
        'rv'    => 'video/vnd.rn-realvideo',
        'shtml' => 'text/html',
        'sit'   => 'application/x-stuffit',
        'smi'   => 'application/smil',
        'smil'  => 'application/smil',
        'swf'   => 'application/x-shockwave-flash',
        'tar'   => 'application/x-tar',
        'tgz'   => 'application/x-tar',
        'text'  => 'text/plain',
        'tif'   => 'image/tiff',
        'tiff'  => 'image/tiff',
        'ts'    => 'video/MP2T',
        'txt'   => 'text/plain',
        'wav'   => 'audio/x-wav',
        'wbxml' => 'application/wbxml',
        'wmlc'  => 'application/wmlc',
        'word'  => 'application/msword',
        'xht'   => 'application/xhtml+xml',
        'xhtml' => 'application/xhtml+xml',
        'xl'    => 'application/excel',
        'xls'   => 'application/excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xml'   => 'text/xml',
        'xsl'   => 'text/xml',
        'zip'   => 'application/x-zip'
    ];

    /**
     * 根据文件后缀获取mine
     * @param string $extension 无.
     * @return string
     */
    public static function get(string $extension): string {
        return static::EXTENSION_MIME_MAPS[$extension] ?? $extension;
    }

    /**
     * 根据文件类型获取文件拓展名
     * @param string $type
     * @return string
     */
    public static function extension(string $type): string {
        $i = strpos($type, ';');
        if ($i !== false) {
            $type = substr($type, 0, $i);
        }
        $key = array_search($type, static::EXTENSION_MIME_MAPS, true);
        if (!empty($key)) {
            return $key;
        }
        $args = explode('/', $type);
        return count($args) > 1 ? $args[1] : $args[0];
    }

    /**
     * 判断 MIME 是否符合
     * @param string $input 文件的 mime
     * @param string $needle 允许的 mime, 可以多个  image/*;video/*;text/xml
     * @return bool
     */
    public static function is(string $input, string $needle): bool {
        if ($needle === '*/*' || $needle === '*' || empty($needle)) {
            return true;
        }
        if (str_starts_with($input, '.')) {
            $input = substr($input, 1);
        }
        if (!str_contains($input, '/')) {
            $input = static::get(strtolower($input));
        }
        if ($input === $needle) {
            return true;
        }
        foreach (explode(';', $needle) as $item) {
            $item = trim($item);
            if ($input === $item) {
                return true;
            }
            $i = strpos($item, '*');
            if ($i === false) {
                continue;
            }
            if ($i > 0 && !str_starts_with($input, substr($item, 0, $i))) {
                continue;
            }
            if ($i < strlen($item) - 1 && !str_ends_with($input, substr($item, $i + 1))) {
                continue;
            }
            return true;
        }
        return false;
    }
}