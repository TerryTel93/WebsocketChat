<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8' />
<style type="text/css">
<!--
.chat_wrapper {
	width: 1000px;
	margin-right: auto;
	margin-left: auto;
	background: #CCCCCC;
	border: 1px solid #999999;
	padding: 10px;
	font: 12px 'lucida grande',tahoma,verdana,arial,sans-serif;
}
.chat_wrapper .message_box {
	background: #FFFFFF;
	height: 600px;
	overflow: auto;
	padding: 10px;
	border: 1px solid #999999;
}
.chat_wrapper .panel input{
	padding: 2px 2px 2px 5px;
}
.system_msg{color: #BDBDBD;font-style: italic;}
.user_name{font-weight:bold;}
.user_message{color: #88B6E0;}
-->
</style>
<link href="bootstrap/css/bootstrap.css" rel="stylesheet">
<link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet">
</head>
<script type="text/javascript" src="js/jquery.js"></script>
<script type="text/javascript" src="mespeak.js"></script>
<script type="text/javascript" src="bootstrap/js/bootstrap.js"></script>
<body>	
<?php 
$colours = array('007AFF','FF7000','FF7000','15E25F','CFC700','CFC700','CF1100','CF00BE','F00');
$user_colour = array_rand($colours);
?>

<script language="javascript" type="text/javascript">
$(document).ready(function(){
	$('<audio id="chatAudio"><source src="sounds-949-you-wouldnt-believe.mp3" type="audio/mpeg"></audio><audio id="serverAudio"><source src="sounds-917-communication-channel.mp3" type="audio/mpeg"></audio>').appendTo('body');
	//create a new WebSocket object.
	var wsUri = "ws://" + window.location.host + ":9000";
	websocket = new WebSocket(wsUri); 
	
	websocket.onopen = function(ev) { // connection is open 
		$('#message_box').append("<div class=\"system_msg\">Connected!</div>"); //notify user
			var msg = {
	message: "/connection",
	name: '<?php echo $_GET["un"]; ?>',
	color : '<?php echo $colours[$user_colour]; ?>',
	channel : '<?php echo $_GET["channel"]; ?>'
	};
	//convert and send data to server
	websocket.send(JSON.stringify(msg));
	}

	$('#send-btn').click(function(){ //use clicks message send button	
		var mymessage = $('#message').val(); //get message text
		var myname = $('#name').val(); //get user name
		
		if(mymessage == ""){ //emtpy message?
			alert("Enter Some message Please!");
			return;
		}
		
		//prepare json data
		var msg = {
		message: mymessage,
		name: '<?php echo $_GET["un"]; ?>',
		color : '<?php echo $colours[$user_colour]; ?>',
		channel : '<?php echo $_GET["channel"]; ?>'
		};
		//convert and send data to server
		websocket.send(JSON.stringify(msg));
	});
	
	//#### Message received from server?
	websocket.onmessage = function(ev) {
		var msg = JSON.parse(ev.data); //PHP sends Json data
		var type = msg.type; //message type
		var umsg = msg.message; //message text
		var uname = msg.name; //user name
		var ucolor = msg.color; //color
		var channel = msg.channel; //channel name

		if(type == 'usermsg') 
		{
			$('#message_box').append("<div><span class=\"user_name\" style=\"color:#"+ucolor+"\">"+uname+"</span> : <span class=\"user_message\">"+umsg+"</span></div>")
			if (uname != '<?php echo $_GET["un"]; ?>')
  			{
  				$('#chatAudio')[0].play();
  				if ($('.myCheckbox').attr('checked','checked')){
  					meSpeak.speak(uname+"........................"+umsg)
  				}
  				
  			}
		}
		if(type == 'system')
		{
			$('#message_box').append("<div class=\"system_msg\"><i>"+umsg+"</i></div>")
  				$('#serverAudio')[0].play();
  				if ($('.myCheckbox').attr('checked','checked')){
  					meSpeak.speak(umsg)
  				}
		}
		if(type == 'systemConnection')
		{
			$('#message_box').append("<div class=\"system_msg\"><i>"+umsg+"</i></div>")
			if ($('.myCheckbox').attr('checked','checked')){
  				meSpeak.speak(umsg)
  			}
		}

		if (uname == '<?php echo $_GET["un"]; ?>' || type != 'systemConnection')
  			{
		$('#message').val(''); //reset text
		}
	};
	
	websocket.onerror	= function(ev){$('#message_box').append("<div class=\"system_error\">Error Occurred - "+ev.data+"</div>");}; 
	websocket.onclose 	= function(ev){$('#message_box').append("<div class=\"system_msg\">Connection Closed</div>");}; 
});
</script>
  <script type="text/javascript">
    meSpeak.speak("");
    meSpeak.loadConfig("mespeak_config.json");
    meSpeak.loadVoice("voices/en/en-us.json");
  </script>
<div class="chat_wrapper">
<div class="message_box" id="message_box"></div>
<br>
<div class="input-append">
  		<input type="text" class="span9" name="message" id="message" placeholder="Message"/>
  		<button class="btn" id="send-btn" type="button">Send</button>
</div>
</div>

</body>
</html>