# Socket of PHP
[![Gitter](https://badges.gitter.im/ifehrim/Socket.svg)]()
[![License](https://poser.pugx.org/ifehrim/Socket/license)]()

## What is it
Socket is PHP framework with high performance for easily building fast, scalable network applications. Supports HTTP, SSL and other custom protocols.
PHP 7.3 or Higher  


## Installation
     use alim:
     php alim install Services
     use git:
     git clone https://github.com/ifehrim/services-case.git 


### example files:

[server ser.php](./ser.php)

[client ser.php](./cli.php)



### how to use ?

-  step:1# register server||client:
   
        //server
        Socket::ser("tcp://0.0.0.0:9999");
        //client
        Socket::cli("tcp://0.0.0.0:9999");
        
        
-  allow actions:
        
        start,conn,read,stat,close,stop or error
        
    
-  step:2# register actions:        
        
        Socket::on('start', function () {
            print 'start';
        });
        Socket::on('stat',function(){
            print json_encode(Socket::$stat);
        });
        Socket::on('read', function ($buf, Socket $soc) {
            $len=strlen($buf);
            if(is_numeric($len)&&$len>0){
              $soc->write("hello -% ");
            }
            return $len;
        });

-  step:3# event loop: 
  
       Socket::loop(function () {
           //todo something inner loop
       });