<?php

ini_set('memory_limit','5G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once('/data/project/cyberbot/Peachy/Init.php' );
require_once('/data/project/cyberbot/database.inc');
require_once('/data/project/cyberbot/database2.inc');

$site2 = Peachy::newWiki( "meta" );
$site = Peachy::newWiki( "cyberbotii" );
$site->set_runpage( "User:Cyberbot II/Run/SPAM" );

//recovery from a crash or a stop
$status = unserialize( file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/sbstatus' ) );
if( isset( $status['status'] ) && $status['status'] != 'idle' ) {
    //Attempt to recover data state or start over on failure.
    file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/lastcrash', serialize( time() ) );
    $a = $status['bladd'];
    $d = $status['bldeleted'];
    $e = $status['blexception'];
    file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/sbstatus', serialize(array( 'status' => 'recover', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>'x', 'scantype'=>'x' )) );
    if( !file_exists( '/data/project/cyberbot/CyberbotII/spambotdata/pagebuffer') ) goto normalrun;
    else $pagebuffer = unserialize( file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/pagebuffer') );
    if( !is_array( $pagebuffer ) ) goto normalrun;
    if( !file_exists( '/data/project/cyberbot/CyberbotII/spambotdata/rundata') ) goto normalrun;
    else $rundata = unserialize( file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/rundata') );
    if( !is_array( $rundata ) ) goto normalrun;
    if( !isset( $rundata['whitelist'] ) ) goto normalrun;
    else $whitelistregex = $rundata['whitelist'];
    if( !isset( $rundata['blacklist'] ) ) goto normalrun;
    else $blacklistregex = $rundata['blacklist'];
    if( !file_exists( '/data/project/cyberbot/CyberbotII/spambotdata/exceptions.wl' ) ) goto normalrun;
    else $exceptions = unserialize( file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/exceptions.wl' ) );
    if( !isset( $rundata['blacklistregex'] ) ) goto normalrun;
    else $blacklistregexarray = $rundata['blacklistregex'];
    if( !isset( $rundata['globalblacklistregex'] ) ) goto normalrun;
    else $globalblacklistregexarray = $rundata['globalblacklistregex'];
    if( !isset( $rundata['whitelistregex'] ) ) goto normalrun;
    else $whitelistregexarray = $rundata['whitelistregex'];
    $dblocal = new Database( 'tools-db', $toolserver_username2, $toolserver_password2, 'cyberbot' );
    $dbwiki = new Database( 'enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );
}
if( isset( $status['status'] ) && $status['status'] == 'scan' && $status['scantype'] == 'local' ) {
    //Attempt to restart scan at crash point
    if( !isset( $rundata['linkcount'] ) ) goto normalrun;
    else $linkcount[0]['count'] = $rundata['linkcount'];
    if( !isset( $rundata['offset'] ) ) goto normalrun;
    else $offset = $rundata['offset'];
    if( !isset( $rundata['starttime'] ) ) goto normalrun;
    else $starttime = $rundata['starttime'];
    if( !isset( $rundata['todelete'] ) ) goto normalrun;
    else $todelete = $rundata['todelete'];
    goto localscan;
}
if( isset( $status['status'] ) && $status['status'] == 'scan' && $status['scantype'] == 'replica' ) {
    //Attempt to restart scan at crash point
    if( !isset( $rundata['linkcount'] ) ) goto normalrun;
    else $linkcount[0]['count'] = $rundata['linkcount'];
    if( !isset( $rundata['offset'] ) ) goto normalrun;
    else $offset = $rundata['offset'];
    if( !isset( $rundata['starttime'] ) ) goto normalrun;
    else $starttime = $rundata['starttime'];
    goto wikiscan; 
}
if( isset( $status['status'] ) && $status['status'] == 'process' ) goto findrule;
if( isset( $status['status'] ) && $status['status'] == 'tag' ) goto tagging;
if( isset( $status['status'] ) && $status['status'] == 'remove' ) goto removing;

    normalrun:
    echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
    echo "Retrieving blacklists...\n\n";
    $d = 0;
    $a = 0;
    $e = 0;
    $status = array( 'status' => 'start', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>'x', 'scantype'=>'x' );
    $rundata = array();
    updateStatus();
    $globalblacklistregex = $site2->initPage( 'Spam blacklist' )->get_text();
    $globalblacklistregexarray = explode( "\n", $globalblacklistregex );
    $rundata['globalblacklistregex'] = $globalblacklistregexarray;                                                                     

    $blacklistregex = $site->initPage( 'MediaWiki:Spam-blacklist' )->get_text();
    $blacklistregexarray = explode( "\n", $blacklistregex );
    $rundata['blacklistregex'] = $blacklistregexarray; 
    $blacklistregex = buildSafeRegexes(array_merge($blacklistregexarray, $globalblacklistregexarray));
    $rundata['blacklist'] = $blacklistregex;

    $whitelistregex = $site->initPage( 'MediaWiki:Spam-whitelist' )->get_text();
    $whitelistregexarray = explode( "\n", $whitelistregex );
    $whitelistregex = buildSafeRegexes($whitelistregexarray);
    $rundata['whitelist'] = $whitelistregex;
    $rundata['whitelistregex'] = $whitelistregexarray;
    
    $dblocal = new Database( 'tools-db', $toolserver_username2, $toolserver_password2, 'cyberbot' );
    $dbwiki = new Database( 'enwiki.labsdb', $toolserver_username, $toolserver_password, 'enwiki_p' );
    $pagebuffer = array();
    $temp = array();
        
    $exceptions = $site->initPage( 'User:Cyberpower678/spam-exception.js' )->get_text();
    file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/exceptionsraw', $exceptions );
    if( $exceptions == null || $exceptions == "" || $exceptions == false ) exit(1);
    if( !is_null($exceptions) ) {
        $exceptions = explode( "\n", $exceptions );
        $exceptions = stripLines( $exceptions );
        $exceptions = str_replace( "<nowiki>", "", $exceptions );
        $exceptions = str_replace( "</nowiki>", "", $exceptions );
        foreach( $exceptions as $id=>$exception ) {
            if( str_replace( 'ns=', '', $exception ) != $exception ) $temp[] = array( 'ns'=>trim( substr( $exception, strlen("ns=") ) ) );
            else {
                $exception = explode( '|', $exception );
                $temp[] = array( 'page'=>trim( substr( $exception[0], strlen("page=") ) ), 'url'=>trim( substr( $exception[1], strlen("url=") ) ) );
            }
        }
        $exceptions = $temp;
        unset($temp);
    }

    if( !isset( $exceptions[0]['page'] ) && !isset( $exceptions[0]['url'] ) && !isset( $exceptions[0]['ns'] ) ) $exceptions = null;
    file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/exceptions.wl', serialize($exceptions) );
    updateData();
    
    $linkcount = $dblocal->select( "blacklisted_links", "COUNT(*) AS count" );
    $rundata['linkcount'] = $linkcount[0]['count'];
    $offset = 0;
    $rundata['offset'] = $offset;
    //compile the pages containing blacklisted URLs
    echo "Scanning {$linkcount[0]['count']} previously blacklisted links in the database...\n\n";
    $status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"0% (0 of {$linkcount[0]['count']})", 'scantype'=>'local' );
    updateStatus();
    $starttime = time();
    $rundata['starttime'] = $starttime;
    $todelete = array();
    $rundata['todelete'] = $todelete;
    updateData();
    localscan:
    while( $offset < $linkcount[0]['count'] ) {
        $i = $offset;
        $dblocal = new Database( 'tools-db', $toolserver_username2, $toolserver_password2, 'cyberbot' );
        $result = $dblocal->select( "blacklisted_links", "*", array(), array( 'limit'=>$offset.',5000') );
        if( isset($result['db']) ) {
            unset($result['db']);
            unset($result['result']);
            unset($result['pos']);
        }
        foreach( $result as $link ) {
            if( regexscan( $link['url'] ) ) {
                if( !isset( $pagebuffer[$link['page']]['object'] ) ) $pagebuffer[$link['page']]['object'] = $site->initPage( null, $link['page']);
                if( !isset( $pagebuffer[$link['page']]['title'] ) ) $pagebuffer[$link['page']]['title'] = $pagebuffer[$link['page']]['object']->get_title();                                                        
                $pagelinks = $pagebuffer[$link['page']]['object']->get_extlinks();
                if( !exceptionCheck( $pagebuffer[$link['page']]['title'], $link['url'] ) ) {
                    if( in_array_recursive($link['url'], $pagelinks) ) $pagebuffer[$link['page']]['urls'][] = $link['url'];
                    else {
                        $todelete[] = array( 'id'=>$link['page'], 'url'=>$link['url'] );
                        $d++;
                    }  
                }                                                                                                  
                else $e++;
            } else {
                $todelete[] = array( 'id'=>$link['page'], 'url'=>$link['url'] );                       
                $d++;
            }
            $i++;
            $completed = ($i/$linkcount[0]['count'])*100;
            $completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
            $completedby = time() + $completedin;
            $status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($i of {$linkcount[0]['count']})", 'scantype'=>'local', 'scaneta'=>round($completedby, 0) );
            updateStatus();
        }
        $offset += 5000;
        $rundata['offset'] = $offset;
        $rundata['todelete'] = $todelete;
        updateData();
    }
    foreach( $todelete as $item ) {
        $dblocal->delete( "blacklisted_links", array( 'url'=>$item['url'], 'page'=>$item['id'] ) );
    }
    unset( $todelete );
    unset( $rundata['todelete'] );
    unset( $item );

    if( !file_exists('/data/project/cyberbot/CyberbotII/spambotdata/global.bl') ) file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/global.bl', serialize($globalblacklistregexarray));
    else {
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/sblastrun/global.bl', file_get_contents('/data/project/cyberbot/CyberbotII/spambotdata/global.bl'));    
        $globalblacklistregexarray2 = array_diff($globalblacklistregexarray, unserialize(file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/global.bl' )));
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/global.bl', serialize($globalblacklistregexarray));
    }
    if( !file_exists('/data/project/cyberbot/CyberbotII/spambotdata/local.bl') || !file_exists('/data/project/cyberbot/CyberbotII/spambotdata/local.wl') ) {
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.wl', serialize($whitelistregexarray));
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.bl', serialize($blacklistregexarray));
    } else {
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/sblastrun/local.bl', file_get_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.bl'));
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/sblastrun/local.wl', file_get_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.wl'));
        $blacklistregexarray2 = array_merge(array_diff($blacklistregexarray, unserialize(file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/local.bl' ))), array_diff(unserialize(file_get_contents( '/data/project/cyberbot/CyberbotII/spambotdata/local.wl' )), $whitelistregexarray));
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.wl', serialize($whitelistregexarray));
        file_put_contents('/data/project/cyberbot/CyberbotII/spambotdata/local.bl', serialize($blacklistregexarray));  
        if( isset( $globalblacklistregexarray2 ) ) $blacklistregexarray3 = array_merge($blacklistregexarray2, $globalblacklistregexarray2);
    }
    $status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'scanprogress'=>"Calculating...", 'scantype'=>'replica' );
    updateStatus();

    unset( $globalblacklistregexarray2 );
    unset( $blacklistregexarray2 );
    unset( $globalblacklistregex );
    unset( $old );
    unset( $whitelistregexarray );
    echo count( $blacklistregexarray3 ) . " new regexes found to scan...\n\n";

    if( !empty($blacklistregexarray3) ) {
        $blacklistregex = buildSafeRegexes($blacklistregexarray3);
        unset( $blacklistregexarray3 );
        $rundata['blacklist'] = $blacklistregex;

        $linkcount = $dbwiki->select( "externallinks", "COUNT(*) AS count" );
        $rundata['linkcount'] = $linkcount[0]['count'];
        $offset = 0;
        $rundata['offset'] = $offset;
        $completed = ($offset/$linkcount[0]['count'])*100;
        //compile the pages containing blacklisted URLs
        echo "Scanning {$linkcount[0]['count']} externallinks in the database...\n\n";
        $status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($offset of {$linkcount[0]['count']})", 'scantype'=>'replica' );
        updateStatus();
        $starttime = time();
        $rundata['starttime'] = $starttime;
        updateData();
        wikiscan:
        while( $offset < $linkcount[0]['count'] ) {
            $dblocal = new Database( 'tools-db', $toolserver_username2, $toolserver_password2, 'cyberbot' );
            $result = $dbwiki->select( "externallinks", "*", array(), array( 'limit'=>$offset.',15000') );
            unset($result['db']);
            unset($result['result']);
            unset($result['pos']);
            //print_r( $result );    
            foreach( $result as $page ) {
                if( regexscan( $page['el_to'] ) ) {
                    if( !isset( $pagebuffer[$page['el_from']] ) ) $pagebuffer[$page['el_from']]['title'] = $site->initPage( null, $page['el_from'])->get_title();
                    if( !exceptionCheck( $pagebuffer[$page['el_from']]['title'], $page['el_to'] ) ) {
                        if( !isset( $pagebuffer[$page['el_from']]['urls'] ) ) $pagebuffer[$page['el_from']]['urls'] = array();
                        if( !in_array_recursive($page['el_to'], $pagebuffer[$page['el_from']]['urls']) ) {
                        $pagebuffer[$page['el_from']]['urls'][] = $page['el_to'];
                        }
                    } else $e++;
                    $dblocal->insert( "blacklisted_links", array( 'url'=>$page['el_to'], 'page'=>$page['el_from'] ) );
                    $a++;
                }
            }
            $offset+=15000;
            $rundata['offset'] = $offset;
            $completed = ($offset/$linkcount[0]['count'])*100;
            $completedin = 2*((((time() - $starttime)*100)/$completed)-(time() - $starttime));
            $completedby = time() + $completedin;
            $status = array( 'status' => 'scan', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($offset of {$linkcount[0]['count']})", 'scantype'=>'replica', 'scaneta'=>round($completedby, 0) );
            updateStatus();
            updateData();
        }
    }
    
    unset( $rundata['offset'] );
    unset( $rundata['linkcount'] );
    unset( $rundata['starttime'] );
    
    findrule:
    $globalblacklistregexarray = explode( "\n", $site2->initPage( 'Spam blacklist' )->get_text() );                                                                     
    $blacklistregexarray = explode( "\n", $site->initPage( 'MediaWiki:Spam-blacklist' )->get_text() );
    $whitelistregex = buildSafeRegexes( explode( "\n", $site->initPage( 'MediaWiki:Spam-whitelist' )->get_text() ) );

    $starttime = time();
    $i = 0;
    $count = count( $pagebuffer );
    $status = array( 'status' => 'process', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"0% (0 of $count)", 'scantype'=>'x' );
    updateStatus();
    
    //Check to make sure some things are still updated.  Remove outdated entries.
    $i = 0;
    foreach( $pagebuffer as $id=>$page ) {
        $i++;
        if( empty($page['urls']) || !isset($page['urls']) ) {unset( $pagebuffer[$id] ); continue;}
        //check if it has been whitelisted/deblacklisted during the database scan and make sure it isn't catching itself.
        $pagedata = $site->initPage( null, $id )->get_text();
        preg_match( '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', $pagedata, $template );
        $pagedata = str_replace( $template[0], "", $pagedata );
        foreach( $page['urls'] as $id2=>$url ) if( $pagebuffer[$id]['rules'][$id2]=findRule( $url ) === false || isWhitelisted( $url ) || strpos( $pagedata, $url ) === false ) unset( $pagebuffer[$id]['urls'][$id2] );
        if( isset( $pagebuffer[$id]['object'] ) ) unset( $pagebuffer[$id]['object'] );
        if( empty($page['urls']) || !isset($page['urls']) ) unset( $pagebuffer[$id] );
        $completed = ($i/$count)*100;
        $completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime); 
        $completedby = time() + $completedin;
        $status = array( 'status' => 'process', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>round($completed, 3)."% ($i of $count)", 'scantype'=>'x', 'scaneta'=>round($completedby, 0) ); 
        updateStatus();
    }
       
    echo "Added $a links to the local database!\n";
    echo "Deleted $d links from the local database!\n\n";
    echo "Ignored $e links on the blacklist!\n\n";
    
    file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/blacklistedlinks', print_r($pagebuffer, true) );
    //generate tags for each page and tag them.
    updateData();
    tagging:
    echo "Tagging pages...\n\n"; 
    $starttime = time();
    $i = 0;
    $count = count( $pagebuffer );
    $status = array( 'status' => 'tag', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>"0% (0 of $count)" );
    updateStatus();
    foreach( $pagebuffer as $pid=>$page ) {
        $i++;
        $pageobject = $site->initPage( null, $pid );
        $talkpageobject = $pageobject->get_talkID();
        if( !is_null( $talkpageobject ) ) $talkpageobject = $site->initPage( null, $talkpageobject );
        $out = "{{Blacklisted-links|1=";
        $out2 = "";
        foreach ( $page['urls'] as $l=>$url ) {
            $out2 .= "\n*$url";
            $out2 .= "\n*:''Triggered by <code>{$page['rules'][$l]['rule']}</code> on the {$page['rules'][$l]['blacklist']} blacklist''";    
        }
        $out .= "$out2|bot=Cyberbot II|invisible=false}}\n";
        $templates = $pageobject->get_templates();
        $buffer = $pageobject->get_text();
        if( $buffer == "" || is_null( $buffer ) ) continue;
        if( in_array_recursive( 'Template:Blacklisted-links', $templates) ) {
            preg_match( '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', $buffer, $template );
            $linkstrings = $template[3];
            $template = $template[0];
            if( trim( $out2 ) == trim( "\n".$linkstrings ) ) {
                goto placenotice;
            }
            $out = str_replace( trim( "\n".$linkstrings ), trim( $out2 ), $template );
            $buffer = str_replace( $template, $out, $buffer );
            $pageobject->edit( $buffer, "Updating {{[[Template:Blacklisted-links|Blacklisted-links]]}}.", true );      
        } else {
            preg_match( '/^\s*(?:((?:\s*\{\{\s*(?:about|correct title|dablink|distinguish|for|other\s?(?:hurricaneuses|people|persons|places|uses(?:of)?)|redirect(?:-acronym)?|see\s?(?:also|wiktionary)|selfref|the)\d*\s*(\|(?:\{\{[^{}]*\}\}|[^{}])*)?\}\})+(?:\s*\n)?)\s*)?/i', $buffer, $temp );
            $buffer = preg_replace( '/^\s*(?:((?:\s*\{\{\s*(?:about|correct title|dablink|distinguish|for|other\s?(?:hurricaneuses|people|persons|places|uses(?:of)?)|redirect(?:-acronym)?|see\s?(?:also|wiktionary)|selfref|the)\d*\s*(\|(?:\{\{[^{}]*\}\}|[^{}])*)?\}\})+(?:\s*\n)?)\s*)?/i', $temp[0].$out, $buffer );
            $pageobject->edit( $buffer, "Tagging page with {{[[Template:Blacklisted-links|Blacklisted-links]]}}.  Blacklisted links found." );
        }
        placenotice:
        if( !is_null( $talkpageobject ) ) {
            $out2 = "";
            foreach ( $page['urls'] as $l=>$url ) {
                $out2 .= "\n*<nowiki>$url</nowiki>";
                $out2 .= "\n*:''Triggered by <code>{$page['rules'][$l]['rule']}</code> on the {$page['rules'][$l]['blacklist']} blacklist''";    
            }
            $talkpagedata = $talkpageobject->get_text( false, "Blacklisted Links Found on the Main Page" );
            $talkout = "Cyberbot II has detected that page contains external links that have either been globally or locally blacklisted.\nLinks tend to be blacklisted because they have a history of being spammed, or are highly innappropriate for Wikipedia.\n";
            $talkout .= "This, however, doesn't necessaryily mean it's spam, or not a good link.\n";
            $talkout .= "If the link is a good link, you may wish to request whitelisting by going to the [[MediaWiki talk:Spam-whitelist|request page for whitelisting]].\n";
            $talkout .= "If you feel the link being caught by the blacklist is a false positive, or no longer needed on the blacklist, you may request the regex be removed or altered at the [[MediaWiki talk:Spam-blacklist|blacklist request page]].\n";
            $talkout .= "If the link is blacklisted globally and you feel the above applies you may request to whitelist it using the before mentioned request page, or request its removal, or alteration, at the [[meta:Talk:Spam Blacklist|request page on meta]].\n";
            $talkout .= "When requesting whitelisting, be sure to supply the link to be whitelisted and wrap the link in nowiki tags.\n";
            $talkout .= "The whitelisting process can take its time so once a request has been filled out, you may set the invisible parameter on the tag to true.\nPlease be aware that the bot will replace removed tags, and will remove misplaced tags regularly.\n\n";
            $talkout .= "'''Below is a list of links that were found on the main page:'''\n".$out2;
            $talkout .= "\n\nIf you would like me to provide more information on the talk page, contact [[User:Cyberpower678]] and ask him to program me with more info.\n\nFrom your friendly hard working bot.~~~~";
            if( $talkpagedata === false ) $talkpageobject->newsection( $talkout, "Blacklisted Links Found on the Main Page", "Notification of blacklisted links on the main page." );
        }
        $completed = ($i/$count)*100;
        $completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
        $completedby = time() + $completedin; 
        $status = array( 'status' => 'tag', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>round($completed, 3)."% ($i of $count)", 'editeta'=>round($completedby, 0) ); 
        updateStatus();   
    }

    //search for misplaced tags and remove them.
    removing:
    echo "Removing misplaced tags...\n\n";
    $transclusions = $site->embeddedin( "Template:Blacklisted-links", null, -1 );
    $i=0;
    $count = count( $transclusions );
    $status = array( 'status' => 'remove', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>"0% (0 of $count)" );
    updateStatus();
    foreach( $transclusions as $page ) {
        $i++;
        $pageobject = $site->initPage( $page );
        $talkpageobject = $pageobject->get_talkID();
        if( !is_null( $talkpageobject ) ) $talkpageobject = $site->initPage( null, $talkpageobject );
        if( isset( $pagebuffer[$pageobject->get_id()] ) ) continue;
        $buffer = $pageobject->get_text();
        if( $buffer == "" || is_null( $buffer ) ) continue;
        $buffer = preg_replace( array( '/\{\{Spam\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i', '/\{\{Blacklisted\-links\|(1\=)?(\n)?((.(\n)?)*?)\|bot\=Cyberbot II(\|invisible=(.*?))?\}\}(\n)?/i' ), '', $buffer );
        $pageobject->edit( $buffer, "Removing {{[[Template:Blacklisted-links|Blacklisted-links]]}}.  No blacklisted links were found.", true );
        if( !is_null( $talkpageobject ) ) {
            $talkpagedata = $talkpageobject->get_text( false, "Blacklisted Links Found on the Main Page" );
            if( $talkpagedata !== false ) {
                $talkout = $talkpagedata."\n\n{{done|Resolved}} This issue has been resolved, and I have therefore removed the tag, if not already done.  No further action is necessary.~~~~";
                $talkpagedata = str_replace( $talkpagedata, $talkout, $talkpageobject->get_text() );
                $talkpageobject->edit( $talkpagedata, "/* Blacklisted Links Found on the Main Page */ Resolved." );
            }
        }
        $completed = ($i/$count)*100;
        $completedin = (((time() - $starttime)*100)/$completed)-(time() - $starttime);
        $completedby = time() + $completedin; 
        $status = array( 'status' => 'remove', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x', 'editprogress'=>round($completed, 3)."% ($i of $count)", 'editeta'=>round($completedby, 0) ); 
        updateStatus();
    }
    $status = array( 'status' => 'idle', 'bladd'=>$a, 'bldeleted'=>$d, 'blexception'=>$e, 'scanprogress'=>"x", 'scantype'=>'x' );
    updateStatus();
    unset( $pagebuffer );
    unset( $transclusions );
    unset( $pageobject );
    unset( $dblocal );
    unset( $dbwiki );
    sleep(900);
    goto normalrun;

//This finds the rule that triggered the blacklist  
function findRule( $link ) {
    global $blacklistregexarray, $globalblacklistregexarray;
    $regexStart = '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
    $regexEnd = ')'.getRegexEnd( 0 );
    $lines = stripLines( $blacklistregexarray );
    foreach( $lines as $id=>$line ) {
        if( preg_match( $regexStart . str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $line) ) . $regexEnd, $link ) ) return array( 'blacklist'=>'local', 'rule'=>str_replace( '|', '&#124;', $line ) );
    }
    $lines = stripLines( $globalblacklistregexarray );
    foreach( $lines as $id=>$line ) {
        if( preg_match( $regexStart . str_replace( '/', '\/', preg_replace('|\\\*/|u', '/', $line) ) . $regexEnd, $link ) ) return array( 'blacklist'=>'global', 'rule'=>str_replace( '|', '&#124;', $line ) );
    }
    return false;
}
//Check if it's whitelisted since we started this run.
function isWhitelisted( $url ) {
    global $whitelistregex;
    foreach( $whitelistregex as $wregex ) if( preg_match($wregex, $url) ) return true;
    return false;
}

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
    return file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/sbstatus', serialize($status) );    
}
//generate a data file
function updateData() {
    global $rundata, $pagebuffer;
    return ( file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/rundata', serialize($rundata) ) && file_put_contents( '/data/project/cyberbot/CyberbotII/spambotdata/pagebuffer', serialize( $pagebuffer ) ) );
        
}
//make sure the page is on the exceptions list
function exceptionCheck( $page, $url ) {
    global $exceptions;
    if( is_null($exceptions) ) return false;
    foreach( $exceptions as $exception ) {
        if( isset( $exception['ns'] ) ) {
            $temp = explode( ':', $page );
            if( isset( $temp[1] ) && $temp[0] == $exception['ns'] ) return true;
        } else {
            if( $exception['page'] == '*' && $exception['url'] == '*' ) continue;
            if( $page == $exception['page'] || $exception['page'] == '*') {
                if( $url == $exception['url'] || $exception['url'] == '*' ) return true;
            }   
        }
    }
    return false;
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
