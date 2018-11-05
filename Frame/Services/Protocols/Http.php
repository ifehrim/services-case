<?php
/**
 * Created by PhpStorm.
 * User: fehrim
 * Date: 2018/11/1
 * Time: 21:30
 */

namespace Frame\Services\Protocols;


class Http
{
    /**
     * The supported HTTP methods
     * @var array
     */
    public static $methods = array('GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS');

    public static $codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );
    /**
     * @var self
     */
    public static $instance = null;
    public static $header = array();
    public static $gzip = false;
    public static $sessionPath = '';
    public static $sessionName = '';
    public static $sessionGcProbability = 1;
    public static $sessionGcDivisor = 1000;
    public static $sessionGcMaxLifeTime = 1440;
    public $sessionStarted = false;
    public $sessionFile = '';

    public static function init()
    {
        if (!self::$sessionName) {
            self::$sessionName = ini_get('session.name');
        }

        if (!self::$sessionPath) {
            self::$sessionPath = @session_save_path();
        }

        if (!self::$sessionPath || strpos(self::$sessionPath, 'tcp://') === 0) {
            self::$sessionPath = sys_get_temp_dir();
        }

        if ($gc_probability = ini_get('session.gc_probability')) {
            self::$sessionGcProbability = $gc_probability;
        }

        if ($gc_divisor = ini_get('session.gc_divisor')) {
            self::$sessionGcDivisor = $gc_divisor;
        }

        if ($gc_max_life_time = ini_get('session.gc_maxlifetime')) {
            self::$sessionGcMaxLifeTime = $gc_max_life_time;
        }
    }

    /**
     * Check the integrity of the package.
     *
     * @param string $buf
     * @return int
     */
    public static function size($buf)
    {
        if (!strpos($buf, "\r\n\r\n")) {
            return 0;
        }
        list($header,) = explode("\r\n\r\n", $buf, 2);
        $method = substr($header, 0, strpos($header, ' '));
        if (in_array($method, static::$methods)) {
            return static::getRequestSize($header, $method);
        } else {
            return "HTTP/1.1 400 Bad Request\r\n\r\n";
        }
    }

    /**
     * Get whole size of the request
     * includes the request headers and request body.
     * @param string $header The request headers
     * @param string $method The request method
     * @return integer
     */
    protected static function getRequestSize($header, $method)
    {
        if ($method === 'GET' || $method === 'OPTIONS' || $method === 'HEAD') {
            return strlen($header) + 4;
        }
        $match = array();
        if (preg_match("/\r\nContent-Length: ?(\d+)/i", $header, $match)) {
            $content_length = isset($match[1]) ? $match[1] : 0;
            return $content_length + strlen($header) + 4;
        }
        return $method === 'DELETE' ? strlen($header) + 4 : 0;
    }

    /**
     * Parse $_POST、$_GET、$_COOKIE.
     *
     * @param string $buf
     * @return array
     */
    public static function decode($buf)
    {
        // Init.
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        // Clear cache.
        self::$header = array('Connection' => 'Connection: keep-alive');
        self::$instance = new self();
        // $_SERVER
        $_SERVER = array(
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => '',
            'REQUEST_URI' => '',
            'SERVER_PROTOCOL' => '',
            'SERVER_SOFTWARE' => '',
            'SERVER_NAME' => '',
            'HTTP_HOST' => '',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => '',
            'HTTP_CONNECTION' => '',
            'CONTENT_TYPE' => '',
            'REMOTE_ADDR' => '',
            'REMOTE_PORT' => '0',
            'REQUEST_TIME' => time()
        );

        // Parse headers.
        list($http_header, $http_body) = explode("\r\n\r\n", $buf, 2);
        $header_data = explode("\r\n", $http_header);

        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header_data[0]);

        $http_post_boundary = '';
        unset($header_data[0]);
        foreach ($header_data as $content) {
            // \r\n\r\n
            if (empty($content)) {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = str_replace('-', '_', strtoupper($key));
            $value = trim($value);
            $_SERVER['HTTP_' . $key] = $value;
            switch ($key) {
                // HTTP_HOST
                case 'HOST':
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1])) {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'COOKIE':
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // content-type
                case 'CONTENT_TYPE':
                    if (!preg_match('/boundary="?(\S+)"?/', $value, $match)) {
                        if ($pos = strpos($value, ';')) {
                            $_SERVER['CONTENT_TYPE'] = substr($value, 0, $pos);
                        } else {
                            $_SERVER['CONTENT_TYPE'] = $value;
                        }
                    } else {
                        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
                        $http_post_boundary = '--' . $match[1];
                    }
                    break;
                case 'CONTENT_LENGTH':
                    $_SERVER['CONTENT_LENGTH'] = $value;
                    break;
            }
        }
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
            self::$gzip = true;
        }
        // Parse $_POST.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_SERVER['CONTENT_TYPE'])) {
                switch ($_SERVER['CONTENT_TYPE']) {
                    case 'multipart/form-data':
                        self::parseUploadFiles($http_body, $http_post_boundary);
                        break;
                    case 'application/json':
                        $_POST = json_decode($http_body, true);
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str($http_body, $_POST);
                        break;
                }
            }
        }

        // Parse other HTTP action parameters
        if ($_SERVER['REQUEST_METHOD'] != 'GET' && $_SERVER['REQUEST_METHOD'] != "POST") {
            $data = array();
            if ($_SERVER['CONTENT_TYPE'] === "application/x-www-form-urlencoded") {
                parse_str($http_body, $data);
            } elseif ($_SERVER['CONTENT_TYPE'] === "application/json") {
                $data = json_decode($http_body, true);
            }
            $_REQUEST = array_merge($_REQUEST, $data);
        }

        // HTTP_RAW_REQUEST_DATA HTTP_RAW_POST_DATA
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $http_body;

        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($_SERVER['QUERY_STRING']) {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        } else {
            $_SERVER['QUERY_STRING'] = '';
        }

        if (is_array($_POST)) {
            // REQUEST
            $_REQUEST = array_merge($_GET, $_POST, $_REQUEST);
        } else {
            // REQUEST
            $_REQUEST = array_merge($_GET, $_REQUEST);
        }

        return array('get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'server' => $_SERVER, 'files' => $_FILES);
    }

    /**
     * Http encode.
     *
     * @param string $content
     * @return string
     */
    public static function encode($content)
    {
        // Default http-code.
        if (!isset(self::$header['Http-Code'])) {
            $header = "HTTP/1.1 200 OK\r\n";
        } else {
            $header = self::$header['Http-Code'] . "\r\n";
            unset(self::$header['Http-Code']);
        }

        // Content-Type
        if (!isset(self::$header['Content-Type'])) {
            $header .= "Content-Type: text/html;charset=utf-8\r\n";
        }

        // other headers
        foreach (self::$header as $key => $item) {
            if ('Set-Cookie' === $key && is_array($item)) {
                foreach ($item as $it) {
                    $header .= $it . "\r\n";
                }
            } else {
                $header .= $item . "\r\n";
            }
        }
        if (self::$gzip) {
            $header .= "Content-Encoding: gzip\r\n";
            $content = gzencode($content);
        }
        // header
        $header .= "Server: \r\nContent-Length: " . strlen($content) . "\r\n\r\n";

        // save session
        self::sessionWriteClose();

        // the whole http package
        return $header . $content;
    }

    /**
     * 设置http头
     *
     * @return bool|void
     */
    public static function header($content, $replace = true, $http_response_code = 0)
    {
        if (PHP_SAPI != 'cli') {
            return $http_response_code ? header($content, $replace, $http_response_code) : header($content, $replace);
        }
        if (strpos($content, 'HTTP') === 0) {
            $key = 'Http-Code';
        } else {
            $key = strstr($content, ":", true);
            if (empty($key)) {
                return false;
            }
        }

        if ('location' === strtolower($key) && !$http_response_code) {
            return self::header($content, true, 302);
        }

        if (isset(self::$codes[$http_response_code])) {
            self::$header['Http-Code'] = "HTTP/1.1 $http_response_code " . self::$codes[$http_response_code];
            if ($key === 'Http-Code') {
                return true;
            }
        }

        if ($key === 'Set-Cookie') {
            self::$header[$key][] = $content;
        } else {
            self::$header[$key] = $content;
        }

        return true;
    }

    /**
     * Remove header.
     *
     * @param string $name
     * @return void
     */
    public static function headerRemove($name)
    {
        if (PHP_SAPI != 'cli') {
            header_remove($name);
            return;
        }
        unset(self::$header[$name]);
    }

    /**
     * Set cookie.
     *
     * @param string $name
     * @param string $value
     * @param integer $maxage
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $HTTPOnly
     * @return bool|void
     */
    public static function setcookie(
        $name,
        $value = '',
        $maxage = 0,
        $path = '',
        $domain = '',
        $secure = false,
        $HTTPOnly = false
    )
    {
        if (PHP_SAPI != 'cli') {
            return setcookie($name, $value, $maxage, $path, $domain, $secure, $HTTPOnly);
        }
        return self::header(
            'Set-Cookie: ' . $name . '=' . rawurlencode($value)
            . (empty($domain) ? '' : '; Domain=' . $domain)
            . (empty($maxage) ? '' : '; Max-Age=' . $maxage)
            . (empty($path) ? '' : '; Path=' . $path)
            . (!$secure ? '' : '; Secure')
            . (!$HTTPOnly ? '' : '; HttpOnly'), false);
    }

    /**
     * sessionCreateId
     *
     * @return string
     */
    public static function sessionCreateId()
    {
        mt_srand();
        return bin2hex(pack('d', microtime(true)) . pack('N', mt_rand(0, 2147483647)));
    }

    /**
     * sessionId
     *
     * @param string $id
     *
     * @return string|null
     */
    public static function sessionId($id = null)
    {
        if (PHP_SAPI != 'cli') {
            return $id ? session_id($id) : session_id();
        }
        if (static::sessionStarted() && self::$instance->sessionFile) {
            return str_replace('sess_', '', basename(self::$instance->sessionFile));
        }
        return '';
    }

    /**
     * sessionName
     *
     * @param string $name
     *
     * @return string
     */
    public static function sessionName($name = null)
    {
        if (PHP_SAPI != 'cli') {
            return $name ? session_name($name) : session_name();
        }
        $session_name = self::$sessionName;
        if ($name && !static::sessionStarted()) {
            self::$sessionName = $name;
        }
        return $session_name;
    }

    /**
     * sessionSavePath
     *
     * @param string $path
     *
     * @return void
     */
    public static function sessionSavePath($path = null)
    {
        if (PHP_SAPI != 'cli') {
            return $path ? session_save_path($path) : session_save_path();
        }
        if ($path && is_dir($path) && is_writable($path) && !static::sessionStarted()) {
            self::$sessionPath = $path;
        }
        return self::$sessionPath;
    }

    /**
     * sessionStarted
     *
     * @return bool
     */
    public static function sessionStarted()
    {
        if (!self::$instance) return false;

        return self::$instance->sessionStarted;
    }

    /**
     * sessionStart
     *
     * @return bool
     */
    public static function sessionStart()
    {
        if (PHP_SAPI != 'cli') {
            return session_start();
        }

        self::tryGcSessions();

        if (self::$instance->sessionStarted) {
            return true;
        }
        self::$instance->sessionStarted = true;
        // Generate a SID.
        if (!isset($_COOKIE[self::$sessionName]) || !is_file(self::$sessionPath . '/sess_' . $_COOKIE[self::$sessionName])) {
            // Create a unique session_id and the associated file name.
            while (true) {
                $session_id = static::sessionCreateId();
                if (!is_file($file_name = self::$sessionPath . '/sess_' . $session_id)) break;
            }
            self::$instance->sessionFile = $file_name;
            return self::setcookie(
                self::$sessionName
                , $session_id
                , ini_get('session.cookie_lifetime')
                , ini_get('session.cookie_path')
                , ini_get('session.cookie_domain')
                , ini_get('session.cookie_secure')
                , ini_get('session.cookie_httponly')
            );
        }
        if (!self::$instance->sessionFile) {
            self::$instance->sessionFile = self::$sessionPath . '/sess_' . $_COOKIE[self::$sessionName];
        }
        // Read session from session file.
        if (self::$instance->sessionFile) {
            $raw = file_get_contents(self::$instance->sessionFile);
            if ($raw) {
                $_SESSION = unserialize($raw);
            }
        }
        return true;
    }

    /**
     * Save session.
     *
     * @return bool
     */
    public static function sessionWriteClose()
    {
        if (PHP_SAPI != 'cli') {
            session_write_close();
            return true;
        }
        if (!empty(self::$instance->sessionStarted) && !empty($_SESSION)) {
            $session_str = serialize($_SESSION);
            if ($session_str && self::$instance->sessionFile) {
                return file_put_contents(self::$instance->sessionFile, $session_str);
            }
        }
        return empty($_SESSION);
    }

    /**
     * End, like call exit in php-fpm.
     *
     * @param string $msg
     * @throws \Exception
     */
    public static function end($msg = '')
    {
        if (PHP_SAPI != 'cli') {
            exit($msg);
        }
        if ($msg) {
            echo $msg;
        }
        throw new \Exception('jump_exit');
    }

    /**
     * Get mime types.
     *
     * @return string
     */
    public static function getMimeTypesFile()
    {
        return 'types {
    text/html                             html htm shtml;
    text/css                              css;
    text/xml                              xml;
    image/gif                             gif;
    image/jpeg                            jpeg jpg;
    application/x-javascript              js;
    application/atom+xml                  atom;
    application/rss+xml                   rss;

    text/mathml                           mml;
    text/plain                            txt;
    text/vnd.sun.j2me.app-descriptor      jad;
    text/vnd.wap.wml                      wml;
    text/x-component                      htc;

    image/png                             png;
    image/tiff                            tif tiff;
    image/vnd.wap.wbmp                    wbmp;
    image/x-icon                          ico;
    image/x-jng                           jng;
    image/x-ms-bmp                        bmp;
    image/svg+xml                         svg svgz;
    image/webp                            webp;

    application/java-archive              jar war ear;
    application/mac-binhex40              hqx;
    application/msword                    doc;
    application/pdf                       pdf;
    application/postscript                ps eps ai;
    application/rtf                       rtf;
    application/vnd.ms-excel              xls;
    application/vnd.ms-powerpoint         ppt;
    application/vnd.wap.wmlc              wmlc;
    application/vnd.google-earth.kml+xml  kml;
    application/vnd.google-earth.kmz      kmz;
    application/x-7z-compressed           7z;
    application/x-cocoa                   cco;
    application/x-java-archive-diff       jardiff;
    application/x-java-jnlp-file          jnlp;
    application/x-makeself                run;
    application/x-perl                    pl pm;
    application/x-pilot                   prc pdb;
    application/x-rar-compressed          rar;
    application/x-redhat-package-manager  rpm;
    application/x-sea                     sea;
    application/x-shockwave-flash         swf;
    application/x-stuffit                 sit;
    application/x-tcl                     tcl tk;
    application/x-x509-ca-cert            der pem crt;
    application/x-xpinstall               xpi;
    application/xhtml+xml                 xhtml;
    application/zip                       zip;

    application/octet-stream              bin exe dll;
    application/octet-stream              deb;
    application/octet-stream              dmg;
    application/octet-stream              eot;
    application/octet-stream              iso img;
    application/octet-stream              msi msp msm;

    audio/midi                            mid midi kar;
    audio/mpeg                            mp3;
    audio/ogg                             ogg;
    audio/x-m4a                           m4a;
    audio/x-realaudio                     ra;

    video/3gpp                            3gpp 3gp;
    video/mp4                             mp4;
    video/mpeg                            mpeg mpg;
    video/quicktime                       mov;
    video/webm                            webm;
    video/x-flv                           flv;
    video/x-m4v                           m4v;
    video/x-mng                           mng;
    video/x-ms-asf                        asx asf;
    video/x-ms-wmv                        wmv;
    video/x-msvideo                       avi;
}';
    }

    /**
     * Parse $_FILES.
     *
     * @param string $http_body
     * @param string $http_post_boundary
     * @return void
     */
    protected static function parseUploadFiles($http_body, $http_post_boundary)
    {
        $http_body = substr($http_body, 0, strlen($http_body) - (strlen($http_post_boundary) + 4));
        $boundary_data_array = explode($http_post_boundary . "\r\n", $http_body);
        if ($boundary_data_array[0] === '') {
            unset($boundary_data_array[0]);
        }
        $key = -1;
        foreach ($boundary_data_array as $boundary_data_buffer) {
            list($boundary_header_buffer, $boundary_value) = explode("\r\n\r\n", $boundary_data_buffer, 2);
            // Remove \r\n from the end of buffer.
            $boundary_value = substr($boundary_value, 0, -2);
            $key++;
            foreach (explode("\r\n", $boundary_header_buffer) as $item) {
                list($header_key, $header_value) = explode(": ", $item);
                $header_key = strtolower($header_key);
                switch ($header_key) {
                    case "content-disposition":
                        // Is file data.
                        if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $header_value, $match)) {
                            // Parse $_FILES.
                            $_FILES[$key] = array(
                                'name' => $match[1],
                                'file_name' => $match[2],
                                'file_data' => $boundary_value,
                                'file_size' => strlen($boundary_value),
                            );
                            continue;
                        } // Is post field.
                        else {
                            // Parse $_POST.
                            if (preg_match('/name="(.*?)"$/', $header_value, $match)) {
                                $_POST[$match[1]] = $boundary_value;
                            }
                        }
                        break;
                    case "content-type":
                        // add file_type
                        $_FILES[$key]['file_type'] = trim($header_value);
                        break;
                }
            }
        }
    }

    /**
     * Try GC sessions.
     *
     * @return void
     */
    public static function tryGcSessions()
    {
        if (self::$sessionGcProbability <= 0 ||
            self::$sessionGcDivisor <= 0 ||
            rand(1, self::$sessionGcDivisor) > self::$sessionGcProbability
        ) {
            return;
        }

        $time_now = time();
        foreach (glob(self::$sessionPath . '/ses*') as $file) {
            if (is_file($file) && $time_now - filemtime($file) > self::$sessionGcMaxLifeTime) {
                unlink($file);
            }
        }
    }
}