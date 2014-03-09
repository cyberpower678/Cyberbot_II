<?php

ini_set('memory_limit','16M');

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";

require_once( '/data/project/cyberbot/Peachy/Init.php' );

$site = Peachy::newWiki( "cyberbotii" );

$site->set_runpage( "User:Cyberbot II/Run/PC" );

while(true) {
	echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
	$request = $site->get_http()->get( 'http://en.wikipedia.org/w/index.php?title=Special:StablePages&offset=&limit=500000000' );

	$timestamp = date( 'Y-m-d\TH:i:s\Z' );

	if( !preg_match( '/\<\!--( )?Served by (.*?) in (.*?) secs.( )?--\>/i', $request ) ) {
		echo( "Site failure" );
		exit(1);
	}

	$templatelist1 = initPage("Template:Pp-pc1")->embeddedin();
	$templatelist2 = initPage("Template:Pp-pc2")->embeddedin();

	echo "Retrieving Protected Pages...\n";

	preg_match_all('/'.
							'\<li\>\<a href=(.*?) title=(.*?)\>(.*?)\<\/a\>(.*?)\[autoreview=(.*?)\]/i',$request,$list);

	$names = $list[3];
	$level = $list[5];

	echo "Retrieved protected pages.\n";

	$i = 0;

	//Tag each page with a protection template
	foreach( $names as $page )    {
			echo "Processing ".$page."...\n";
			$istagged = false;
			if( $level[$i] == "autoconfirmed" )    {
					$template = "{{pp-pc1}}\n";
					echo "Tagging ".$page." with {{pp-pc1}}...\n";
					foreach( $templatelist1 as $page2 )    {
							if( $page == htmlspecialchars($page2) )    {
									echo "Page already tagged with {{pp-pc1}}!\n";
									$istagged = true;
									break;
							}
					}
					if( !$istagged ) {
                        $logs = $site->logs( "stable", false, $pagename );
                        if( isset( $logs[0]['timestamp'] ) ) $lastprotectaction = strtotime( $logs[0]['timestamp'] );
                        else $lastprotectaction = 0;
                        $dt = time() - $lastprotectaction;
                        if( $dt > 300 ) $site->initPage($page, null, false, true, $timestamp)->prepend($template, "Tagging page with PC1 protection template.", true);    
                    }
			}    else    {
					$template = "{{pp-pc2}}\n";
					echo "Tagging ".$page." with {{pp-pc2}}...\n";
					foreach( $templatelist2 as $page2 )    {
							if( $page == htmlspecialchars ($page2) )    {
									echo "Page already tagged with {{pp-pc2}}!\n";
									$istagged = true;
									break;
							}        
					}
					if( !$istagged ) 
                    {
                        $logs = $site->logs( "stable", false, $pagename );
                        if( isset( $logs[0]['timestamp'] ) ) $lastprotectaction = strtotime( $logs[0]['timestamp'] );
                        else $lastprotectaction = 0;
                        $dt = time() - $lastprotectaction;
                        if( $dt > 300 ) $site->initPage($page, null, false, true, $timestamp)->prepend($template, "Tagging page with PC2 protection template.", true);
                    }
			}
			$i = $i+1;
	}

	//Removing templates from unprotected pages

	echo "\nVerifying that all pages are tagged correctly...\n";

	foreach($templatelist1 as $page)    {
		$i = 0;
		$isprotected = false;
			foreach($names as $page2)    {
					if(htmlspecialchars ($page) == $page2)    {
							if($level[$i] == "autoconfirmed")    {
									echo $page." has a correct tag\n";
									$isprotected = true;
							}
					}
					$i = $i+1;
			}
			if($isprotected == false)    {
					echo $page." is not PC1 protected.  Removing template...\n";
					$data = initPage($page, null, false, true, $timestamp);
					$data->edit(preg_replace('/\{\{(P|p)?p-pc1(.*?)\}\}(\n)?/i','',$data->get_text()),"Removing {{Pp-pc1}}.  Page is not protected.", true);
			}
		}

	foreach($templatelist2 as $page)    {
			$i = 0;
			$isprotected = false;
			foreach($names as $page2)    {
					if(htmlspecialchars ($page) == $page2)    {
							if($level[$i] == "review")    {
									echo $page." has a correct tag\n";
									$isprotected = true;
							}
					}
					$i = $i+1;
			}
			if($isprotected == false)    {
					echo $page." is not PC2 protected.  Removing template...\n";
					$data = initPage($page, null, false, true, $timestamp);
					$data->edit(preg_replace('/\{\{(P|p)?p-pc2(.*?)\}\}(\n)?/i','',$data->get_text()),"Removing {{Pp-pc2}}.  Page is not protected.", true);
			}
	}

	echo "Task completed!\n\n";
}