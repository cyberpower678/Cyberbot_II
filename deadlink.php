<?php
/*
This software has been created by Cyberpower678
This software analyzes dead-links and attempts to reliably find the proper archived page for it.
This software uses the MW API
This software uses the Wayback API
*/

ini_set('memory_limit','1G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( 'deadlink.config.inc.php' );

botLogon( USERNAME, $password );

$LINK_SCAN = 0;
$DEAD_ONLY = 2;
$TAG_OVERRIDE = 1;
$PAGE_SCAN = 0;
$ARCHIVE_BY_ACCESSDATE = 1;
$TOUCH_ARCHIVE = 0;
$NOTIFY_ON_TALK = 1;
$NOTIFY_ERROR_ON_TALK = 1;
$TALK_MESSAGE_HEADER = "Links modified on main page";
$TALK_MESSAGE = "Please review the links modified on the main page...";
$TALK_ERROR_MESSAGE = "There were problems archiving a few links on the page.";
$TALK_ERROR_MESSAGE_HEADER = "Notification of problematic links";
$DEADLINK_TAGS = array( "{{dead-link}}" );
$CITATION_TAGS = array( "{{cite web}}" );
$ARCHIVE_TAGS = array( "{{wayback}}" );
$IGNORE_TAGS = array( "{{cbignore}}" );
$DEAD_RULES = array();
$VERIFY_DEAD = 1;
$ARCHIVE_ALIVE = 1;
$runpagecount = 0;
$alreadyArchived = array();
$lastpage = false;
if( file_exists( DLAA ) ) $alreadyArchived = unserialize( file_get_contents( DLAA ) );
if( file_exists( IAPROGRESS.WIKIPEDIA ) ) $lastpage = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA ) );
if( file_exists( IAPROGRESS.WIKIPEDIA."c" ) ) {
    $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."c" ) );
    if( is_null($tmp) || empty($tmp) || empty($tmp['return']) || empty($tmp['pages'] ) ) {
        $return = "";
        $pages = false;
    } else {
        $return = $tmp['return'];
        $pages = $tmp['pages'];
    }
    $tmp = null;
    unset( $tmp );
} else {
    $pages = false;
    $return = "";
}
if( is_null( $alreadyArchived ) ) $alreadyArchived = array();
if( $lastpage === false || empty( $lastpage ) || is_null( $lastpage ) ) $lastpage = false;

while( true ) {
    echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
    $runstart = time();
    $runtime = 0;
    if( !file_exists( IAPROGRESS.WIKIPEDIA."stats" ) ) {
        $pagesAnalyzed = 0;
        $linksAnalyzed = 0;
        $linksFixed = 0;
        $linksTagged = 0;
        $pagesModified = 0;
        $linksArchived = 0;
    } else {
        $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."stats" ) );
        $pagesAnalyzed = $tmp['pagesAnalyzed'];
        $linksAnalyzed = $tmp['linksAnalyzed'];
        $linksFixed = $tmp['linksFixed'];
        $linksTagged = $tmp['linksTagged'];
        $pagesModified = $tmp['pagesModified'];
        $linksArchived = $tmp['linksArchived'];
        $runstart = $tmp['runstart'];
        $tmp = null;
        unset( $tmp );
    }
    $failedToArchive = array();
    $allerrors = array();
    $iteration = 0;
    //$config = $site->initPage( "User:Cyberbot II/Dead-links" )->get_text( true );
    $config = getPageText( "User:Cyberbot II/Dead-links" );
    preg_match( '/\n\|LINK_SCAN\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $LINK_SCAN = $param1[1];
    preg_match( '/\n\|DEAD_ONLY\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEAD_ONLY = $param1[1];
    preg_match( '/\n\|TAG_OVERRIDE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TAG_OVERRIDE = $param1[1];
    preg_match( '/\n\|PAGE_SCAN\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $PAGE_SCAN = $param1[1];
    preg_match( '/\n\|ARCHIVE_BY_ACCESSDATE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_BY_ACCESSDATE = $param1[1];
    preg_match( '/\n\|TOUCH_ARCHIVE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TOUCH_ARCHIVE = $param1[1];
    preg_match( '/\n\|NOTIFY_ON_TALK\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $NOTIFY_ON_TALK = $param1[1];
    preg_match( '/\n\|NOTIFY_ERROR_ON_TALK\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $NOTIFY_ERROR_ON_TALK = $param1[1];
    preg_match( '/\n\|TALK_MESSAGE_HEADER\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE_HEADER = $param1[1];
    preg_match( '/\n\|TALK_MESSAGE\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE = $param1[1];
    preg_match( '/\n\|TALK_ERROR_MESSAGE_HEADER\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_ERROR_MESSAGE_HEADER = $param1[1];
    preg_match( '/\n\|TALK_ERROR_MESSAGE\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_ERROR_MESSAGE = $param1[1];
    preg_match( '/\n\|DEADLINK_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEADLINK_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|CITATION_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $CITATION_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|ARCHIVE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|IGNORE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $IGNORE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|VERIFY_DEAD\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $VERIFY_DEAD = $param1[1];
    preg_match( '/\n\|ARCHIVE_ALIVE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_ALIVE = $param1[1];
    foreach( $DEAD_RULES as $tid => $rule ) $DEAD_RULES[$tid] = explode( ":", $rule );
    foreach( $DEADLINK_TAGS as $tid=>$tag ) $DEADLINK_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $CITATION_TAGS as $tid=>$tag ) $CITATION_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $ARCHIVE_TAGS as $tid=>$tag ) $ARCHIVE_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $IGNORE_TAGS as $tid=>$tag ) $IGNORE_TAGS[$tid] = preg_quote( $tag, '/' );
    
    //Get started with the run
    do {
        $iteration++;
        if( $iteration !== 1 ) {
            $lastpage = false;
            $pages = false;
        }
        //fetch the pages we want to analyze and edit.  This fetching process is done in batches to preserve memory. 
        if( DEBUG === true && $debugStyle == "test" ) {     //This fetches a specific page for debugging purposes
            echo "Fetching test pages...\n";
            $pages = array( $debugPage );   
        } elseif( $PAGE_SCAN == 0 ) {                       //This fetches all the articles, or a batch of them.
            echo "Fetching";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
            echo " article pages...\n";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
                 $pages = getAllArticles( 5000, $return, false );
                 $return = $pages[1];
                 $pages = $pages[0];
            } elseif( $iteration !== 1 || $pages === false ) {
                $pages = getAllArticles( 5000, $return, $lastpage );
                $return = $pages[1];
                $pages = $pages[0];
                file_put_contents( IAPROGRESS.WIKIPEDIA."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );     
            } else {
                if( $lastpage !== false ) {
                    foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
                    $pages = array_slice( $pages, $tcount + 1 );
                }
            }
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n";
        } elseif( $PAGE_SCAN == 1 ) {                       //This fetches only articles with a deadlink tag in it, or a batch of them
            echo "Fetching";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
            echo " articles with links marked as dead...\n";
            if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
                $pages = getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $DEADLINK_TAGS ) ) ) ), $debugStyle, $return );
                $return = $pages[1];
                $pages = $pages[0];
            } elseif( $iteration !== 1 || $pages === false ) {
                $pages = getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $DEADLINK_TAGS ) ) ) ), 5000, $return );
                $return = $pages[1];
                $pages = $pages[0];
                file_put_contents( IAPROGRESS.WIKIPEDIA."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );
            } else {
                if( $lastpage !== false ) {
                    foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
                    $pages = array_slice( $pages, $tcount + 1 );
                }
            }
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n"; 
        }
        
        //Begin page analysis
        if( WORKERS === false || DEBUG === true ) {
            foreach( $pages as $tid => $tpage ) {
                $pagesAnalyzed++;
                $runpagecount++;
                if( WORKERS === false ) $stats = analyzePage( $tpage['title'], $tpage['pageid'], $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN );
                else {
                    $testbot[$tid] = new ThreadedBot( $tpage['title'], $tpage['pageid'], $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, "test" );
                    $testbot[$tid]->run();
                    $stats = $testbot[$tid]->result;
                }
                if( $stats['pagemodified'] === true ) $pagesModified++;
                $linksAnalyzed += $stats['linksanalyzed'];
                $linksArchived += $stats['linksarchived'];
                $linksFixed += $stats['linksrescued'];
                $linksTagged += $stats['linkstagged'];
                $alreadyArchived = array_merge( $stats['newlyArchived'], $alreadyArchived );
                $failedToArchive = array_merge( $failedToArchive, $stats['archiveProblems'] );
                $allerrors = array_merge( $allerrors, $stats['errors'] );
                if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
                file_put_contents( DLAA, serialize( $alreadyArchived ) );
                if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
            }
        } else {   
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) && ($handle = opendir( IAPROGRESS.WIKIPEDIA."workers" )) ) {
                 while( false !== ( $entry = readdir( $handle ) ) ) {
                    if( $entry == "." || $entry == ".." ) continue;
                    $tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA."workers/$entry" ) );
                    if( $tmp === false ) {
                        $tmp = null;
                        unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" );
                        continue;
                    }
                    $pagesAnalyzed++;
                    if( $tmp['pagemodified'] === true ) $pagesModified++;
                    $linksAnalyzed += $tmp['linksanalyzed'];
                    $linksArchived += $tmp['linksarchived'];
                    $linksFixed += $tmp['linksrescued'];
                    $linksTagged += $tmp['linkstagged'];
                    $tmp = null;
                    unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" ); 
                }
                unset( $tmp ); 
                file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) ); 
            }
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) closedir( $handle );
            $workerQueue = new Pool( $workerLimit );
            foreach( $pages as $tid => $tpage ) {
                $pagesAnalyzed++;
                $runpagecount++;
                echo "Submitted {$tpage['title']}, job ".($tid+1)." for analyzing...\n";
                $workerQueue->submit( new ThreadedBot( $tpage['title'], $tpage['pageid'], $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, $tid ) );       
                if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
            }
            $workerQueue->shutdown();  
            $workerQueue->collect(
            function( $thread ) {  
                global $pagesModified, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $alreadyArchived, $failedToArchive, $allerrors;
                $stats = $thread->result;
                if( $stats['pagemodified'] === true ) $pagesModified++;
                $linksAnalyzed += $stats['linksanalyzed'];
                $linksArchived += $stats['linksarchived'];
                $linksFixed += $stats['linksrescued'];
                $linksTagged += $stats['linkstagged'];
                $alreadyArchived = array_merge( $stats['newlyArchived'], $alreadyArchived );
                $failedToArchive = array_merge( $failedToArchive, $stats['archiveProblems'] );
                $allerrors = array_merge( $allerrors, $stats['errors'] );
                $stats = null;
                unset( $stats );
                return $thread->isGarbage();
            });
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) &&  $handle = opendir( IAPROGRESS.WIKIPEDIA."workers" ) ) {
                 while( false !== ( $entry = readdir( $handle ) ) ) {
                    if( $entry == "." || $entry == ".." ) continue;
                    unlink( IAPROGRESS.WIKIPEDIA."workers/$entry" ); 
                }
            }
            if( file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) closedir( $handle );
            echo "STATUS REPORT:\nLinks analyzed so far: $linksAnalyzed\nLinks archived so far: $linksArchived\nLinks fixed so far: $linksFixed\nLinks tagged so far: $linksTagged\n\n";
            file_put_contents( DLAA, serialize( $alreadyArchived ) );
            file_put_contents( IAPROGRESS.WIKIPEDIA."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
        }
        unset( $pages );
    } while( !empty( $return ) && DEBUG === false && LIMITEDRUN === false ); 
    $runend = time();
    $runtime = $runend-$runstart;
    echo "Updating list of failed archive attempts...\n\n";
    $out = "";
    foreach( $failedToArchive as $id=>$link ) $out .= "\n*$link with error '''{$allerrors[$id]}'''";
    if( DEBUG === false || LIMITEDRUN === true ) edit( "User:Cyberbot II/Links that won't archive", $out, "Updating list of links that won't archive. #IABot", true, false, true, "append" );
    echo "Printing log report, and starting new run...\n\n";
    if( DEBUG === false && LIMITEDRUN === false ) generateLogReport();
    if( file_exists( IAPROGRESS.WIKIPEDIA."stats" ) && LIMITEDRUN === false ) unlink( IAPROGRESS.WIKIPEDIA."stats" );  
    if( DEBUG === false && LIMITEDRUN === false ) sleep(10);
    if( DEBUG === true || LIMITEDRUN === true ) exit(0);                                           
}

//Create run log information
function generateLogReport() {
    global $NOTIFY_ON_TALK, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $runtime, $pagesAnalyzed, $pagesModified;
    $log = getPageText( "User:Cyberbot II/Dead-Links Log" );
    $entry = "|-\n|";
    $entry .= date( 'H:i, j F Y (\U\T\C)', $runstart );
    $entry .= "||";
    $entry .= date( 'H:i, j F Y (\U\T\C)', $runend );
    $entry .= "||";
    $entry .= date( 'z:H:i:s', $runend-$runstart );
    $entry .= "||";
    $entry .= $pagesAnalyzed;
    $entry .= "||";
    $entry .= $pagesModified;
    $entry .= "||";
    $entry .= $linksAnalyzed;
    $entry .= "||";
    $entry .= $linksFixed;
    $entry .= "||";
    $entry .= $linksTagged;
    $entry .= "||";
    $entry .= $linksArchived;
    $entry .= "\n";
    $log = str_replace( "|}", $entry."|}", $log );
    edit( "User:Cyberbot II/Dead-Links Log", $log, "Updating run log with run statistics #IABot" );
    return;
}
//Construct string
function generateString( $link, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS ) {
    $out = "";
    $mArray = mergeNewData( $link );
    $tArray = array_merge( $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS ); 
    $regex = '/('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}/i';
    $remainder = preg_replace( $regex, "", $mArray['remainder'] );
    //Beginning of the string
    if( $link['link_type'] == "reference" ) {
        $tArray = array();
        if( isset( $link['reference']['parameters'] ) && isset( $link['newdata']['parameters'] ) ) $tArray = array_merge( $link['reference']['parameters'], $link['newdata']['parameters'] );
        elseif( isset( $link['reference']['parameters'] ) ) $tArray = $link['reference']['parameters'];
        elseif( isset( $link['newdata']['parameters'] ) ) $tArray = $link['reference']['parameters'];
        $out .= "<ref";
        foreach( $tArray as $parameter => $value ) {
            $out .= " $parameter=$value";
        }
        $out .= ">";
        if( $mArray['link_type'] == "link" || ( $mArray['is_archive'] === true && $mArray['archive_type'] == "link" ) ) $out .= $mArray['link_string'];
        elseif( $mArray['link_type'] == "template" ) {
            $out .= "{{".$mArray['link_template']['name'];
            foreach( $mArray['link_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
            $out .= "}}";
        }  
    } elseif( $link['link_type'] == "externallink" ) {
        $out .= str_replace( $link['externallink']['remainder'], "", $link['string'] );
    } elseif( $link['link_type'] == "template" ) {
        $out .= "{{".$link['name'];
        foreach( $mArray['link_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
        $out .= "}}";
    }
    if( $mArray['tagged_dead'] === true ) {
        if( $mArray['tag_type'] == "template" ) {
            $out .= "{{".$mArray['tag_template']['name'];
            foreach( $mArray['tag_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
            $out .= "}}";
        }
    }
    $out .= $remainder;
    if( $mArray['has_archive'] === true ) {
        if( $link['link_type'] == "externallink" ) {
            $out = str_replace( $mArray['url'], $mArray['archive_url'], $out );
        } elseif( $mArray['archive_type'] == "template" ) {
            $out .= " {{".$mArray['archive_template']['name'];
            foreach( $mArray['archive_template']['parameters'] as $parameter => $value ) $out .= "|$parameter=$value ";
            $out .= "}}";  
        }
    }
    if( $link['link_type'] == "reference" ) $out .= "</ref>";
    return $out;
}

//Merge the new data in a custom array_merge function
function mergeNewData( $link, $recurse = false ) {
    $returnArray = array();
    if( $recurse !== false ) {
        foreach( $link as $parameter => $value ) {
            if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) $returnArray[$parameter] = $recurse[$parameter];
            elseif( isset($recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = mergeNewData( $value, $recurse[$parameter] );
            elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
            else $returnArray[$parameter] = $value; 
        }
        foreach( $recurse as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
        return $returnArray;
    }
    foreach( $link[$link['link_type']] as $parameter => $value ) {
        if( isset( $link['newdata'][$parameter] ) && !is_array( $link['newdata'][$parameter] )  && !is_array( $value ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
        elseif( isset( $link['newdata'][$parameter] ) && is_array( $link['newdata'][$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = mergeNewData( $value, $link['newdata'][$parameter] );
        elseif( isset( $link['newdata'][$parameter] ) ) $returnArray[$parameter] = $link['newdata'][$parameter];
        else $returnArray[$parameter] = $value;    
    }
    foreach( $link['newdata'] as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
    return $returnArray;
}

//Verify that newdata is actually different from old
function newIsNew( $link ) {
    $t = false;
    foreach( $link['newdata'] as $parameter => $value ) {
        if( !isset( $link[$link['link_type']][$parameter] ) || $value != $link[$link['link_type']][$parameter] ) $t = true;
    }
    return $t;
}

//Gather and parse all references and return as organized array
function getReferences( $page, &$history, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS, $CITATION_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget ) {
    $linksAnalyzed = 0;
    $tArray = array_merge( $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS );
    $returnArray = array();   
    $regex = '/<ref([^\/]*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}.*?)?<\/ref>(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?\}\})*/i';
    preg_match_all( $regex, preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $page ), $params );
    foreach( $params[0] as $tid=>$fullmatch ) {
        $linksAnalyzed++;
        if( !isset( $returnArray[$tid] ) ) {
            $returnArray[$tid]['link_type'] = "reference";
            //Fetch parsed reference content
            $returnArray[$tid]['reference'] = getLinkDetails( $params[2][$tid], $params[3][$tid].$params[5][$tid], $history, $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget ); 
            $returnArray[$tid]['string'] = $params[0][$tid];
        }
        //Fetch reference parameters
        if( !empty( $params[1][$tid] ) ) $returnArray[$tid]['reference']['parameters'] = getReferenceParameters( $params[1][$tid] );
        if( empty( $params[2][$tid] ) && empty( $params[3][$tid] ) ) {
            unset( $returnArray[$tid] );
            continue;
        }
    }
    $returnArray['count'] = $linksAnalyzed;
    return $returnArray;   
}

//Gather and parse all external links including references
function getExternalLinks( $page, &$history, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS, $CITATION_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget ) {
    $linksAnalyzed = 0;
    $tArray = array_merge( $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS );
    $returnArray = array();
    $regex = '/(<ref([^\/]*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}.*?)?<\/ref>(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?\}\})*|\[{1}((?:https?:)?\/\/.*?)\s?.*?\]{1}.*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}\s*?)*?|(('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).').*?\}\})\s*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\}\s*?)*?)/i';
    preg_match_all( $regex, preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $page ), $params );
    foreach( $params[0] as $tid=>$fullmatch ) {
        $linksAnalyzed++;
        if( !empty( $params[2][$tid] ) || !empty( $params[2][$tid] ) || !empty( $params[3][$tid] ) ) {
            $returnArray[$tid]['link_type'] = "reference";
            //Fetch parsed reference content
            $returnArray[$tid]['reference'] = getLinkDetails( $params[3][$tid], $params[4][$tid].$params[6][$tid], $history, $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget ); 
            $returnArray[$tid]['string'] = $params[0][$tid];
            //Fetch reference parameters
            if( !empty( $params[2][$tid] ) ) $returnArray[$tid]['reference']['parameters'] = getReferenceParameters( $params[2][$tid] );
            if( empty( $params[3][$tid] ) && empty( $params[4][$tid] ) ) {
                unset( $returnArray[$tid] );
                continue;
            }
        } elseif( !empty( $params[8][$tid] ) ) {
            $returnArray[$tid]['link_type'] = "externallink";
            //Fetch parsed reference content
            $returnArray[$tid]['externallink'] = getLinkDetails( $params[0][$tid], $params[9][$tid], $history, $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget ); 
            $returnArray[$tid]['string'] = $params[0][$tid];
        } elseif( !empty( $params[11][$tid] ) || !empty( $params[13][$tid] ) ) {
            $returnArray[$tid]['link_type'] = "template";
            //Fetch parsed reference content
            $returnArray[$tid]['template'] = getLinkDetails( $params[11][$tid], $params[13][$tid], $history, $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $paget );
            $returnArray[$tid]['name'] = str_replace( "{{", "", $params[12][$tid] );
            $returnArray[$tid]['string'] = $params[0][$tid];
        }
    }
    $returnArray['count'] = $linksAnalyzed;
    return $returnArray; 
}

//Read and parse the reference string
function getReferenceParameters( $refparamstring ) {
    $returnArray = array();
    preg_match_all( '/(\S*)\s*=\s*(".*?"|\'.*?\'|\S*)/i', $refparamstring, $params );
    foreach( $params[0] as $tid => $tvalue ) {
        $returnArray[$params[1][$tid]] = $params[2][$tid];   
    }
    return $returnArray;
}

//This is the parsing engine.  It picks apart the string in every detail, so the bot can accurately construct an appropriate reference.
function getLinkDetails( $linkString, $remainder, &$history, $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $page ) {
    $returnArray = array();
    $returnArray['link_string'] = $linkString;
    $returnArray['remainder'] = $remainder;              
    if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $IGNORE_TAGS ) ).')\s*?\|?(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params ) || preg_match( '/('.str_replace( "\}\}", "", implode( '|', $IGNORE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        return array( 'ignore' => true );
    }
    if( strpos( $linkString, "archive.org" ) !== false && !preg_match( '/('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        $returnArray['has_archive'] = true;
        $returnArray['is_archive'] = true;
        $returnArray['archive_type'] = "link";
        $returnArray['link_type'] = "x";
        if( preg_match( '/archive\.org\/(web\/)?(\d{14}|\*)\/(\S*)\s/i', $linkString, $returnArray['url'] ) ) {
            if( $returnArray['url'][2] != "*" ) $returnArray['archive_time'] = strtotime( $returnArray['url'][2] );
            else $returnArray['archive_time'] = "x";
            $returnArray['archive_url'] = trim( $returnArray['url'][0] );
            $returnArray['url'] = $returnArray['url'][3];
        } else {
            return array( 'ignore' => true );  
        }
        $returnArray['access_time'] = $returnArray['archive_time'];
        $returnArray['tagged_dead'] = true;
        $returnArray['tag_type'] = "implied"; 
    } elseif( strpos( $linkString, "archiveurl" ) === false && strpos( $linkString, "archive-url" ) === false && strpos( $linkString, "web.archive.org" ) !== false && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        $returnArray['has_archive'] = true;
        $returnArray['is_archive'] = true;
        $returnArray['archive_type'] = "invalid";
        $returnArray['link_type'] = "template";
        $returnArray['link_template'] = array();
        $returnArray['link_template']['parameters'] = getTemplateParameters( $params[2] );
        $returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
        $returnArray['link_template']['string'] = $params[0];
        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)/i', $returnArray['link_template']['parameters']['url'], $params2 ) ) {
            $returnArray['archive_time'] = strtotime( $params2[2] );
            $returnArray['archive_url'] = trim( $params2[0] );
            $returnArray['url'] = $params2[3];    
        } else {
            return array( 'ignore' => true );
        }
        $returnArray['tagged_dead'] = true;
        $returnArray['tag_type'] = "implied";
        if( !isset( $returnArray['link_template']['parameters']['accessdate'] ) && !isset( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = $returnArray['archive_time'];   
        else {
            if( isset( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
            else $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
        }
    } elseif( empty( $linkString ) && preg_match( '/('.str_replace( "\}\}", "", implode( '|', $ARCHIVE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params ) ) {
        $returnArray['has_archive'] = true;
        $returnArray['is_archive'] = true;
        $returnArray['archive_type'] = "template";
        $returnArray['link_type'] = "x";
        $returnArray['archive_template'] = array();
        $returnArray['archive_template']['parameters'] = getTemplateParameters( $params[2] );
        $returnArray['archive_template']['name'] = str_replace( "{{", "", $params[1] );
        $returnArray['archive_template']['string'] = $params[0];
        $returnArray['tagged_dead'] = true;
        $returnArray['tag_type'] = "implied";
        if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
        else $returnArray['archive_time'] = "x";
        if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
        elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
        elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
        else $returnArray['archive_url'] = "x";  
        
        //Check for a malformation or template misuse.
        if( $returnArray['archive_url'] == "x" ) {
            if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
                if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[2] );
                    $returnArray['archive_url'] = $params3[0];
                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                } else {
                    $returnArray['archive_type'] = "invalid";
                } 
            } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[2] );
                    $returnArray['archive_url'] = $params3[0];
                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                } else {
                    $returnArray['archive_type'] = "invalid";
                }
            } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                if( preg_match( 'archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[2] );
                    $returnArray['archive_url'] = $params3[0];
                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
                } else {
                    $returnArray['archive_type'] = "invalid";
                }
            }
        }
        $returnArray['access_time'] = $returnArray['archive_time'];
    } elseif( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        $returnArray['tagged_dead'] = false;
        if( !empty( $remainder ) ) {
            $returnArray['has_archive'] = false;
            $returnArray['is_archive'] = false;
            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $ARCHIVE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params2 ) ) {
                $returnArray['has_archive'] = true;
                $returnArray['is_archive'] = false;
                $returnArray['archive_type'] = "template";
                $returnArray['archive_template'] = array();
                $returnArray['archive_template']['parameters'] = getTemplateParameters( $params2[2] );
                $returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
                $returnArray['archive_template']['string'] = $params2[0];
                if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
                else $returnArray['archive_time'] = "x";
                if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
                elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
                elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
                else $returnArray['archive_url'] = "x";  
                
                //Check for a malformation or template misuse.
                if( $returnArray['archive_url'] == "x" ) {
                    if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        } 
                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    }
                }
            }
            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $DEADLINK_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
                $returnArray['tagged_dead'] = true;
                $returnArray['tag_type'] = "template";
                $returnArray['tag_template']['parameters'] = getTemplateParameters( $params2[2] );
                $returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
                $returnArray['tag_template']['string'] = $params2[0];
            } else {
                $returnArray['tagged_dead'] = false;
            }  
        } else {
            $returnArray['has_archive'] = false;
            $returnArray['is_archive'] = false;
        } 
        $returnArray['link_type'] = "template";
        $returnArray['link_template'] = array();
        $returnArray['link_template']['parameters'] = getTemplateParameters( $params[2] );
        $returnArray['link_template']['name'] = str_replace( "{{", "", $params[1] );
        $returnArray['link_template']['string'] = $params[0];
        if( isset( $returnArray['link_template']['parameters']['url'] ) ) $returnArray['url'] = $returnArray['link_template']['parameters']['url'];
        else return array( 'ignore' => true );
        if( isset( $returnArray['link_template']['parameters']['accessdate']) && !empty( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
        elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) && !empty( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
        else $returnArray['access_time'] = getTimeAdded( $returnArray['url'], $history, $page );
        if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archiveurl'];  
        if( isset( $returnArray['link_template']['parameters']['archive-url'] ) ) $returnArray['archive_url'] = $returnArray['link_template']['parameters']['archive-url'];
        if( isset( $returnArray['link_template']['parameters']['archiveurl'] ) || isset( $returnArray['link_template']['parameters']['archive-url'] ) ) {
            $returnArray['archive_type'] = "parameter";
            $returnArray['has_archive'] = true;
            $returnArray['is_archive'] = true;
        }
        if( isset( $returnArray['link_template']['parameters']['archivedate'] ) ) $returnArray['archive_time'] = $returnArray['link_template']['parameters']['archivedate'];
        if( isset( $returnArray['link_template']['parameters']['archive-date'] ) ) $returnArray['archive_time'] = $returnArray['link_template']['parameters']['archive-date'];
        if( ( isset( $returnArray['link_template']['parameters']['deadurl'] ) && $returnArray['link_template']['parameters']['deadurl'] == "yes" ) || ( ( isset( $returnArray['link_template']['parameters']['dead-url'] ) && $returnArray['link_template']['parameters']['dead-url'] == "yes" ) ) ) {
            $returnArray['tagged_dead'] = true;
            $returnArray['tag_type'] = "parameter";
        }
    } elseif( preg_match( '/((?:https?:)?\/\/.*?)(\s|\])/i', $linkString, $params ) ) {
        $returnArray['url'] = $params[1];
        $returnArray['link_type'] = "link"; 
        $returnArray['access_time'] = getTimeAdded( $returnArray['url'], $history, $page );
        $returnArray['is_archive'] = false;
        $returnArray['tagged_dead'] = false;
        $returnArray['has_archive'] = false;
        if( !empty( $remainder ) ) {
            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $ARCHIVE_TAGS ) ).')\s?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
                $returnArray['has_archive'] = true;
                $returnArray['is_archive'] = false;
                $returnArray['archive_type'] = "template";
                $returnArray['archive_template'] = array();
                $returnArray['archive_template']['parameters'] = getTemplateParameters( $params2[2] );
                $returnArray['archive_template']['name'] = str_replace( "{{", "", $params2[1] );
                $returnArray['archive_template']['string'] = $params2[0];
                if( isset( $returnArray['archive_template']['parameters']['date'] ) ) $returnArray['archive_time'] = strtotime( $returnArray['archive_template']['parameters']['date'] );
                else $returnArray['archive_time'] = "x";
                if( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['url'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['url']}";
                elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters'][1] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters'][1]}";
                elseif( isset( $returnArray['archive_template']['parameters']['date'] ) && isset( $returnArray['archive_template']['parameters']['site'] ) ) $returnArray['archive_url'] = "https://web.archive.org/web/{$returnArray['archive_template']['parameters']['date']}/{$returnArray['archive_template']['parameters']['site']}";
                else $returnArray['archive_url'] = "x";  
                
                //Check for a malformation or template misuse.
                if( $returnArray['archive_url'] == "x" ) {
                    if( isset( $returnArray['archive_template']['parameters'][1] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        } 
                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                        if( preg_match( '/archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[2] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['url']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    }
                }
            }
            if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $DEADLINK_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params2 ) ) {
                $returnArray['tagged_dead'] = true;
                $returnArray['tag_type'] = "template";
                $returnArray['tag_template']['parameters'] = getTemplateParameters( $params2[2] );
                $returnArray['tag_template']['name'] = str_replace( "{{", "", $params2[1] );
                $returnArray['tag_template']['string'] = $params2[0];
            } else {
                $returnArray['tagged_dead'] = false;
            }    
        } else {
            $returnArray['has_archive'] = false;
        }
    } else {
        $returnArray['ignore'] = true;
    }
    if( !isset( $returnArray['ignore'] ) && ( $TOUCH_ARCHIVE == 1 || $returnArray['has_archive'] === false ) && $VERIFY_DEAD == 1 ) isLinkDead( $returnArray );
    else $returnArray['is_dead'] = null;
    return $returnArray;
}
//Look for the time the link was added.
function getTimeAdded( $url, &$history, $page ) {
    
    //Return current time for an empty input.
    if( empty( $url ) ) return time();
    
    //Use the database to execute the search if available
    if( USEWIKIDB === true && ($db = mysqli_connect( WIKIHOST, WIKIUSER, WIKIPASS, WIKIDB, WIKIPORT )) ) {
        $res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE CONTAINS(".TEXTTABLE.".old_id, '".mysqli_escape_string( $db, $url )."') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );       
        //$res = mysqli_query( $db, "SELECT ".REVISIONTABLE.".rev_timestamp FROM ".REVISIONTABLE." JOIN ".TEXTTABLE." ON ".REVISIONTABLE.".rev_id = ".TEXTTABLE.".old_id WHERE ".TEXTTABLE.".old_id LIKE '%".mysqli_escape_string( $db, $url )."%') ORDER BY ".REVISIONTABLE.".rev_timestamp ASC LIMIT 0,1;" );
        $tmp = mysqli_fetch_assoc( $res );
        mysqli_free_result( $res );
        unset( $res );
        if( $tmp !== false ) {
            mysqli_close( $db );
            unset( $db );
            return strtotime( $tmp['rev_timestamp'] );
        }
    }
    if( isset( $db ) ) {
        mysqli_close( $db );
        unset( $db );
        echo "ERROR: Wiki database usage failed.  Defaulting to API Binary search...\n";
    }
    
    //Do a binary search
    if( empty( $history ) ) $history = getPageHistory( $page );
    
    $range = count( $history );
    $upper = $range - 1;
    $lower = 0;
    $needle = round( $range/2 ) - 1;
    $time = time();
    
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    
    for( $stage = 2; $stage <= 16; $stage++ ) {
        $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=timestamp%7Ccontent&rvlimit=1&rawcontinue=&rvstartid={$history[$needle]['revid']}&rvendid={$history[$needle]['revid']}&titles=".urlencode( $page );
        curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
        $data = curl_exec( $curl ); 
        $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
        $data2 = trim( substr( $data, $header_size ) );
        $data = null;
        $data = unserialize( $data2 );
        $data2 = null; 
        if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
            if( isset( $template['revisions'] ) ) $revision = $template['revisions'][0];
            else $revision = false;
        } else $revision = false;
        if( $revision === false ) break;
        else {
            if( isset( $revision['*'] ) ) {
                if( strpos( $revision['*'], $url ) === false ) {
                    $lower = $needle + 1;
                    $needle += round( $range/(pow( 2, $stage )) );
                } else {
                    $upper = $needle;
                    $needle -= round( $range/(pow( 2, $stage )) );
                }   
            } else break;
        }
        //If we narrowed it to a sufficiently low amount or if the needle isn't changing, why continue?
        if( $upper - $lower <= 5 || $needle == $upper || ($needle + 1) == $lower ) break;
    }
    
    $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=timestamp%7Ccontent&rvlimit=max&rawcontinue=&rvstartid={$history[$lower]['revid']}&rvendid={$history[$upper]['revid']}&titles=".urlencode( $page );
    curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data2 = trim( substr( $data, $header_size ) );
    $data = null;
    $data = unserialize( $data2 );
    $data2 = null; 
    curl_close( $curl );  
    unset( $curl, $data2 );
    
    if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
        if( isset( $template['revisions'] ) ) $revisions = $template['revisions'];
        else {
            $revisions = null;
            unset( $revisions );
            return $time;   
        }
    } else {
        $revisions = null;
        unset( $revisions );
        return $time;   
    }
    
    foreach( $revisions as $revision ) {
        $time = strtotime( $revision['timestamp'] ); 
        if( !isset( $revision['*'] ) ) continue;
        if( strpos( $revision['*'], $url ) !== false ) break;  
    }
    $revision = $revisions = null;
    unset( $revisions, $revision );
    return $time;
}

//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
function getTemplateParameters( $templateString ) {
    $returnArray = array();
    $tArray = array();
    while( true ) {
        $offset = 0;        
        $loopcount = 0;
        $pipepos = strpos( $templateString, "|", $offset);
        $tstart = strpos( $templateString, "{{", $offset );   
        $tend = strpos( $templateString, "}}", $offset );
        $lstart = strpos( $templateString, "[[", $offset );
        $lend = strpos( $templateString, "]]", $offset );
        while( true ) {
            $loopcount++;
            if( $lend !== false && $tend !== false ) $offset = min( array( $tend, $lend ) ) + 1;
            elseif( $lend === false ) $offset = $tend + 1;
            else $offset = $lend + 1;     
            while( ( $tstart < $pipepos && $tend > $pipepos ) || ( $lstart < $pipepos && $lend > $pipepos ) ) $pipepos = strpos( $templateString, "|", $pipepos + 1 );
            $tstart = strpos( $templateString, "{{", $offset );   
            $tend = strpos( $templateString, "}}", $offset );
            $lstart = strpos( $templateString, "[[", $offset );
            $lend = strpos( $templateString, "]]", $offset );
            if( ( $pipepos < $tstart || $tstart === false ) && ( $pipepos < $lstart || $lstart === false ) ) break;
            if( $loopcount >= 500 ) return false;
        }
        if( $pipepos !== false ) {  
            $tArray[] = substr( $templateString, 0, $pipepos  );
            $templateString = substr_replace( $templateString, "", 0, $pipepos + 1 );
        } else {
            $tArray[] = $templateString;
            break;
        }
    }
    $count = 0;
    foreach( $tArray as $tid => $tstring ) $tArray[$tid] = explode( '=', $tstring, 2 );
    foreach( $tArray as $array ) {
        $count++;
        if( count( $array ) == 2 ) $returnArray[trim( $array[0] )] = trim( $array[1] );
        else $returnArray[ $count ] = trim( $array[0] );
    }
    return $returnArray;
}

//Verify if link is a dead-link
function isLinkDead( &$referenceArray ) {
    return false;
    $referenceArray['is_dead'] = false;
    if( isset( $referenceArray['url'] ) ) {
        //$page = $site->get_http()->get( $referenceArray['url'] );
        //$code = $site->get_http()->get_HTTP_code();
        if( $code != 200 ) $referenceArray['is_dead'] = true;    
    } else {
        $referenceArray['is_dead'] = false;
    }
    return $referenceArray['is_dead'];     
}

//Submit archive requests (multithread version available too)
function requestArchive( $urls, $alreadyArchived ) {
    $getURLs = array();
    $returnArray = array( 'result'=>array(), 'errors'=>array(), 'newlyArchived'=>array() );
    foreach( $urls as $id=>$url ) {
        if( in_array( $url, $alreadyArchived ) ) {
            $returnArray['result'][$id] = null;
            continue;
        }
        $getURLs[$id] = array( 'url' => "http://web.archive.org/save/$url", 'type' => "get" ); 
    }
    if( !empty( $getURLs ) ) $res = multiquery( $getURLs );
    foreach( $res['headers'] as $id=>$item ) {
        if( isset( $item['X-Archive-Wayback-Liveweb-Error'] ) ) {
            $returnArray['errors'][$id] = $item['X-Archive-Wayback-Liveweb-Error'];
            $returnArray['result'][$id] = false;
            $returnArray['newlyArchived'][] = $urls[$id];
        } else $returnArray['result'][$id] = true;
    }
    $res = null;
    unset( $res );
    return $returnArray;
}

//Checks availability of archives
function isArchived( $urls, $alreadyArchived ) {
    $getURLs = array();
    $returnArray = array( 'result'=>array(), 'errors'=>array() );
    foreach( $urls as $id=>$url ) {
        if( in_array( $url, $alreadyArchived ) ) {
            $returnArray['result'][$id] = true;
            continue;
        }
        $url = urlencode( $url );
        $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url&output=json&limit=-2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );
    }
    $res = multiquery( $getURLs );
    foreach( $res['results'] as $id=>$data ) {
        $data = json_decode( $data, true );
        $returnArray['result'][$id] = !empty( $data );
        if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
    }
    $res = null;
    unset( $res );
    return $returnArray;
}

//Fetches archives
function retrieveArchive( $data ) {
    $returnArray = array( 'result'=>array(), 'errors'=>array() );
    foreach( $data as $id=>$item ) {
        $url = $item[0];
        $time = $item[1];
        $url = urlencode( $url ); 
        $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url".( !is_null( $time ) ? "&to=".date( 'YmdHis', $time ) : "" )."&output=json&limit=-2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );
    }
    $res = multiquery( $getURLs );
    $getURLs = array();
    foreach( $res['results'] as $id=>$data2 ) {
        $data2 = json_decode( $data2, true );
        if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error']; 
        if( !empty($data2) ) {
            $returnArray['result'][$id]['archive_url'] = "https://web.archive.org/".$data2[count($data2)-1][1]."/".$data2[count($data2)-1][2];
            $returnArray['result'][$id]['archive_time'] = strtotime( $data2[count($data2)-1][1] );    
        } else {
            $url = $data[$id][0];
            $time = $data[$id][1];
            $getURLs[$id] = array( 'url'=>"http://web.archive.org/cdx/search/cdx?url=$url".( !is_null( $time ) ? "&from=".date( 'YmdHis', $time ) : "" )."&output=json&limit=2&matchType=exact&filter=statuscode:(200|203|206)", 'type'=>"get" );  
        }
    }
    $res = null;
    unset( $res );
    if( !empty( $getURLs ) ) {
        $res = multiquery( $getURLs );
        foreach( $res['results'] as $id=>$data ) {
            $data = json_decode( $data, true );
            if( isset( $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'] ) ) $returnArray['errors'][$id] = $res['headers'][$id]['X-Archive-Wayback-Runtime-Error'];
            if( !empty($data) ) {
                $returnArray['result'][$id]['archive_url'] = "https://web.archive.org/".$data[1][1]."/".$data[1][2];
                $returnArray['result'][$id]['archive_time'] = strtotime( $data[1][1] );    
            } else {
                $returnArray['result'][$id] = false;
            }
        }
        $res = null;
        unset( $res );
    } 
    return $returnArray;
}

//Perform multiple queries simultaneously
function multiquery( $data ) {
    $multicurl_resource = curl_multi_init(); 
    if( $multicurl_resource === false ) {
        return false;
    }
    $curl_instances = array();
    $returnArray = array( 'headers' => array(), 'results' => array(), 'errors' => array() );
    foreach( $data as $id=>$item ) {
        $curl_instances[$id] = curl_init();
        if( $curl_instances[$id] === false ) {
            return false;
        }

        curl_setopt( $curl_instances[$id], CURLOPT_USERAGENT, USERAGENT );
        curl_setopt( $curl_instances[$id], CURLOPT_MAXCONNECTS, 100 );
        curl_setopt( $curl_instances[$id], CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
        curl_setopt( $curl_instances[$id], CURLOPT_MAXREDIRS, 10 );
        curl_setopt( $curl_instances[$id], CURLOPT_ENCODING, 'gzip' );
        curl_setopt( $curl_instances[$id], CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl_instances[$id], CURLOPT_HEADER, 1 );
        curl_setopt( $curl_instances[$id], CURLOPT_TIMEOUT, 100 );
        curl_setopt( $curl_instances[$id], CURLOPT_CONNECTTIMEOUT, 10 );
        if( $item['type'] == "post" ) {
            curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, 0 );
            curl_setopt( $curl_instances[$id], CURLOPT_HTTPGET, 0 );
            curl_setopt( $curl_instances[$id], CURLOPT_POST, 1 );
            curl_setopt( $curl_instances[$id], CURLOPT_POSTFIELDS, $item['data'] );
            curl_setopt( $curl_instances[$id], CURLOPT_URL, $item['url'] );   
        } elseif( $item['type'] == "get" ) {
            curl_setopt( $curl_instances[$id], CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $curl_instances[$id], CURLOPT_HTTPGET, 1 );
            curl_setopt( $curl_instances[$id], CURLOPT_POST, 0 );
            if( isset( $item['data'] ) && !is_null( $item['data'] ) && is_array( $item['data'] ) ) {
                $url .= '?' . http_build_query( $item['data'] );
            }
            curl_setopt( $curl_instances[$id], CURLOPT_URL, $item['url'] );    
        } else {
            return false;
        }
        curl_multi_add_handle( $multicurl_resource, $curl_instances[$id] );
    }
    $active = null;
    do {
        $mrc = curl_multi_exec($multicurl_resource, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($multicurl_resource) == -1) {
            usleep(100);
        }
        do {
            $mrc = curl_multi_exec($multicurl_resource, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        
    }
    
    foreach( $data as $id=>$item ) {
        $returnArray['errors'][$id] = curl_error( $curl_instances[$id] );
        if( ($returnArray['results'][$id] = curl_multi_getcontent( $curl_instances[$id] ) ) !== false ) {
            $header_size = curl_getinfo( $curl_instances[$id], CURLINFO_HEADER_SIZE );
            $returnArray['headers'][$id] = http_parse_headers( substr( $returnArray['results'][$id], 0, $header_size ) );
            $returnArray['results'][$id] = trim( substr( $returnArray['results'][$id], $header_size ) );
        }
        curl_multi_remove_handle( $multicurl_resource, $curl_instances[$id] );
    }
    curl_multi_close( $multicurl_resource );
    return $returnArray;
}

function http_parse_headers( $header ) {
    $header = preg_replace( '/http\/\d\.\d\s\d{3}.*?\n/i', "", $header );
    $header = explode( "\n", $header );
    $returnArray = array();
    foreach( $header as $id=>$item) $header[$id] = explode( ":", $item, 2 );
    foreach( $header as $id=>$item) if( count( $item ) == 2 ) $returnArray[trim($item[0])] = trim($item[1]);
    return $returnArray;
}

function analyzePage( $page, $pageid, $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN ) {
    if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA, serialize( array( 'title' => $page, 'id' => $pageid ) ) );
    if( WORKERS === false ) echo "Analyzing {$page} ({$pageid})...\n";
    $modifiedLinks = array();
    $archiveProblems = array();
    $archived = 0;
    $rescued = 0;
    $tagged = 0;
    $analyzed = 0;
    $newlyArchived = array();
    $timestamp = date( "Y-m-d\TH:i:s\Z" ); 
    $history = array(); 
    $oldtext = $newtext = getPageText( $page );
    if( preg_match( '/\{\{((U|u)se)?\s?(D|d)(MY|my)\s?(dates)?/i', $oldtext ) ) $df = true;
    else $df = false;
    if( $LINK_SCAN == 0 ) $links = getExternalLinks( $oldtext, $history, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS, $CITATION_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $page );
    else $links = getReferences( $oldtext, $history, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS, $CITATION_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD, $page );
    $analyzed = $links['count'];
    unset( $links['count'] );
    
    //Check if we already have the link in the database
    /*if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
        
    }   */
                                   
    //Process the links
    $checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = array();
    foreach( $links as $id=>$link ) {
        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $ARCHIVE_ALIVE == 1 ) $toArchive[$id] = $link[$link['link_type']]['url'];
    }
    $checkResponse = isArchived( $toArchive, $alreadyArchived );
    $checkResponse = $checkResponse['result'];
    $toArchive = array();
    foreach( $links as $id=>$link ) {
        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
            $toArchive[$id] = $link[$link['link_type']]['url']; 
        }
        if( $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
            if( $link[$link['link_type']]['link_type'] != "x" ) {
                if( ($link[$link['link_type']]['tagged_dead'] === true && ( $TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $DEAD_ONLY == 2 ) || ( $DEAD_ONLY == 0 ) ) {
                    $toFetch[$id] = array( $link[$link['link_type']]['url'], ( $ARCHIVE_BY_ACCESSDATE == 1 ? ( $link[$link['link_type']]['access_time'] != "x" ? $link[$link['link_type']]['access_time'] : null ) : null ) );  
                }
            }
        }
    }
    $errors = array();
    if( MULTITHREAD === true && DEBUG === false ) {
        if( !empty( $toArchive ) ) if( ($archiveResponse = AsyncFunctionCall::call( "requestArchive", array( $toArchive, $alreadyArchived ) )) === false ) {
            echo "Threaded function call, requestArchive, failed, running non-threaded function call...\n";
        }
        if( !empty( $toFetch ) ) if( ($fetchResponse = AsyncFunctionCall::call( "retrieveArchive", array( $toFetch ) )) === false ) {
            echo "Threaded function call, retrieveArchive, failed, running non-threaded function call...\n";
        }
        if( !empty( $toArchive ) && $archiveResponse !== false ) {
            $archiveResponse->join();
            $archiveResponse = $archiveResponse->result;
            $errors = $archiveResponse['errors'];
            $newlyArchived = $archiveResponse['newlyArchived'];
            $archiveResponse = $archiveResponse['result'];
        } elseif( $archiveResponse === false ) {
            $archiveResponse = requestArchive( $toArchive, $alreadyArchived );
            $errors = $archiveResponse['errors'];
            $newlyArchived = $archiveResponse['newlyArchived'];
            $archiveResponse['result'];
        }
        if( !empty( $toFetch ) && $fetchResponse !== false ) {
            $fetchResponse->join();
            $fetchResponse = $fetchResponse->result; 
            $fetchResponse = $fetchResponse['result'];
        } elseif( $fetchResponse === false ) {
            $fetchResponse = retrieveArchive( $toFetch );
            $fetchResponse['result'];
        }                
    } else {
        if( !empty( $toArchive ) ) {
            $archiveResponse = requestArchive( $toArchive, $alreadyArchived );
            $errors = $archiveResponse['errors'];
            $newlyArchived = $archiveResponse['newlyArchived'];
            $archiveResponse = $archiveResponse['result'];
        }
        if( !empty( $toFetch ) ) {
            $fetchResponse = retrieveArchive( $toFetch );
            $fetchResponse = $fetchResponse['result'];
        } 
    }
    foreach( $links as $id=>$link ) {
        if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
        if( ( $link[$link['link_type']]['is_dead'] !== true && $link[$link['link_type']]['tagged_dead'] !== true ) && $ARCHIVE_ALIVE == 1 && !$checkResponse[$id] ) {
            if( $archiveResponse[$id] === true ) {
                $archived++;  
            } elseif( $archiveResponse[$id] === false ) {
                $archiveProblems[$id] = $link[$link['link_type']]['url'];
                $failedToArchive[] = $link[$link['link_type']]['url'];
                $allerrors[] = $errors[$id];
            }
        }
        if( $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
            if( $link[$link['link_type']]['link_type'] != "x" ) {
                if( ($link[$link['link_type']]['tagged_dead'] === true && ( $TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $DEAD_ONLY == 2 ) || ( $DEAD_ONLY == 0 ) ) {
                    if( ($temp = $fetchResponse[$id]) !== false ) {
                        $rescued++;
                        $modifiedLinks[$id]['type'] = "addarchive";
                        $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
                        $modifiedLinks[$id]['newarchive'] = $temp['archive_url'];
                        if( $link[$link['link_type']]['has_archive'] === true ) {
                            $modifiedLinks[$id]['type'] = "modifyarchive";
                            $modifiedLinks[$id]['oldarchive'] = $link[$link['link_type']]['archive_url'];
                        }
                        $link['newdata']['has_archive'] = true;
                        $link['newdata']['archive_url'] = $temp['archive_url'];
                        $link['newdata']['archive_time'] = $temp['archive_time'];
                        if( $link[$link['link_type']]['link_type'] == "link" ) {
                            $link['newdata']['archive_type'] = "template";
                            $link['newdata']['tagged_dead'] = false;
                            $link['newdata']['archive_template']['name'] = "wayback";
                            if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) unset( $link[$link['link_type']]['archive_template']['parameters'] );
                            $link['newdata']['archive_template']['parameters']['url'] = $link[$link['link_type']]['url'];
                            $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
                            if( $df === true ) $link['newdata']['archive_template']['parameters']['df'] = "y";
                        } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
                            $link['newdata']['archive_type'] = "parameter";
                            if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) $link['newdata']['tagged_dead'] = true;
                            else $link['newdata']['tagged_dead'] = false;
                            $link['newdata']['tag_type'] = "parameter";
                            if( $link[$link['link_type']]['tagged_dead'] === true || $link[$link['link_type']]['is_dead'] === true ) {
                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
                                else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
                            }
                            else {
                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "no";
                                else $link['newdata']['link_template']['parameters']['dead-url'] = "no";
                            }
                            if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-url'] ) ) $link['newdata']['link_template']['parameters']['archiveurl'] = $temp['archive_url'];
                            else $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
                            if( $df === true ) {
                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'j F Y', $temp['archive_time'] );
                                else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
                            } else {
                                if( !isset( $link[$link['link_type']]['link_template']['parameters']['archive-date'] ) ) $link['newdata']['link_template']['parameters']['archivedate'] = date( 'F j, Y', $temp['archive_time'] );
                                else $link['newdata']['link_template']['parameters']['archive-date'] = date( 'F j, Y', $temp['archive_time'] );    
                            }
                            
                            if( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) {
                                $link['newdata']['link_template']['parameters']['url'] = $link[$link['link_type']]['url'];
                                $modifiedLinks[$id]['type'] = "fix";
                            }
                        }
                        unset( $temp );
                    } else {
                        if( $link[$link['link_type']]['tagged_dead'] !== true ) $link['newdata']['tagged_dead'] = true;
                        else continue;
                        $tagged++;
                        $modifiedLinks[$id]['type'] = "tagged";
                        $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
                        if( $link[$link['link_type']]['link_type'] == "link" ) {
                            $link['newdata']['tag_type'] = "template";
                            $link['newdata']['tag_template']['name'] = "dead link";
                            $link['newdata']['tag_template']['parameters']['date'] = date( 'F Y' );
                            $link['newdata']['tag_template']['parameters']['bot'] = "Cyberbot II";    
                        } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
                            $link['newdata']['tag_type'] = "parameter";
                            if( !isset( $link[$link['link_type']]['link_template']['parameters']['dead-url'] ) ) $link['newdata']['link_template']['parameters']['deadurl'] = "yes";
                            else $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
                        }
                    }    
                } elseif( $link[$link['link_type']]['tagged_dead'] === true && $link[$link['link_type']]['is_dead'] == false ) {
                    $rescued++;
                    $modifiedLinks[$id]['type'] = "tagremoved";
                    $modifiedLinks[$id]['link'] = $link[$link['link_type']]['url'];
                    $link['newdata']['tagged_dead'] = false;
                }   
            }
        }
        if( isset( $link['newdata'] ) && newIsNew( $link ) ) {
            $link['newstring'] = generateString( $link, $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS );
            $newtext = str_replace( $link['string'], $link['newstring'], $newtext );
        }
    }
    $archiveResponse = $checkResponse = $fetchResponse = null;
    unset( $archiveResponse, $checkResponse, $fetchResponse );
    if( WORKERS === true ) {
        echo "Analyzed $page ($pageid)\n";
    }
    echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: ".(memory_get_usage( true )/1048576)." MB; Max System Memory Used: ".(memory_get_peak_usage(true)/1048576)." MB\n\n";
    if( !empty( $archiveProblems ) && $NOTIFY_ERROR_ON_TALK == 1 ) {
        $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $TALK_ERROR_MESSAGE ) )."~~~~";
        $out = "";
        foreach( $archiveProblems as $id=>$problem ) {
            $out .= "* $problem with error {$errors[$id]}\n";
        } 
        $body = str_replace( "{problematiclinks}", $out, str_replace( "\\n", "\n", $TALK_ERROR_MESSAGE ) )."~~~~";
        edit( "Talk:$page", $body, "Notifications of sources failing to archive. #IABot", $timestamp, true, "new", $TALK_ERROR_MESSAGE_HEADER );  
    }
    $pageModified = false;
    if( $oldtext != $newtext ) {
        $pageModified = true;
        $revid = edit( $page, $newtext, "Rescuing $rescued sources, flagging $tagged as dead, and archiving $archived sources. #IABot", false, $timestamp );
        if( $NOTIFY_ON_TALK == 1 && $revid !== false ) {
            $out = "";
            foreach( $modifiedLinks as $link ) {
                $out .= "*";
                switch( $link['type'] ) {
                    case "addarchive":
                    $out .= "Added archive {$link['newarchive']} to ";
                    break;
                    case "modifyarchive":
                    $out .= "Replaced archive link {$link['oldarchive']} with {$link['newarchive']} on ";
                    break;
                    case "fix":
                    $out .= "Attempted to fix sourcing for ";
                    break;
                    case "tagged":
                    $out .= "Added {{tlx|dead link}} tag to ";
                    break;
                    case "tagremoved":
                    $out .= "Removed dead tag from ";
                    break;
                    default:
                    $out .= "Modified source for ";
                    break;
                }
                $out .= $link['link'];
                $out .= "\n";     
            }
            $header = str_replace( "{namespacepage}", $page, str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, $TALK_MESSAGE_HEADER ) ) ) );
            $body = str_replace( "{diff}", "https://en.wikipedia.org/w/index.php?diff=prev&oldid=$revid", str_replace( "{modifiedlinks}", $out, str_replace( "{namespacepage}", $page, str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, str_replace( "\\n", "\n", $TALK_MESSAGE ) ) ) ) ) ) )."~~~~";
            edit( "Talk:$page", $body, "Notification of altered sources needing review #IABot", false, $timestamp, true, "new", $header );
        }
    }
    $oldtext = $newtext = $history = null;
    unset( $oldtext, $newtext, $history );
    $returnArray = array( 'archiveProblems'=>$archiveProblems, 'errors'=>$errors, 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'newlyArchived'=>$newlyArchived, 'pagemodified'=>$pageModified );
    return $returnArray;
}

//Multithread safe API functions
function botLogon( $user, $pass ) {
    echo "Logging on as $user...";
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    $post = array( 'action'=>'login', 'lgname'=>$user, 'lgpassword'=>$pass, 'format'=>'php' );
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 0 );
    curl_setopt( $curl, CURLOPT_POST, 1 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
    curl_setopt( $curl, CURLOPT_URL, API ); 
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    $data = unserialize( $data );
    $post['lgtoken'] = $data['login']['token'];
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    $data = unserialize( $data );
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    if( $data['login']['result'] == "Success" ) {
        echo "Success!!\n\n";
        return true;
    }
    else {
        echo "Failed!!\n\n";
        return false;
    }
}

function isLoggedOn( $user ) {
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    $get = "action=query&meta=userinfo&format=php";
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    $data = unserialize( $data );
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    if( $data['query']['userinfo']['name'] == $user ) return true;
    else return false;
}

function getPageText( $page ) {
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    $get = "action=raw&title=".urlencode($page);
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    $api = str_replace( "api.php", "index.php", API );
    curl_setopt( $curl, CURLOPT_URL, $api."?$get" ); 
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    return $data;   
}

function getTaggedArticles( $titles, $limit, $resume ) {
    $returnArray = array();
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );    
    while( true ) {
        $get = "action=query&prop=transcludedin&format=php&tinamespace=0&tilimit=".($limit-count($returnArray)).( empty($resume) ? "" : "&ticontinue=$resume" )."&rawcontinue=&titles=".urlencode($titles);
        curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
        $data = curl_exec( $curl ); 
        $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
        $data = trim( substr( $data, $header_size ) );
        $data = unserialize( $data ); 
         foreach( $data['query']['pages'] as $template ) {
            if( isset( $template['transcludedin'] ) ) $returnArray = array_merge( $returnArray, $template['transcludedin'] );
        } 
        if( isset( $data['query-continue']['transcludedin']['ticontinue'] ) ) $resume = $data['query-continue']['transcludedin']['ticontinue'];
        else {
            $resume = "";
            break;
        } 
        if( $limit <= count( $returnArray ) ) break; 
    }
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    return array( $returnArray, $resume);
}

function getPageHistory( $page ) {
    $returnArray = array();
    $resume = "";
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );    
    while( true ) {
        $get = "action=query&prop=revisions&format=php&rvdir=newer&rvprop=ids&rvlimit=max".( empty($resume) ? "" : "&rvcontinue=$resume" )."&rawcontinue=&titles=".urlencode($page);
        curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
        $data = curl_exec( $curl ); 
        $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
        $data2 = trim( substr( $data, $header_size ) );
        $data = null;
        $data = unserialize( $data2 );
        $data2 = null; 
        if( isset( $data['query']['pages'] ) ) foreach( $data['query']['pages'] as $template ) {
            if( isset( $template['revisions'] ) ) $returnArray = array_merge( $returnArray, $template['revisions'] );
        } 
        if( isset( $data['query-continue']['revisions']['rvcontinue'] ) ) $resume = $data['query-continue']['revisions']['rvcontinue'];
        else {
            $resume = "";
            break;
        } 
        $data = null;
        unset($data);
    }
    curl_close( $curl );
    $data = $data2 = $curl = null;
    unset( $curl, $data, $data2 );
    return $returnArray;    
}

function isEnabled( $runpage ) {
    $text = getPageText( $runpage );
    if( $text == "enable" ) return true;
    else return false;
}

function nobots( $text, $username = "", $taskname = "" ) {
    if( strpos( $text, "{{nobots}}" ) !== false ) return true;
    if( strpos( $text, "{{bots}}" ) !== false ) return false;

    if( preg_match( '/\{\{bots\s*\|\s*allow\s*=\s*(.*?)\s*\}\}/i', $text, $allow ) ) {
        if( $allow[1] == "all" ) return false;
        if( $allow[1] == "none" ) return true;
        $allow = array_map( 'trim', explode( ',', $allow[1] ) );
        if( !is_null( $username ) && in_array( trim( $username ), $allow ) ) {
            return false;
        }
        return true;
    }

    if( preg_match( '/\{\{(no)?bots\s*\|\s*deny\s*=\s*(.*?)\s*\}\}/i', $text, $deny ) ) {
        if( $deny[2] == "all" ) return true;
        if( $deny[2] == "none" ) return false;
        $allow = array_map( 'trim', explode( ',', $deny[2] ) );
        if( ( !is_null( $username ) && in_array( trim( $username ), $allow ) ) || ( !is_null( $taskname ) && in_array( trim( $taskname ), $allow ) ) ) {
            return true;
        }
        return false;
    }
    return false;   
}

function edit( $page, $text, $summary, $minor = false, $timestamp = false, $bot = true, $section = false, $title = "" ) {
    if( !isEnabled( RUNPAGE ) ) {
        echo "ERROR: BOT IS DISABLED!!\n\n";
        return false; 
    }
    if( NOBOTS === true && nobots( $text, USERNAME, TASKNAME ) ) {
        echo "ERROR: RESTRICTED BY NOBOTS!!\n\n";
        return false;
    }
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    $post = array( 'action'=>'edit', 'title'=>$page, 'text'=>$text, 'format'=>'php', 'summary'=>$summary, 'md5'=>md5($text), 'nocreate'=>'yes' );
    if( $minor ) {
        $post['minor'] = 'yes';
    } else {
        $post['notminor'] = 'yes';
    }
    if( $timestamp ) {
        $post['basetimestamp'] = $timestamp;
        $post['starttimestamp'] = $timestamp;
    }
    if( $bot ) {
        $post['bot'] = 'yes';
    }
    if( $section == "new" ) {
        $post['section'] = "new";
        $post['sectiontitle'] = $title;
        $post['redirect'] = "yes";
    } elseif( $section == "append" ) {
        $post['appendtext'] = $text;
        $post['redirect'] = "yes";
    }
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    $get = "action=query&meta=tokens&format=php";    
    curl_setopt( $curl, CURLOPT_URL, API."?$get" );
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    $data = unserialize( $data );
    $post['token'] = $data['query']['tokens']['csrftoken'];
    curl_setopt( $curl, CURLOPT_HTTPGET, 0 );
    curl_setopt( $curl, CURLOPT_POST, 1 );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
    curl_setopt( $curl, CURLOPT_URL, API ); 
    $data = curl_exec( $curl ); 
    $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
    $data = trim( substr( $data, $header_size ) );
    $data = unserialize( $data );
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    if( isset( $data['edit'] ) && $data['edit']['result'] == "Success" && !isset( $data['edit']['nochange']) ) {
        return $data['edit']['newrevid'];
    } else {
        return false;
    }
}

//SQL related stuff
function createELTable() {
    if( ( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) !== false ) {
        if( mysqli_query( "CREATE TABLE `externallinks_".WIKIPEDIA."` (
                              `pageid` INT(12) NOT NULL,
                              `url` VARCHAR(767) NOT NULL,
                              `archive_url` BLOB NULL,
                              `has_archive` INT(1) UNSIGNED NOT NULL DEFAULT '0',
                              `live_state` INT(1) UNSIGNED NOT NULL DEFAULT 4,
                              `archivable` INT(1) UNSIGNED NOT NULL DEFAULT 1,
                              `archived` INT(1) UNSIGNED NOT NULL DEFAULT 0,
                              `archive_failure` BLOB NULL,
                              `access_date` INT(10) UNSIGNED NOT NULL,
                              `archive_date` INT(10) UNSIGNED NULL,
                              `reviewed` INT(1) UNSIGNED NOT NULL DEFAULT 0,
                              UNIQUE INDEX `url_UNIQUE` (`url` ASC),
                              PRIMARY KEY (`url`, `pageid`, `live_state`, `archived`, `reviewed`, `archivable`),
                              INDEX `LIVE_STATE` (`live_state` ASC),
                              INDEX `REVIEWED` (`reviewed` ASC),
                              INDEX `ARCHIVED` (`archived` ASC, `archivable` ASC),
                              INDEX `URL` (`url` ASC),
                              INDEX `PAGEID` (`pageid` ASC));
                              ") ) echo "Successfully created an external links table for ".WIKIPEDIA."\n\n";
        else {
            echo "Failed to create an externallinks table to use.\nThis table is vital for the operation of this bot. Exiting...";
            exit( 10000 );
        }  
    } else {
        echo "Failed to establish a database connection.  Exiting...";
        exit( 20000 );
    }
    mysqli_close( $db );
    unset( $db );
}

function getAllArticles( $limit, $resume ) {
    $returnArray = array();
    $curl = curl_init();
    curl_setopt($curl,CURLOPT_COOKIEFILE, COOKIE);
    curl_setopt($curl,CURLOPT_COOKIEJAR, COOKIE);
    curl_setopt( $curl, CURLOPT_USERAGENT, USERAGENT );
    curl_setopt( $curl, CURLOPT_MAXCONNECTS, 100 );
    curl_setopt( $curl, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USED );
    curl_setopt( $curl, CURLOPT_MAXREDIRS, 10 );
    curl_setopt( $curl, CURLOPT_ENCODING, 'gzip' );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $curl, CURLOPT_HEADER, 1 );
    curl_setopt( $curl, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 0 );
    curl_setopt( $curl, CURLOPT_HTTPGET, 1 );
    curl_setopt( $curl, CURLOPT_POST, 0 );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );    
    while( true ) {
        $get = "action=query&list=allpages&format=php&apnamespace=0&apfilterredir=nonredirects&aplimit=".($limit-count($returnArray))."&rawcontinue=&apcontinue=$resume";
        curl_setopt( $curl, CURLOPT_URL, API."?$get" ); 
        $data = curl_exec( $curl ); 
        $header_size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
        $data = trim( substr( $data, $header_size ) );
        $data = unserialize( $data ); 
        $returnArray = array_merge( $returnArray, $data['query']['allpages'] );
        if( isset( $data['query-continue']['allpages']['apcontinue'] ) ) $resume = $data['query-continue']['allpages']['apcontinue'];
        else {
            $resume = "";
            break;
        } 
        if( $limit <= count( $returnArray ) ) break; 
    }
    curl_close( $curl );
    $curl = null;
    unset( $curl );
    return array( $returnArray, $resume);
}

//Multithread engine

//This thread class allows for asyncronous function calls.  This is useful for the functions that consume time and can run in the background.
//Caution must be excercised to ensure that the functions are thread safe.
class AsyncFunctionCall extends Thread {
    
    protected $method;
    protected $params;
    public $result;
    
    public function __construct( $method, $params ) {
        $this->method = $method;
        $this->params = $params;
        $this->result = null; 
    }
    
    public function run() {
        if (($this->result=call_user_func_array($this->method, $this->params))) {
            return true;
        } else return false;
    }
    
    public static function call($method, $params){
        $thread = new AsyncFunctionCall($method, $params);
        if($thread->start()){
            return $thread;
        } else {
            echo "Unable to initiate background function $method!\n";
            return false;
        }
    }
}

// Analyze multiple pages simultaneously and edit them.
class ThreadedBot extends Collectable {
    
    protected $id, $page, $pageid, $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN;
    
    public $result;
    
    public function __construct($page, $pageid, $alreadyArchived, $ARCHIVE_ALIVE, $TAG_OVERRIDE, $ARCHIVE_BY_ACCESSDATE, $TOUCH_ARCHIVE, $DEAD_ONLY, $NOTIFY_ERROR_ON_TALK, $NOTIFY_ON_TALK, $TALK_MESSAGE_HEADER, $TALK_MESSAGE, $TALK_ERROR_MESSAGE_HEADER, $TALK_ERROR_MESSAGE, $DEADLINK_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $ARCHIVE_TAGS, $VERIFY_DEAD, $LINK_SCAN, $i) {
        $this->page = $page;
        $this->pageid = $pageid;
        $this->alreadyArchived = $alreadyArchived;
        $this->ARCHIVE_ALIVE = $ARCHIVE_ALIVE;
        $this->TAG_OVERRIDE = $TAG_OVERRIDE;
        $this->ARCHIVE_BY_ACCESSDATE = $ARCHIVE_BY_ACCESSDATE;
        $this->TOUCH_ARCHIVE = $TOUCH_ARCHIVE;
        $this->DEAD_ONLY = $DEAD_ONLY;
        $this->NOTIFY_ERROR_ON_TALK = $NOTIFY_ERROR_ON_TALK;
        $this->NOTIFY_ON_TALK = $NOTIFY_ON_TALK;
        $this->TALK_MESSAGE_HEADER = $TALK_MESSAGE_HEADER;
        $this->TALK_MESSAGE = $TALK_MESSAGE;
        $this->TALK_ERROR_MESSAGE_HEADER = $TALK_ERROR_MESSAGE_HEADER;
        $this->TALK_ERROR_MESSAGE = $TALK_ERROR_MESSAGE;
        $this->DEADLINK_TAGS = $DEADLINK_TAGS;
        $this->CITATION_TAGS = $CITATION_TAGS;
        $this->IGNORE_TAGS = $IGNORE_TAGS;
        $this->ARCHIVE_TAGS = $ARCHIVE_TAGS;
        $this->VERIFY_DEAD = $VERIFY_DEAD;
        $this->LINK_SCAN = $LINK_SCAN; 
        $this->id = $i;   
    }
    
    public function run() {
        $this->result = analyzePage( $this->page, $this->pageid, $this->alreadyArchived, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN);
        if( !file_exists( IAPROGRESS.WIKIPEDIA."workers/" ) ) mkdir( IAPROGRESS.WIKIPEDIA."workers", 0777 );
        file_put_contents( IAPROGRESS.WIKIPEDIA."workers/worker{$this->id}", serialize( $this->result ) );
        $this->setGarbage();
        $this->page = null;
        $this->pageid = null;
        $this->alreadyArchived = null;
        $this->ARCHIVE_ALIVE = null;
        $this->TAG_OVERRIDE = null;
        $this->ARCHIVE_BY_ACCESSDATE = null;
        $this->TOUCH_ARCHIVE = null;
        $this->DEAD_ONLY = null;
        $this->NOTIFY_ERROR_ON_TALK = null;
        $this->NOTIFY_ON_TALK = null;
        $this->TALK_MESSAGE_HEADER = null;
        $this->TALK_MESSAGE = null;
        $this->TALK_ERROR_MESSAGE_HEADER = null;
        $this->TALK_ERROR_MESSAGE = null;
        $this->DEADLINK_TAGS = null;
        $this->CITATION_TAGS = null;
        $this->IGNORE_TAGS = null;
        $this->ARCHIVE_TAGS = null;
        $this->VERIFY_DEAD = null;
        $this->LINK_SCAN = null;
        unset( $this->page, $this->pageid, $this->alreadyArchived, $this->ARCHIVE_ALIVE, $this->TAG_OVERRIDE, $this->ARCHIVE_BY_ACCESSDATE, $this->TOUCH_ARCHIVE, $this->DEAD_ONLY, $this->NOTIFY_ERROR_ON_TALK, $this->NOTIFY_ON_TALK, $this->TALK_MESSAGE_HEADER, $this->TALK_MESSAGE, $this->TALK_ERROR_MESSAGE_HEADER, $this->TALK_ERROR_MESSAGE, $this->DEADLINK_TAGS, $this->CITATION_TAGS, $this->IGNORE_TAGS, $this->ARCHIVE_TAGS, $this->VERIFY_DEAD, $this->LINK_SCAN );
    }
}
?>
