<?php

ini_set('memory_limit','16M');

require_once( '/data/project/cyberbot/Peachy/Init.php' );

$site = Peachy::newWiki( "cyberbotii" );

$site->set_runpage("User:Cyberbot II/Run/DCN");

$vandalinfo = initPage('Template:Vandalism information')->get_text();

if ( preg_match('/level  = \{\{WikiDefcon\/levels\|(.*?)\}\}/i', $vandalinfo) ) {
	preg_match('/level  = \{\{WikiDefcon\/levels\|(.*?)\}\}/i', $vandalinfo, $revertcount);
	$defcon = determineDefCon($revertcount[1]);
	notifyUsers($defcon, $revertcount[1]);
}
else
{
	$CVU = initPage('Wikipedia talk:Counter-Vandalism Unit')->get_text();
	$CVU = $CVU."\n\n== Cyberbot II Error: ==\n\nCyberbot II is unable to detect the {{tlx|WikiDefcon/levels}} template.  This is needed so the bot can properly process vandalism levels.  It appears that {{tlx|Vandalism information}} template is not being correctly updated.  Please fix the issue so that I can continue functioning normally.~~~~";
	echo $CVU;
	initPage( 'Wikipedia talk:Counter-Vandalism Unit' )->edit($CVU,"Fatal Error: Unable to read [[Template:Vandalism information]]", false);
}

function determineDefCon($reverts) {
	$temp = 5;
	if ( $reverts > 4 ) {
		$temp = 4;
		if ( $reverts > 9 ) {
			$temp = 3;
			if ( $reverts > 14 ) {
				$temp = 2;
				if ( $reverts > 19 ) {
					$temp = 1;
				}
			}
		}
	}
	echo "Current Defcon: $temp\n";
	return $temp;
}

function notifyUsers($threshold, $count) {
	$notifpage = initPage('Wikipedia:Counter-Vandalism Unit/Notifications list')->get_text();
	if ( preg_match('/\*\[\[(.*?)\]\]\|DefCon\: (.*?)\|(.*?)\|(.*?)\|/i', $notifpage) ) {
		preg_match_all('/\*\[\[(.*?)\]\]\|DefCon\: (.*?)\|(.*?)\|(.*?)\|/i', $notifpage, $userlist);
		//print_r($userlist);
		$userdefsettings = $userlist[2];
		$usertimesettings = $userlist[3];
		$usernotifsettings = $userlist[4];
		$userlist = $userlist[1];
		$i = 0;
		foreach( $userlist as $user ) {
			echo "Processing $user.\nUser prefers to be notified when DefCon reaches level $userdefsettings[$i].\n\n";
			if ( $threshold <= $userdefsettings[$i]) {
				$out = initPage($user)->get_text()."\n\n== Notification of elevated vandalism ==\n\nHello.  I am Cyberbot II.  According to the [[Wikipedia:Counter-Vandalism Unit/Notifications list]], you have signed up to receive notifications from me when vandalism has reached a DefCon level of $usersettings[$i].  The current DefCon is $threshold with $count reverts per minute.  If you don't like to receive these notfications, you may opt out by removing yourself from this list.~~~~\n";
				echo $out;
				initPage( $user )->edit($out, "/*Notification of elevated vandalism*/ new section", false);
			}
			$i = $i + 1;
		}
		//print_r($userdefsettings);
	}
}