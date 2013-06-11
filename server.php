<?php
$host = '192.168.1.10'; //host
$port = '9000'; //port
$null = NULL; //null var

//Create TCP/IP sream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket to specified host
socket_bind($socket, 0, $port);

//listen to port
socket_listen($socket);

//create & add listning socket to the list
$clients = array($socket);
$usernames = array();
//start endless loop, so that our script doesn't stop
      function emoticons($text) {
           $icons = array(
						  ':)'    =>  '<img src="emotions-fb/smile.gif" class="emo1"/>',
                          'O:)'   =>  '<img src="emotions-fb/angel.gif" class="emo1"/>',
						  ':3'    =>  '<img src="emotions-fb/colonthree.gif" class="emo1"/>',
                          'o.0'   =>  '<img src="emotions-fb/confused.gif" class="emo1"/>',
						  'o.O'   =>  '<img src="emotions-fb/confused.gif" class="emo1"/>',
						  ":')"   =>  '<img src="emotions-fb/cry.gif" class="emo1"/>',
						  "3:)"   =>  '<img src="emotions-fb/devil.gif" class="emo1"/>',
						  ":("    =>  '<img src="emotions-fb/frown.gif" class="emo1"/>',
						  '8)'    =>  '<img src="emotions-fb/glasses.gif" class="emo1"/>',
                          ':D'    =>  '<img src="emotions-fb/grin.gif" class="emo1"/>',
						  '>:('   =>  '<img src="emotions-fb/grumpy.gif" class="emo1"/>',
                          '<3'    =>  '<img src="emotions-fb/heart.gif" class="emo1"/>',
						  'kiki'  =>  '<img src="emotions-fb/^_^.gif" class="emo1"/>',
						  ":*"    =>  '<img src="emotions-fb/kiss.gif" class="emo1"/>',
						  ":v"    =>  '<img src="emotions-fb/pacman.gif" class="emo1"/>',
						  ":("    =>  '<img src="emotions-fb/frown.gif" class="emo1"/>',
						  '-_-'    =>  '<img src="emotions-fb/squint.gif" class="emo1"/>',
						  '8|'   =>  '<img src="emotions-fb/sunglasses.gif" class="emo1"/>',
                          ':p'    =>  '<img src="emotions-fb/tongue.gif" class="emo1"/>',
						  ':P'  =>  '<img src="emotions-fb/tongue.gif" class="emo1"/>',
						  ":/"    =>  '<img src="emotions-fb/unsure.gif" class="emo1"/>',
						  ">:O"    =>  '<img src="emotions-fb/upset.gif" class="emo1"/>',
						  ";)"    =>  '<img src="emotions-fb/wink.gif" class="emo1"/>',
						  ); 
            $text = " ".$text." ";       
            foreach ($icons as $search => $replace){
             $text = str_replace(" ".$search." ", " ".$replace." ", $text);
            }
			echo $text;
           return trim($text);
      }
while (true) {
	//manage multipal connections
	$changed = $clients;
	//returns the socket resources in $changed array
	socket_select($changed, $null, $null, 0, 10);
	
	//check for new socket
	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket); //accpet new socket
		$clients[] = $socket_new; //add socket to client array
		
		$header = socket_read($socket_new, 1024); //read data sent by the socket
		perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake
		
		socket_getpeername($socket_new, $ip); //get ip address of connected socket
		$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER: '.$ip.' connected'))); //prepare json data
		send_message($response); //notify all users about new connection
		
		//make room for new socket
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
	}
	
	//loop through all connected sockets
	foreach ($changed as $changed_socket) {	
		
		//check for any incomming data
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
			$received_text = unmask($buf); //unmask data
			$tst_msg = json_decode($received_text); //json decode 
			$user_name = $tst_msg->name; //sender name
			$user_message = $tst_msg->message; //message text
			$user_color = $tst_msg->color; //color
			$usernames[substr((string)$changed_socket, -1)]['username'] = $user_name;
			print_r($usernames);
			//prepare data to be sent to client
			$user_message = emoticons($user_message);
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
			$count = count($clients)-1;
			if ($user_message == "/players"){
						$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER: '.$count.' users are connected:'.implode(',', array_map(function($usernames){ return $usernames['username']; }, $usernames)))));
						@socket_write($changed_socket,$response,strlen($response));
				break 2;
			}
			if ($user_message == "/whoami"){
				$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER:'.$user_name)));
				@socket_write($changed_socket,$response,strlen($response));
				break 2;
			}		
			send_message($response_text); //send data
			break 2; //exist this loop
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { // check disconnected client
			// remove client for $clients array
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
			//notify all users about disconnected connection
			$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER: '.$usernames[substr((string)$changed_socket, -1)]['username'].' disconnected')));
			unset($usernames[$changed_socket]);
			send_message($response);
		}
	}
}
// close the listening socket
socket_close($sock);

function send_message($msg)
{
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}

//Unmask incoming framed message
function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

//Encode message for transfer to client.
function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

//handshake new client.
function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}