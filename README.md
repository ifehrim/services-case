# Socket of PHP
[![Gitter](https://badges.gitter.im/ifehrim/Socket.svg)]()
[![License](https://poser.pugx.org/ifehrim/Socket/license)]()

## What is it
Socket is PHP framework with high performance for easily building fast, scalable network applications. Supports HTTP, SSL and other custom protocols.
PHP 7.0 or Higher  


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
       
       
       
test result single process:

    adem@ubuntu:~$ ab -n1000000 -c100 -k http://0.0.0.0:9999/
    This is ApacheBench, Version 2.3 <$Revision: 1706008 $>
    Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
    Licensed to The Apache Software Foundation, http://www.apache.org/
    
    Benchmarking 0.0.0.0 (be patient)
    Completed 100000 requests
    Completed 200000 requests
    Completed 300000 requests
    Completed 400000 requests
    Completed 500000 requests
    Completed 600000 requests
    Completed 700000 requests
    Completed 800000 requests
    Completed 900000 requests
    Completed 1000000 requests
    Finished 1000000 requests
    
    
    Server Software:
    Server Hostname:        0.0.0.0
    Server Port:            9999
    
    Document Path:          /
    Document Length:        5 bytes
    
    Concurrency Level:      100
    Time taken for tests:   14.665 seconds
    Complete requests:      1000000
    Failed requests:        0
    Keep-Alive requests:    1000000
    Total transferred:      116000000 bytes
    HTML transferred:       5000000 bytes
    Requests per second:    68189.94 [#/sec] (mean)
    Time per request:       1.466 [ms] (mean)
    Time per request:       0.015 [ms] (mean, across all concurrent requests)
    Transfer rate:          7724.64 [Kbytes/sec] received
    
    Connection Times (ms)
                  min  mean[+/-sd] median   max
    Connect:        0    0   0.1      0       9
    Processing:     0    1   1.1      1      26
    Waiting:        0    1   1.1      1      26
    Total:          0    1   1.1      1      26
    
    Percentage of the requests served within a certain time (ms)
      50%      1
      66%      2
      75%      2
      80%      2
      90%      3
      95%      3
      98%      4
      99%      5
     100%     26 (longest request)


test result multi process(3): (use alim Forker) 

    adem@ubuntu:~$ ab -n1000000 -c100 -k http://0.0.0.0:9999/
    This is ApacheBench, Version 2.3 <$Revision: 1706008 $>
    Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
    Licensed to The Apache Software Foundation, http://www.apache.org/
    
    Benchmarking 0.0.0.0 (be patient)
    Completed 100000 requests
    Completed 200000 requests
    Completed 300000 requests
    Completed 400000 requests
    Completed 500000 requests
    Completed 600000 requests
    Completed 700000 requests
    Completed 800000 requests
    Completed 900000 requests
    Completed 1000000 requests
    Finished 1000000 requests
    
    
    Server Software:        Socket
    Server Hostname:        0.0.0.0
    Server Port:            9999
    
    Document Path:          /
    Document Length:        5 bytes
    
    Concurrency Level:      100
    Time taken for tests:   6.390 seconds
    Complete requests:      1000000
    Failed requests:        0
    Keep-Alive requests:    1000000
    Total transferred:      90000000 bytes
    HTML transferred:       5000000 bytes
    Requests per second:    156490.58 [#/sec] (mean)
    Time per request:       0.639 [ms] (mean)
    Time per request:       0.006 [ms] (mean, across all concurrent requests)
    Transfer rate:          13754.05 [Kbytes/sec] received
    
    Connection Times (ms)
                  min  mean[+/-sd] median   max
    Connect:        0    0   0.0      0       4
    Processing:     0    1   0.4      1      15
    Waiting:        0    1   0.4      1      15
    Total:          0    1   0.4      1      15
    
    Percentage of the requests served within a certain time (ms)
      50%      1
      66%      1
      75%      1
      80%      1
      90%      1
      95%      1
      98%      1
      99%      2
     100%     15 (longest request)

