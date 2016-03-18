<?php

ini_set('memory_limit','5G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once('/data/project/cyberbot/Peachy/Init.php' );
require_once('/data/project/cyberbot/database.inc');

$site2 = Peachy::newWiki( "meta" );
$site = Peachy::newWiki( "cyberbotii" );

$a=0;

//recovery from a crash or a stop midscan
$status = unserialize( file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/fsbstatus' ) );
if( isset( $status['fstatus'] ) && $status['fstatus'] == 'scan' ) {
	preg_match( '/\((.*?) of/i', $status['fscanprogress'], $previousoffset );
	$a = $status['fbladd'];
	$previousoffset = $previousoffset[1];
}

echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
echo "Retrieving blacklists...\n\n";

$status = array( 'fstatus' => 'scan', 'fbladd'=>$a, 'fscanprogress'=>'x' );
updateStatus();
$globalblacklistregex = $site2->initPage( 'Spam blacklist' )->get_text();
$globalblacklistregexarray = explode( "\n", $globalblacklistregex );																	 

$blacklistregex = $site->initPage( 'MediaWiki:Spam-blacklist' )->get_text();
$blacklistregexarray = explode( "\n", $blacklistregex ); 
$blacklistregex = buildSafeRegexes(array_merge($blacklistregexarray, $globalblacklistregexarray));

$whitelistregex = $site->initPage( 'MediaWiki:Spam-whitelist' )->get_text();
$whitelistregexarray = explode( "\n", $whitelistregex );
$whitelistregex = buildSafeRegexes($whitelistregexarray);

$dblocal = mysqli_connect( 'tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );
$dbwiki = mysqli_connect( 'enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );

$res = mysqli_query( $dbwiki, "SELECT COUNT(*) AS count FROM externallinks;" );
$linkcount = mysqli_fetch_assoc( $res );
$linkcount = $linkcount['count'];
mysqli_free_result( $res );
$offset = 0;
if( isset( $previousoffset ) ) {
	$offset = $previousoffset;
	unset( $previousoffset );
}
$completed = 0;
$completed = ($offset/$linkcount)*100;
//compile the pages containing blacklisted URLs
echo "Scanning {$linkcount} externallinks in the database...\n\n";
$status = array( 'fstatus' => 'scan', 'fbladd'=>$a, 'fscanprogress'=>round($completed, 3)."% ($offset of {$linkcount})" );
updateStatus();
$starttime = time();
while( $offset < $linkcount ) {
	while ( !($res = mysqli_query( $dbwiki, "SELECT * FROM externallinks LIMIT $offset,15000;" )) ) {
		echo "Reconnecting to enwiki DB...\n\n";
		mysqli_close( $dbwiki );
		$dbwiki = mysqli_connect( 'enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );   
	}	
	while( $page = mysqli_fetch_assoc( $res ) ) {
		if( regexscan( $page['el_to'] ) ) {
			while ( !(mysqli_query( $dblocal, "INSERT INTO blacklisted_links (`url`,`page`) VALUES ('".mysqli_escape_string($dblocal, $page['el_to'])."','".mysqli_escape_string($dblocal, $page['el_from'])."');" )) && mysqli_errno( $dblocal ) != 1062 ) {
				echo "Attempted INSERT INTO blacklisted_links (`url`,`page`) VALUES ('".mysqli_escape_string($dblocal, $page['el_to'])."','".mysqli_escape_string($dblocal, $page['el_from'])."'); with error ".mysqli_errno( $dblocal )."\n\n";
				echo "Reconnecting to local DB...\n\n";
				mysqli_close( $dblocal );
				$dblocal = mysqli_connect( 'tools-db', $toolserver_username, $toolserver_password, 's51059__cyberbot' );   
			}
			$a++;
		}
	}
	mysqli_free_result( $res );
	$offset+=15000;
	$completed = ($offset/$linkcount)*100;
	$completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
	$completedby = time() + $completedin;
	$status = array( 'fstatus' => 'scan', 'fbladd'=>$a, 'fscanprogress'=>round($completed, 3)."% ($offset of {$linkcount})", 'fscaneta'=>round($completedby, 0) );
	updateStatus();
}
$status = array( 'fstatus' => 'idle' );
updateStatus();

echo "Added $a pages to the local database!\n";
mysqli_close( $dblocal );
mysqli_close( $dbwiki );
  
//This scans the links with the regexes on the blacklist.  If it finds a match, it scans the whitelist to see if it should be ignored.
function regexscan( $link ) {
	global $blacklistregex, $whitelistregex;
	foreach( $blacklistregex as $regex ) {
		if( preg_match($regex, $link) ) {
			foreach( $whitelistregex as $wregex ) if( preg_match($wregex, $link) ) return false;
			return true;
		}
	}
	return false;
}
//generate a status file
function updateStatus() {
	global $status;
	return file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/fsbstatus', serialize($status) );	
}
//This is the spam blacklist engine used in MediaWiki adapted for this script.  This ensures consistency with the actual wiki.
function stripLines( $lines ) {
	return array_filter(
		array_map( 'trim',
			preg_replace( '/#.*$/', '',
				$lines ) ) );
}

function buildSafeRegexes( $lines ) {
	$lines = stripLines( $lines );
	$regexes = buildRegexes( $lines );
	if( validateRegexes( $regexes ) ) {
		return $regexes;
	} else {
		// _Something_ broke... rebuild line-by-line; it'll be
		// slower if there's a lot of blacklist lines, but one
		// broken line won't take out hundreds of its brothers.
		return buildRegexes( $lines, 0 );
	}
}

function buildRegexes( $lines, $batchSize=4096 ) {
	# Make regex
	# It's faster using the S modifier even though it will usually only be run once
	//$regex = 'https?://+[a-z0-9_\-.]*(' . implode( '|', $lines ) . ')';
	//return '/' . str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $regex) ) . '/Sim';
	$regexes = array();
	$regexStart = '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
	$regexEnd = ')'.getRegexEnd( $batchSize );
	$build = false;
	foreach( $lines as $line ) {
		if( substr( $line, -1, 1 ) == "\\" ) {
			// Final \ will break silently on the batched regexes.
			// Skip it here to avoid breaking the next line;
			// warnings from getBadLines() will still trigger on
			// edit to keep new ones from floating in.
			continue;
		}
		// FIXME: not very robust size check, but should work. :)
		if( $build === false ) {
			$build = $line;
		} elseif( strlen( $build ) + strlen( $line ) > $batchSize ) {
			$regexes[] = $regexStart .
				str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $build) ) .
				$regexEnd;
			$build = $line;
		} else {
			$build .= '|';
			$build .= $line;
		}
	}
	if( $build !== false ) {
		$regexes[] = $regexStart .
			str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $build) ) .
			$regexEnd;
	}
	return $regexes;
}

function getRegexEnd( $batchSize ) {
	return ($batchSize > 0 ) ? '/Sim' : '/im';
}

function validateRegexes( $regexes ) {
	foreach( $regexes as $regex ) {
		//wfSuppressWarnings();
		$ok = preg_match( $regex, '' );
		//wfRestoreWarnings();

		if( $ok === false ) {
			return false;
		}
	}
	return true;
}