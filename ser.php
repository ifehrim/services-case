<?php
/**
 * Created by IntelliJ IDEA.
 * User: pc
 * Date: 11/5/2018
 * Time: 15:57
 */

use Frame\Services\Protocols\Http;
use Frame\Services\Socket;

include 'Frame/Services/Socket.php';
include 'Frame/Services/Protocols/Http.php';


Socket::ser("tcp://0.0.0.0:9999");
Socket::on('start', function ($bind = null) {
    print "start:{$bind}\n";
});

Socket::on('stat', function () {
    print "stat:" . json_encode(Socket::$stat) . "\n";
});

Socket::on('read', function ($buf, Socket $soc) {
    $soc->write("HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nServer:Socket Of PHP\r\nContent-Length: 5\r\n\r\nhello");
});
Socket::loop();