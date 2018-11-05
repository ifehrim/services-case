<?php
/**
 * Created by IntelliJ IDEA.
 * User: pc
 * Date: 11/5/2018
 * Time: 16:02
 */


use Frame\Services\Socket;

include 'Frame/Services/Socket.php';

Socket::on('error',function ($msg,$err=null){
    print $msg;
    print $err;
});

$so = Socket::cli("tcp://127.0.0.1:9999");
if ($so instanceof Socket) {
    $so->write("i'm a client X1;");
    Socket::on('read', function ($buf, Socket $socket) {
        print $buf;
        $socket->close("byb!");
        die;
    });
    Socket::loop();
}
