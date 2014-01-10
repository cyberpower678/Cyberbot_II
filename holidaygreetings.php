<?php

ini_set('memory_limit','16M');

require_once( 'C:\Users\Maximilian Doerr\Documents\NetBeansProjects\Peachy\Peachy/Init.php' );
$notag = true;
$site = Peachy::newWiki( "admin" );

$wikifriends = initPage('User:Cyberpower678/My Wikifriends')->get_text();
$wikifriends = explode('= Here are a list of editors I consider annoying =',$wikifriends);
$wikifriends = $wikifriends[0];
//echo $wikifriends;
preg_match_all('/\#\[\[User\:(.*?)\]\]/i', $wikifriends, $wikifriends);
print_r ($wikifriends);
foreach( $wikifriends[1] as $user ) {
	echo "Sending seasons greetings to ".$user.".\n\n";
	$out = "{{subst:New Year 1}}~~~~";
	echo $out."\n\n";
	$site->initPage("User talk:".$user)->newsection( $out, "Happy 2014 from Cyberpower678", "Happy New Year" );
}