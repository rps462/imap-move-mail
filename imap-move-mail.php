#!/usr/bin/php
<?

// This script will connect to the specified imap email accounts
// grab the messages and forward them to the specified account.
// While this is mostly intended for processing feedback loops, it
// can be used for anything.

$feedback_host		= ''; 		// host where we will send messages
$feedback_port 		= '25';		// host/port
$feedback_timeout 	= '30';		// host timeout
$feedback_ehlo		= '';			// ehlo to send 
$feedback_addr 		= '';			// address to whom messages will be sent
$feedback_from 		= '';			// address from whom messages will be sent

$fbl_log 		= '/var/log/moveemail.log';

sendLog($fbl_log, "Beginning Mailbox Processing.");

// Speficy Mailboxes here
// Format: 
//    $var = array('name'     => 'Account Title', 
//                 'hostmame' => 'imap host', 
//                 'username' => 'imap login', 
//                 'password' => 'imap password');
// 
// Combine mailboxes into single array:
// 
// $fblarray[] = $var1;
// $fblarray[] = $var2;
// $fblarray[] = $var3;

// Loop through Mailboxes, grab messages from each and add to Message Container

foreach ($fblarray as $_fblarray)
{
	$msgpack = get_fbl_messages($_fblarray, $fbl_log);
	$msgcount = count($msgpack);

	if ($msgcount > 0)
	{
		sendLog($fbl_log, "Adding $msgcount message(s) to $_fblarray[name] message pack.");
   		$fblmsgarray[] = $msgpack;
	} else
	{
		sendLog($fbl_log, "No messages for $_fblarray[name] FBL.");
	}
}

if (count($fblmsgarray) == 0) 
{
	sendLog($fbl_log, "No messages to process, exiting.");
	exit;
}

// Connect up
$smtpconx = fsockopen($feedback_host, $feedback_port, $errno, $errstr, $feedback_timeout);

if(!$smtpconx)
{
	sendLog($fbl_log, "ERROR: $feedback_host - $errno - $errstr.");
	exit;
} else
{
	sendLog($fbl_log, "Connected to $feedback_host.");
}

fwrite($smtpconx, "$feedback_ehlo\r\n");

$message_count = 0;

foreach ($fblmsgarray as $_fblmsgarray)
{
	foreach ($_fblmsgarray as $fblmsg)
	{
		$message_count++;
		fwrite($smtpconx, "MAIL FROM: $feedback_from\r\n");
		fwrite($smtpconx, "RCPT TO: $feedback_addr\r\n");
		fwrite($smtpconx, "DATA\r\n");
		fwrite($smtpconx, "$fblmsg\r\n");
		fwrite($smtpconx, ".\r\n");
	}

}

sendLog($fbl_log, "Processed $message_count message(s).");

fwrite($smtpconx, "QUIT\r\n");

sendLog($fbl_log, "Finished, disconnecting.");

// Functions

function get_fbl_messages($fbl_array, $fbl_log) 
{
	$msgpack  = array();
	$fblname  = $fbl_array['name'];
	$hostname = $fbl_array['hostname'];
	$hostname = "{".$hostname."}";
	$username = $fbl_array['username'];
	$password = $fbl_array['password'];

	sendLog($fbl_log, "Connecting to $fblname FBL IMAP : $username@$hostname.");
	$mailbox = imap_open($hostname, $username, "$password", OP_READONLY);

	if ($mailbox) 
	{
   		$num_msg = imap_num_msg($mailbox);

   		if ($num_msg > 0) 
   		{
			sendLog($fbl_log, "Fetching $num_msg FBL message(s).");

			for ($i=$num_msg; $i>0; $i--) 
			{
				$fblhdr = imap_fetchheader($mailbox, $i);
				$fblhdr = str_replace("\r","",$fblhdr);
				$fblbody = imap_body($mailbox, $i);
				$fblbody = str_replace("\r","",$fblbody);
				$fblmsg = $fblhdr."\n".$fblbody;

				array_push($msgpack, $fblmsg);
				imap_delete($mailbox, $i);
			}
			return $msgpack;
		} 

		imap_close($mailbox);

	} else 
	{
		sendLog($fbl_log, "Error opening mailbox $hostname, $username.");
	}
}

function sendLog($log_file, $msg)
{
	$ct = timeStamp();
	$flog = fopen($log_file, 'a') or die("can't open file $log_file");
	$str = "[" . $ct . "] " . $msg;
	fwrite($flog, $str ."\n");
	fclose($flog);
}

function timeStamp()
{
	$sign = "-";
	$h = "7";
	$dst = "false";

	if ($dst)
	{
		$daylight_saving = date('I');
		if ($daylight_saving)
		{
			if ($sign == "-")
			{
				$h=$h-1;
			} else
			{
				$h=$h+1;
			}
		}
	}

	$hm = $h * 60;
	$ms = $hm * 60;

	if ($sign == "-")
	{
		$timestamp = time()-($ms);
	} else
	{
		$timestamp = time()+($ms);
	}

	$ct = gmdate("Y-m-d H:i:s", $timestamp);
	return($ct);
}

?>