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
			$channel = $tst_msg->channel; //channel
			$usernames[substr((string)$changed_socket, -1)]['username'] = $user_name;
			$usernames[substr((string)$changed_socket, -1)]['channel'] = $channel;
			//prepare data to be sent to client
			
			$user_message = emoticons($user_message);
			$user_message = url($user_message);
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
			$count = count($clients)-1;
			
			if ($user_message == "/allplayers")
			{
				$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER: '.$count.' users are connected:'.implode(',', array_map(function($usernames){ return $usernames['username'].": ".$usernames['channel']; }, $usernames)))));
				@socket_write($changed_socket,$response,strlen($response));
				break 2;
			}

			if ($user_message == "/connection")
			{
				$response = mask(json_encode(array('type'=>'systemConnection', 'message'=>'SERVER: '.$user_name.' Connected To Channel '.$channel.''))); //prepare json data
				send_message($response,$channel);
				break 2;
			}
			if ($user_message == "/whoami")
			{
				$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER:'.$user_name)));
				@socket_write($changed_socket,$response,strlen($response));
				break 2;
			}

			if (substr($user_message, 0, 1) === '/') 
			{
   			 	$response = mask(json_encode(array('type'=>'system', 'message'=>'SERVER: Command Not Fount')));
				@socket_write($changed_socket,$response,strlen($response));
				break 2;
			}

			send_message($response_text,$channel); //send data
			break 2; //exist this loop
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { // check disconnected client
			// remove client for $clients array
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
			//notify all users about disconnected connection
			$response = mask(json_encode(array('type'=>'systemConnection', 'message'=>'SERVER: '.$usernames[substr((string)$changed_socket, -1)]['username'].' disconnected')));
			unset($usernames[$changed_socket]);
			send_message($response,$channel);
		}
	}
}
// close the listening socket
socket_close($sock);

function send_message($msg,$channel='Main')
{
	global $clients;
	global $usernames;
	foreach($clients as $changed_socket)
	{	
		if(isset($usernames[substr((string)$changed_socket, -1)]['channel'])){
			$channel1 = $usernames[substr((string)$changed_socket, -1)]['channel'];
		}
		else
		{
			$channel1 = "Main1";
		}
		if ($channel == $channel1){
			@socket_write($changed_socket,$msg,strlen($msg));
		}
		
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

function url($text)
{
  return  preg_replace(
     array(
       '/(?(?=<a[^>]*>.+<\/a>)
             (?:<a[^>]*>.+<\/a>)
             |
             ([^="\']?)((?:https?|ftp|bf2|):\/\/[^<> \n\r]+)
         )/iex',
       '/<a([^>]*)target="?[^"\']+"?/i',
       '/<a([^>]+)>/i',
       '/(^|\s)(www.[^<> \n\r]+)/iex',
       '/(([_A-Za-z0-9-]+)(\\.[_A-Za-z0-9-]+)*@([A-Za-z0-9-]+)
       (\\.[A-Za-z0-9-]+)*)/iex'
       ),
     array(
       "stripslashes((strlen('\\2')>0?'\\1<a href=\"\\2\">\\2</a>\\3':'\\0'))",
       '<a\\1',
       '<a\\1 target="_blank">',
       "stripslashes((strlen('\\2')>0?'\\1<a href=\"http://\\2\">\\2</a>\\3':'\\0'))",
       "stripslashes((strlen('\\2')>0?'<a href=\"mailto:\\0\">\\0</a>':'\\0'))"
       ),
       $text
   );
}

function youtube($url) {
preg_match(
        '/[\\?\\&]v=([^\\?\\&]+)/',
        $url,
        $matches
    );
$id = $matches[1];
 
$width = '640';
$height = '385';
return '<object width="' . $width . '" height="' . $height . '"><param name="movie" value="http://www.youtube.com/v/' . $id . '&amp;hl=en_US&amp;fs=1?rel=0"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/' . $id . '&amp;hl=en_US&amp;fs=1?rel=0" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="' . $width . '" height="' . $height . '"></embed></object>';
}

function emoticons($text) {
$remove = array('http://','https://');
$remove1 = array('', '', '', '');
$arrFrom = array(':)', 'O:)', ':3', 'o.0', 'o.O', ":')", '3:)', ':(', '8)', ':D', '>:(', '<3', '^_^', ':*', ':v', '-_-', '8|',':p', ':P', ':/', '>:O', ';)');
$arrTo = array(
			  '<img src="emotions-fb/smile.gif" class="emo1"/>',
	          '<img src="emotions-fb/angel.gif" class="emo1"/>',
			  '<img src="emotions-fb/colonthree.gif" class="emo1"/>',
	          '<img src="emotions-fb/confused.gif" class="emo1"/>',
			  '<img src="emotions-fb/confused.gif" class="emo1"/>',
			  '<img src="emotions-fb/cry.gif" class="emo1"/>',
			  '<img src="emotions-fb/devil.gif" class="emo1"/>',
			  '<img src="emotions-fb/frown.gif" class="emo1"/>',
			  '<img src="emotions-fb/glasses.gif" class="emo1"/>',
	          '<img src="emotions-fb/grin.gif" class="emo1"/>',
			  '<img src="emotions-fb/grumpy.gif" class="emo1"/>',
	          '<img src="emotions-fb/heart.gif" class="emo1"/>',
			  '<img src="emotions-fb/kiki.gif" class="emo1"/>',
			  '<img src="emotions-fb/kiss.gif" class="emo1"/>',
			  '<img src="emotions-fb/pacman.gif" class="emo1"/>',
			  '<img src="emotions-fb/squint.gif" class="emo1"/>',
			  '<img src="emotions-fb/sunglasses.gif" class="emo1"/>',
	          '<img src="emotions-fb/tongue.gif" class="emo1"/>',
			  '<img src="emotions-fb/tongue.gif" class="emo1"/>',
			  '<img src="emotions-fb/unsure.gif" class="emo1"/>',
			  '<img src="emotions-fb/upset.gif" class="emo1"/>',
			  '<img src="emotions-fb/wink.gif" class="emo1"/>',
			  );
$text = str_replace($remove, $remove1, $text);
return str_replace($arrFrom, $arrTo, $text);
}
