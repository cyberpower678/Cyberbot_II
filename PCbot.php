<?php

ini_set('memory_limit','16M');

echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";

require_once( '/data/project/cyberbot/Peachy/Init.php' );

$site = Peachy::newWiki( "cyberbotii" );

$site->set_runpage( "User:Cyberbot II/Run/PC" );

while(true) {
	echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
	$request = $site->get_http()->get( 'https://en.wikipedia.org/w/index.php?title=Special:StablePages&offset=&limit=500000000' );

	$timestamp = date( 'Y-m-d\TH:i:s\Z' );

	if( !preg_match( '/mw\.config\.set\(\{\"wgBackendResponseTime\"\:(.*?)\,\"wgHostname\"\:\"(.*?)\"\}\)\;/i', $request ) ) {
		echo( "Site failure" );
		exit(1);
	}

	$templatelist1 = initPage("Template:Pp-pc1")->embeddedin();
	$templatelist2 = initPage("Template:Pp-pc2")->embeddedin();

	echo "Retrieving Protected Pages...\n";

	preg_match_all('/'.
							'\<li\>\<a href=\".*?\"\s*title=\".*?\"\>(.*?)\<\/a\>.*?\[autoreview=(autoconfirmed|review)\]\<i\>(\s*\(expires\s*(.*?\s*\(UTC\))\))?\<\/i\>\<\/li\>/i',$request,$list);

	$names = $list[1];
	$level = $list[2];
    $expiry = $list[4];

	echo "Retrieved protected pages.\n";

	//Tag each page with a protection template
	foreach( $names as $i=>$page )    {
		echo "Processing ".$page."...\nLevel: {$level[$i]}; Expires: {$expiry[$i]}\n";
        $object = $site->initPage( $page, null, false, true, $timestamp );
        $text = $object->get_text();
        if( empty( $text ) ) continue;
        $template = "";
        if( $level[$i] == "autoconfirmed" ) $template .= "{{pp-pc1";
        else $template .= "{{pp-pc2";
        if( ($temp = strpos( strtolower( $text ), "{{pp-pc" )) !== false ) {
            if( strpos( strtolower( $text ), "{{pp-pc", $temp + 1 ) !== false || ( $level[$i] == "autoconfirmed" && strpos( strtolower( $text ), "{{pp-pc2" ) !== false ) || ( $level[$i] == "review" && strpos( strtolower( $text ), "{{pp-pc1" ) !== false ) ) {
                echo "Replacing existing PC tags with correct one...\n";
                $text = preg_replace( '/\{\{[P|p]p-pc[1|2].*?\}\}(\s*?\n)?/i','',$text );    
            } elseif( strpos( strtolower( $text ), $template ) !== false ) {
                echo "Page already tagged with $template}}\n";
                continue;
            }
        }
        if( !empty( $expiry[$i] ) ) $template .= "|expiry=".date( 'F j, Y', strtotime( $expiry[$i] ) );
        $template .= "}}";
        
        $logs = $site->logs( "stable", false, $page );
        if( isset( $logs[0]['timestamp'] ) ) $lastprotectaction = strtotime( $logs[0]['timestamp'] );
        else $lastprotectaction = 0;
        $dt = time() - $lastprotectaction;
        if( $dt <= 300 ) continue;
        
        echo "Tagging ".$page." with $template...\n";            
        if( strtolower( substr( $text, 0, 9 ) ) != "#redirect" ) $object->edit( $template."\n".$text, "Tagging page with $template.", true );            
	    else $object->edit( $text."\n".$template, "Tagging redirect with $template.", true ); 
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
    sleep( 60 );
}