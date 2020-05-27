<?php
require_once 'config.inc';
require_once 'functions.inc';
require_once 'updatenotify.inc';
require_once 'util.inc';

define( 'IPV6_REGEX',
        '/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/'
);
define( 'IPV4_REGEX', '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/' );

$IPExceptionList[] = '192.168.0.0/16';
$IPExceptionList[] = '10.8.0.0/16';

$banIPv6Mask = 'ffff:ffff:ffff:ffff::';
$banIPv4Mask = '255.255.0.0';
$banIPv6CIDR = 64;
$banIPv4CIDR = 16;

$logFile = '/var/log/sshd.log';

$lineParseRegex =
	'/doerr\s*\d*\s*(\d{4}\-\d{2}\-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?\+\d{2}:\d{2})\s*doerr\.asuscomm\.com\s*sshd\s*\d{1,5}\s*\-\s*\-\s*(.*)/';

$banStrings = [
	'[preauth]',
	'Did not receive identification string',
	'Invalid user',
	'Disconnecting invalid user'
];
$failedValidRegex = '/from\sauthenticating\suser\s(.*?)\s(.*?)\s/';

$successRegex = '/Accepted\spublickey\sfor\s(.*?)\sfrom\s(.*?)\s/';

if( !file_exists( 'successes' ) ) $successLogins = [];
else $successLogins = unserialize( file_get_contents( 'successes' ) );

if( file_exists( 'lastCheck' ) ) $lastCheck = unserialize( file_get_contents( 'lastCheck' ) );
else $lastCheck = 0;

$newestEntry = $lastCheck;

$IPBanList = [];

if( !file_exists( $logFile ) ) {
	echo "Unable to locate log file\n";
	exit( 1 );
}
$logData = file_get_contents( $logFile );
$logData = explode( "\n", $logData );

$loadedConfig = false;

$entryAdded = false;

// Look at each log entry
foreach( $logData as $entry ) {
	// If we can parse the line, then break it down
	if( preg_match( $lineParseRegex, $entry, $breakdown ) ) {
		// Get log entry time in unix epoch
		$entryTime = strtotime( $breakdown[1] );
		// Make sure it wasn't already checked on the last run
		if( $entryTime <= $lastCheck ) continue;
		if( $entryTime > $newestEntry ) $newestEntry = $entryTime;

		if( !$loadedConfig ) {
			$a_rule = &array_make_branch( $config, 'system', 'firewall', 'rule' );

			foreach( $a_rule as $rule ) {
				$IPBanList[] = $rule['src'];
			}

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_HTTPGET, 1 );
			curl_setopt( $ch, CURLOPT_POST, 0 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		}

		// Let's look at the actual message
		$logString = $breakdown[2];
		if( preg_match( $successRegex, $logString, $data ) ) {
			$user = $data[1];
			$host = $data[2];

			if( !isset( $successLogins[$user][$host] ) ) {
				$url = "https://ipinfo.io/$host/json";
				curl_setopt( $ch, CURLOPT_URL, $url );

				$ipData = curl_exec( $ch );

				$ipData = json_decode( $ipData, true );

				// Send message to Telegram informing of ban
				sendTelegram( "435724335", "$user logged in from new IP",
				              "IP: $host\nHost: {$ipData['hostname']}\nCity: {$ipData['city']}\nRegion: {$ipData['region']}\nCountry: {$ipData['country']}\nOrginization: {$ipData['org']}"
				);
				$successLogins[$user][$host] = 1;
			} else $successLogins[$user][$host]++;
		} elseif( preg_match( $failedValidRegex, $logString, $data ) ) {
			$user = $data[1];
			$host = $data[2];
			$out = "$host failed to log on as '$user'";

			sendTelegram( "435724335", "Login Failed", $out );
		}
		// Test message against a list of triggers
		else foreach( $banStrings as $testString ) {
			// If a trigger matches, then let's ban the host
			if( strpos( $logString, $testString ) !== false ) {
				// Get IP from message
				if( preg_match( IPV4_REGEX, $logString, $hostMatch ) ||
				    preg_match( IPV6_REGEX, $logString, $hostMatch ) ) {
					$host = $hostMatch[1];

					try {
						$packedHost = dtr_pton( $host );
					} catch( Exception $exception ) {
						echo "$host is not a valid IP\n";
						continue;
					}
					if( strlen( $packedHost ) === 16 ) $packedMask = dtr_pton( $banIPv6Mask );
					else $packedMask = dtr_pton( $banIPv4Mask );

					// Apply set mask on to IP to create the base of the IP range being blocked
					try {
						$toBan = dtr_ntop( $packedHost & $packedMask );
					} catch( Exception $exception ) {
						echo "Oops! Something went wrong here.\n";
						echo "Packed host: $packedHost\n\tLength: " . strlen( $packedHost ) . "\n";
						echo "Packed mask: $packedMask\n\tLength: " . strlen( $packedMask ) . "\n";
						exit( 1 );
					}
					if( strlen( $packedHost ) === 16 ) $toBan .= "/$banIPv6CIDR";
					else $toBan .= "/$banIPv4CIDR";

					// Make sure the range isn't already blocked, and that it's not listed on the whitelist
					if( !in_array( $toBan, $IPBanList ) && !in_array( $toBan, $IPExceptionList ) ) {
						// Red tape gone, let's ban.
						$url = "https://ipinfo.io/$host/json";
						curl_setopt( $ch, CURLOPT_URL, $url );

						$ipData = curl_exec( $ch );

						$ipData = json_decode( $ipData, true );

						$entryAdded = true;
						addFirewallEntry( $toBan,
						                  "Range location: {$ipData['city']}, {$ipData['region']}, {$ipData['country']}; Org: {$ipData['org']}"
						);
						echo "Banned $toBan\n";
						$IPBanList[] = $toBan;

						// Send message to Telegram informing of ban
						sendTelegram( "435724335", "$toBan was just banned",
						              "Host: {$ipData['hostname']}\nCity: {$ipData['city']}\nRegion: {$ipData['region']}\nCountry: {$ipData['country']}\nOrginization: {$ipData['org']}"
						);
					}
				}

				break;
			}
		}
	}
}

if( $entryAdded ) applyFirewall();

file_put_contents( 'lastCheck', serialize( $newestEntry ) );
file_put_contents( 'successes', serialize( $successLogins ) );

function applyFirewall() {
	$retval = 0;
	$retval |= updatenotify_process( 'firewall', 'firewall_process_updatenotification' );
	config_lock();
	$retval |= rc_update_service( 'ipfw' );
	config_unlock();
	if( $retval == 0 ):
		updatenotify_delete( 'firewall' );
	endif;
}

function addFirewallEntry( $host, $desc = "Autoblocked by firewall script", $rule = false ) {
	global $a_rule;

	if( !empty( $a_rule ) ):
		array_sort_key( $a_rule, 'ruleno' );
	endif;

	// Input validation.
	// Validate if rule number is unique.
	$ruleNumber = get_next_rulenumber();
	while( array_search_ex( $ruleNumber, $a_rule, "ruleno" ) !== false ) {
		$ruleNumber += 100;
	}

	if( $rule === false ) {
		$rule = [];
		$rule['uuid'] = uuid();
		$rule['enable'] = true;
		$rule['ruleno'] = $ruleNumber;
		$rule['action'] = "deny";
		$rule['log'] = true;
		$rule['protocol'] = "all";
		$rule['src'] = $host;
		$rule['srcport'] = "";
		$rule['dst'] = "";
		$rule['dstport'] = "";
		$rule['direction'] = "in";
		$rule['if'] = "";
		$rule['extraoptions'] = "";
		$rule['desc'] = $desc;
	}

	$a_rule[] = $rule;

	$mode = UPDATENOTIFY_MODE_NEW;

	updatenotify_set( "firewall", $mode, $rule['uuid'] );

	write_config();
}

// Get next rule number.
function get_next_rulenumber() {
	global $a_rule;

	// Set starting rule number
	$ruleno = 10100;

	if( false !== array_search_ex( strval( $ruleno ), $a_rule, "ruleno" ) ) {
		do {
			$ruleno += 100; // Increase rule number until a unused one is found.
		} while( false !== array_search_ex( strval( $ruleno ), $a_rule, "ruleno" ) );
	}

	return $ruleno;
}

function dtr_pton( $ip ) {


	if( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return current( unpack( "a4", inet_pton( $ip ) ) );
	} elseif( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return current( unpack( "a16", inet_pton( $ip ) ) );
	}


	throw new \Exception( "Please supply a valid IPv4 or IPv6 address" );


	return false;
}

function dtr_ntop( $str ) {
	if( strlen( $str ) == 16 OR strlen( $str ) == 4 ) {
		return inet_ntop( pack( "A" . strlen( $str ), $str ) );
	}


	throw new \Exception( "Please provide a 4 or 16 byte string" );


	return false;
}

function sendTelegram( $userID, $title, $message ) {
	if( $userID === false ) return false;

	$userID = escapeshellarg( $userID );
	$title = escapeshellarg( $title );
	$message = escapeshellarg( $message );

	$result = shell_exec( "telegram-notify --title $title  --text $message --user $userID" );

	$result = json_decode( $result, true );

	return ( $result['ok'] == true );
}