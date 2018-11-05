<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 10/30/2018
 * Time: 15:37
 */

namespace Frame\Services;


class Socket
{
    public static $conn = [];
    public static $fds = [
        'read' => [],
        'write' => [],
        'expect' => [],
    ];
    public static $stat = [
        'count' => ['c' => 0, 'r' => 0, 'w' => 0, 'f' => 0],
        'bit' => ['r' => 0, 'w' => 0],
        'bit-s' => ['r' => 0, 'w' => 0],
        'speed' => ['r' => 0, 'w' => 0],
    ];
    protected static $is = 'ser';
    /**
     * @var self
     */
    public static $_mainSer;
    /**
     * @var self
     */
    public static $_mainCli;
    protected static $_context = [];
    protected static $transport = 'tcp';
    protected static $calls;
    protected static $channel;
    protected static $stat_time = 0;
    protected static $url;
    protected $_socket;
    public $_fd;
    protected $_isMaster = false;
    protected $options = "";
    protected $_ssl = false;
    protected $_sendBuffer = '';
    protected $_recvBuffer = '';

    public static function ser($url = "tcp://127.0.0.1:8000", $_context = [])
    {
        if (empty(static::$_mainSer)) {
            self::$url=$url;
           $parse = parse_url($url);
            self::$transport = $parse['scheme'];
            $flags = self::$transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
            self::$_context = array_merge(self::$_context, $_context);
            $url = str_replace(['ssl', 'http'], 'tcp', $url);
            $_mainSer = @stream_socket_server($url, $err_no, $err_msg, $flags, stream_context_create(self::$_context));
            if (!$_mainSer) {
                static::off('error', '_mainSer::', $err_msg);
                return;
            }

            if (self::$transport === 'ssl') {
                @stream_socket_enable_crypto($_mainSer, false);
            }
            if (self::$transport === 'tcp') {
                $socket = @socket_import_stream($_mainSer);
                @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            }
            static::$_mainSer = new static($_mainSer, null, true);
        }
    }

    public static function cli($url = "tcp://127.0.0.1:8000", $_context = [])
    {
        self::$is = 'cli';
        if (empty(self::$_mainCli)) {
            $_mainCli = @stream_socket_client($url, $err_no, $err_msg, 50);
            if (!$_mainCli) {
                static::off('error', '_mainCli::', $err_msg);
                return null;
            }
            self::$_mainCli = new static($_mainCli, null, true);
        }
        return self::$_mainCli;
    }


    public function __construct($socket, $options = null, $isMaster = false)
    {
        if (is_array($socket)) {
            list($url, $_context) = $socket;
            self::ser($url, $_context);
        } else {
            $this->_socket = $socket;
            $this->options = $options;
            static::stat('conn');
            if (self::$transport == 'udp' && !$isMaster) return;
            $this->_fd = (int)$socket;
            stream_set_blocking($this->_socket, 0);
            if (!$isMaster) stream_set_read_buffer($this->_socket, 0);
            $this->_isMaster = $isMaster;
            static::$conn[$this->_fd] = $this;
            static::$fds['read'][$this->_fd] = $this->_socket;
            static::off('conn', $this);
        }
    }

    public static function on($func,callable $call=null)
    {
        self::$calls[$func] = $call;
    }

    public static function off($func, ...$params)
    {
        $res = null;
        if (isset(self::$calls[$func])) {
            try {
                $res = call_user_func_array(self::$calls[$func], $params);
            } catch (\Exception $e) {
                $tag = $func;
                $func = "error";
                if (isset(self::$calls[$func])) {
                    call_user_func(self::$calls[$func], $tag, $e, $params);
                }
            }
        }
        return $res;
    }


    public function accept()
    {
        $socket = $this->_socket;
        if (self::$transport == 'udp') {
            $buffer = stream_socket_recvfrom($socket, 65535, 0, $option);
            $o = new static($socket, $option);
            if (false === $buffer || empty($option)) {
                return false;
            }
            static::off('read', $buffer, $o);
        } else {
            $new_socket = @stream_socket_accept($socket, 0, $option);
            if (!$new_socket) return false;
            new static($new_socket, $option);
        }
    }

    public function read($check_eof = true)
    {
        $socket = $this->_socket;
        if (self::$transport === 'ssl' && $this->_ssl !== true) {
            $ret = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_SSLv2_SERVER | STREAM_CRYPTO_METHOD_SSLv23_SERVER);
            if ($ret) $this->_ssl = true;
            static::off('ssl', $this, $ret);
            if (!$ret) return;
        }
        $buffer = @fread($socket, 65535);
        $len = strlen($buffer);
        static::stat('read', $len);
        if ($buffer === '' || $buffer === false) {
            if ($check_eof && (feof($socket) || !is_resource($socket) || $buffer === false)) {
                $this->close();
                return;
            }
        } else {
            $this->_recvBuffer .= $buffer;
        }

        while ($this->_recvBuffer !== '') {
            $len = static::off('read', $this->_recvBuffer, $this);
            if (is_numeric($len) && $len > 0) {
                if (strlen($this->_recvBuffer) === $len) {
                    $this->_recvBuffer = '';
                } else {
                    $this->_recvBuffer = substr($this->_recvBuffer, $len);
                }
            } else {
                $this->_recvBuffer = '';
                break;
            }
        }
        return;
    }


    public function write($send_buffer = "")
    {
        $this->_sendBuffer .= $send_buffer;
        $_len = strlen($this->_sendBuffer);
        if (self::$transport == 'udp') {
            $len = stream_socket_sendto($this->_socket, $this->_sendBuffer, 0, $this->options);
            return $_len === $len;
        }
        if (self::$transport === 'ssl') {
            $len = @fwrite($this->_socket, $this->_sendBuffer, 8192);
        } else {
            $len = @fwrite($this->_socket, $this->_sendBuffer);
        }

        static::stat('write', $len);

        if ($len === $_len) {
            $this->_sendBuffer = '';
            return true;
        } else {
            if ($len > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $len);
            } else {
                static::stat('fail');
                $this->close();
            }
        }
        return false;
    }


    public static function loop($call = null)
    {
        if (is_callable($call) && !empty($call)) static::on('loop', $call);

        static::off('start',self::$url);
//        static::$channel = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
//        if (static::$channel) {
//            stream_set_blocking(static::$channel[0], 0);
//            static::$fds['read'][0] = static::$channel[0];
//        }
        while (true) {

            static::off('loop');

            $read = static::$fds['read'];
            $write = static::$fds['write'];
            $except = static::$fds['expect'];

            static::stat('loop');

            if (self::$is == 'ser') {
                if (is_object(self::$_mainSer)&&!is_resource(self::$_mainSer->_socket)) break;
            } else {
                if (is_object(self::$_mainSer)&&!is_resource(self::$_mainCli->_socket)) break;
            }

            $fd = @stream_select($read, $write, $except, 0);
            if (!$fd) continue;

            foreach ($read as $fd) {
                $fd = (int)$fd;
                if (isset(static::$conn[$fd])) {
                    $self = static::$conn[$fd];
                    if ($self instanceof self) {
                        if ($self->_isMaster && self::$is == 'ser') {
                            $self->accept();
                        } else {
                            $self->read();
                        }
                    }
                }
            }

        }
        static::off('stop');
    }

    public static function bit($bytes = 0)
    {
        if ($bytes > 1024 * 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024 * 1024), 1) . "TB";
        }
        if ($bytes > 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1) . "GB";
        }
        if ($bytes > 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . "MB";
        }
        if ($bytes > 1024) {
            return round($bytes / (1024), 1) . "KB";
        }
        return $bytes . "B";
    }

    public function close($buf = null)
    {

        static::stat('close');
        static::off('close', $this);

        if (!empty($buf)) $this->write($buf);

        if (self::$transport == 'udp') return;

        set_error_handler(function () {
        });
        $fd = (int)$this->_socket;
        fclose($this->_socket);
        unset(self::$fds['read'][$fd]);
        unset(self::$fds['write'][$fd]);
        unset(self::$fds['expect'][$fd]);
        unset(static::$conn[$fd]);
        restore_error_handler();
    }


    public static function destroy()
    {
        self::$_mainSer = null;
        self::$_mainCli = null;
        self::$conn = [];
        self::$fds = ['read' => [], 'write' => [], 'expect' => [],];
        self::$stat = [
            'count' => ['c' => 0, 'r' => 0, 'w' => 0, 'f' => 0],
            'bit' => ['r' => 0, 'w' => 0],
            'bit-s' => ['r' => 0, 'w' => 0],
            'speed' => ['r' => 0, 'w' => 0],
        ];
    }

    public static function stat($type = null, $len = 0)
    {
        if (!isset(self::$calls['stat'])) return;
        switch ($type) {
            case 'conn':
                self::$stat['count']['c']++;
                break;
            case 'close':
                self::$stat['count']['c']--;
                break;
            case 'read':
                self::$stat['bit-s']['r'] += $len;
                self::$stat['bit']['r'] += $len;
                self::$stat['count']['r']++;
                break;
            case 'write':
                self::$stat['bit-s']['w'] += $len;
                self::$stat['bit']['w'] += $len;
                self::$stat['count']['w']++;
                break;
            case 'fail':
                self::$stat['count']['f']++;
                break;
            case 'loop':
                if (self::$stat_time <= time()-60) {
                    self::$stat_time = time();
                    self::$stat['speed']['r'] = self::bit(self::$stat['bit-s']['r']) . "(s)";
                    self::$stat['speed']['w'] = self::bit(self::$stat['bit-s']['w']) . "(s)";
                    $stat = self::$stat;
                    static::off('stat',$stat);
                    unset($stat['bit-s']);
                    self::$stat['bit-s']['r'] = 0;
                    self::$stat['bit-s']['w'] = 0;
                }
                break;
        }
    }


}