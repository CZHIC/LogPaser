<?php

 $client = new Swoole\Client(SWOOLE_SOCK_UDP); 
 $client->connect('127.0.0.1', 9503, 1); 
 $i = 0; 

 $str = '[11211,1001,1,0,1559555403,0,0,0]' ; 
 $client->send($str);
 $message = $client->recv();
 echo "Get Message From Server:{$message}\n"; 
 	
 
