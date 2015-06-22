<?php
/*
This software has been created by Cyberpower678
This software analyzes dead-links and attempts to reliably find the proper archived page for it.
This software uses the MW API
This software uses the Wayback CDX Server API and the Pagination API (not the standard)
*/

ini_set('memory_limit','5G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( '/data/project/cyberbot/Peachy/Init.php' );

$site = Peachy::newWiki( "cyberbotii" );
$site->set_runpage( "User:Cyberbot II/Run/Dead-links" );

$pgDisplayPechoNormal = false;
$pgVerbose = array( 2,3,4 );
$LINK_SCAN = 0;
$DEAD_ONLY = 2;
$TAG_OVERRIDE = 1;
$PAGE_SCAN = 0;
$ARCHIVE_BY_ACCESSDATE = 1;
$TOUCH_ARCHIVE = 0;
$NOTIFY_ON_TALK = 1;
$TALK_MESSAGE_HEADER = "Links modified on main page";
$TALK_MESSAGE = "Please review the links modified on the main page...";
$DEADLINK_TAGS = array( "{{dead-link}}" );
$CITATION_TAGS = array( "{{cite web}}" );
$ARCHIVE_TAGS = array( "{{wayback}}" );
$IGNORE_TAGS = array( "{{cbignore}}" );
$DEAD_RULES = array();
$VERIFY_DEAD = 1;
$ARCHIVE_ALIVE = 1;

while( true ) {
    echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
    $runstart = time();
    $runtime = 0;
    $pagesAnalyzed = 0;
    $linksAnalyzed = 0;
    $linksFixed = 0;
    $linksTagged = 0;
    $pagesModified = 0;
    $linksArchived = 0;
    $return = array();
    $iteration = 0;
    $config = $site->initPage( "User:Cyberbot II/Dead-links" )->get_text( true );
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
    preg_match( '/\n\|TALK_MESSAGE_HEADER\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE_HEADER = $param1[1];
    preg_match( '/\n\|TALK_MESSAGE\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $TALK_MESSAGE = $param1[1];
    preg_match( '/\n\|DEADLINK_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEADLINK_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|CITATION_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $CITATION_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|ARCHIVE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|IGNORE_TAGS\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $IGNORE_TAGS = explode( ';', $param1[1] );
    preg_match( '/\n\|DEAD_RULES\s*=\s*\"(.*?)\"/i', $config, $param1 );
    if( isset( $param1[1] ) ) $DEAD_RULES = explode( ';', $param1[1] );
    preg_match( '/\n\|VERIFY_DEAD\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $VERIFY_DEAD = $param1[1];
    preg_match( '/\n\|ARCHIVE_ALIVE\s*=\s*(\d+)/i', $config, $param1 );
    if( isset( $param1[1] ) ) $ARCHIVE_ALIVE = $param1[1];
    foreach( $DEAD_RULES as $tid => $rule ) $DEAD_RULES[$tid] = explode( ":", $rule );
    foreach( $DEADLINK_TAGS as $tag ) $DEADLINK_TAGS[] = substr_replace( $tag, strtoupper( substr( $tag, 2, 1 ) ), 2, 1 );
    foreach( $CITATION_TAGS as $tag ) $CITATION_TAGS[] = substr_replace( $tag, strtoupper( substr( $tag, 2, 1 ) ), 2, 1 );
    foreach( $ARCHIVE_TAGS as $tag ) $ARCHIVE_TAGS[] = substr_replace( $tag, strtoupper( substr( $tag, 2, 1 ) ), 2, 1 );
    foreach( $IGNORE_TAGS as $tag ) $IGNORE_TAGS[] = substr_replace( $tag, strtoupper( substr( $tag, 2, 1 ) ), 2, 1 );
    foreach( $DEADLINK_TAGS as $tid=>$tag ) $DEADLINK_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $CITATION_TAGS as $tid=>$tag ) $CITATION_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $ARCHIVE_TAGS as $tid=>$tag ) $ARCHIVE_TAGS[$tid] = preg_quote( $tag, '/' );
    foreach( $IGNORE_TAGS as $tid=>$tag ) $IGNORE_TAGS[$tid] = preg_quote( $tag, '/' );
    
    //Get started with the run
    do {
        $iteration++;
        if( $PAGE_SCAN == 0 ) {
            echo "Fetching article pages...\n";
            $tArray = array( '_code' => "ap", 'apnamespace' => 0, '_limit' => 1500000, 'list' => "allpages" );
            $pages = $site->listHandler( $tArray, $return );
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n";
        } elseif( $PAGE_SCAN == 1 ) {
            echo "Fetching articles with links marked as dead...\n";
            $tArray = array( '_code' => "ti", 'tinamespace' => 0, '_limit' => 1500000, 'prop' => "transcludedin", '_lhtitle' => "transcludedin", 'titles' => str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $DEADLINK_TAGS ) ) ) ) );
            $pages = $site->listHandler( $tArray );
            echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n"; 
        }
        foreach( $pages as $tid => $tpage ) {
            //if( $tpage['pageid'] == 2104651 ) {
            //    sleep( 1 );
            //}
            echo "Analyzing {$tpage['title']} ({$tpage['pageid']})...\n";
            $pagesAnalyzed++;
            $archived = 0;
            $rescued = 0;
            $tagged = 0;
            $page = $site->initPage( $tpage['title'], $tpage['pageid'], false );
            $oldtext = $newtext = $page->get_text( true );
            if( $LINK_SCAN == 0 ) $links = getExternalLinks( $page );
            else $links = getReferences( $page );
            //continue;
            
            //Process the links
            foreach( $links as $link ) {
                if( isset( $link[$link['link_type']]['ignore'] ) && $link[$link['link_type']]['ignore'] === true ) continue;
                if( $link[$link['link_type']]['is_dead'] === false && $ARCHIVE_ALIVE == 1 && !isArchived( $link[$link['link_type']]['url'] ) ) {
                    requestArchive( $link[$link['link_type']]['url'] );
                    $archived++;
                }
                if( $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false || ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] == "invalid" ) ) {
                    if( $link[$link['link_type']]['link_type'] != "x" ) {
                        if( ($link[$link['link_type']]['tagged_dead'] === true && ( $TAG_OVERRIDE == 1 || $link[$link['link_type']]['is_dead'] === true ) && ( ( $link[$link['link_type']]['has_archive'] === true && $link[$link['link_type']]['archive_type'] != "parameter" ) || $TOUCH_ARCHIVE == 1 || $link[$link['link_type']]['has_archive'] === false ) ) || ( $link[$link['link_type']]['is_dead'] === true && $DEAD_ONLY == 2 ) || ( $DEAD_ONLY == 0 ) ) {
                            if( ($temp = retrieveArchive( $link[$link['link_type']]['url'], ( $ARCHIVE_BY_ACCESSDATE == 1 ? ( $link[$link['link_type']]['access_time'] != "x" ? $link[$link['link_type']]['access_time'] : null ) : null ) )) !== false ) {
                                $rescued++;
                                $linksFixed++;
                                $link['newdata']['has_archive'] = true;
                                $link['newdata']['archive_url'] = $temp['archive_url'];
                                $link['newdata']['archive_time'] = $temp['archive_time'];
                                if( $link[$link['link_type']]['link_type'] == "link" ) {
                                    $link['newdata']['archive_type'] = "template";
                                    $link['newdata']['tagged_dead'] = false;
                                    $link['newdata']['archive_template']['name'] = "wayback";
                                    $link['newdata']['archive_template']['parameters']['url'] = $link[$link['link_type']]['url'];
                                    $link['newdata']['archive_template']['parameters']['date'] = date( 'YmdHis', $temp['archive_time'] );
                                } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
                                    $link['newdata']['archive_type'] = "parameter";
                                    $link['newdata']['tagged_dead'] = true;
                                    $link['newdata']['tag_type'] = "parameter";
                                    $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
                                    $link['newdata']['link_template']['parameters']['archive-url'] = $temp['archive_url'];
                                    $link['newdata']['link_template']['parameters']['archive-date'] = date( 'j F Y', $temp['archive_time'] );
                                }
                                unset( $temp );
                            } else {
                                $tagged++;
                                $linksTagged++;
                                $link['newdata']['tagged_dead'] = true;
                                if( $link[$link['link_type']]['link_type'] == "link" ) {
                                    $link['newdata']['tag_type'] = "template";
                                    $link['newdata']['tag_template']['name'] = "dead link";
                                    $link['newdata']['tag_template']['parameters']['date'] = date( 'F Y' );
                                    $link['newdata']['tag_template']['parameters']['bot'] = "Cyberbot II";    
                                } elseif( $link[$link['link_type']]['link_type'] == "template" ) {
                                    $link['newdata']['tag_type'] = "parameter";
                                    $link['newdata']['link_template']['parameters']['dead-url'] = "yes";
                                }
                            }    
                        } elseif( $link[$link['link_type']]['tagged_dead'] === true && $link[$link['link_type']]['is_dead'] == false ) {
                            $rescued++;
                            $link['newdata']['tagged_dead'] = false;
                        }   
                    }
                }
                if( isset( $link['newdata'] ) && newIsNew( $link ) ) {
                    $link['newstring'] = generateString( $link );
                    $newtext = str_replace( $link['string'], $link['newstring'], $newtext );
                }
            }
            echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived\n\n";
            if( $oldtext != $newtext ) {
                $pagesModified++;
                $page->edit( $newtext, "Rescuing $rescued sources, flagging $tagged as dead, and archiving $archived sources." );
                if( $NOTIFY_ON_TALK == 1 ) {
                    $talk = $site->initPage( null, $page->get_talkID() );
                    $header = str_replace( "{namespacepage}", $tpage['title'], str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, $TALK_MESSAGE_HEADER ) ) ) );
                    $body = str_replace( "{namespacepage}", $tpage['title'], str_replace( "{linksmodified}", $tagged+$rescued, str_replace( "{linksrescued}", $rescued, str_replace( "{linkstagged}", $tagged, str_replace( "\\n", "\n", $TALK_MESSAGE ) ) ) ) );
                    $talk->newsection( $body, $header, "Notification of altered sources needing review" );
                }
            }
        }
        unset( $pages );
    } while( !empty( $return ) ); 
    $runend = time();
    $runtime = $runend-$runstart;
    generateLogReport();                                             
}

//Create run log information
function generateLogReport() {
    global $site, $NOTIFY_ON_TALK, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $runtime, $pagesAnalyzed, $pagesModified;
    $page = $site->initPage( "User:Cyberbot II/Dead-Links Log" );
    $log = $page->get_text();
    $entry = "|-\n|";
    $entry .= date( 'H:i, j F Y (\U\T\C)', $runstart );
    $entry .= "||";
    $entry .= date( 'H:i, j F Y (\U\T\C)', $runend );
    $entry .= "||";
    $entry .= date( 'z:H:i:s', $runend );
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
    $entry .= "||";
    $entry .= "[https://en.wikipedia.org/w/index.php?title=Special%3AContributions&target=Cyberbot_II&offset=".date( 'YmdHis', $runend )."&limit=".( $NOTIFY_ON_TALK == 1 ? 2*$pagesModified : $pagesModified )."]";
    $entry .= "\n";
    $log = str_replace( "|}", $entry, $log );
    $page->edit( $log, "Updating run log with run statistics" );
    return;
}
//Construct string
function generateString( $link ) {
    $out = "";
    $mArray = mergeNewData( $link );
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
    if( $mArray['has_archive'] === true ) {
        if( $mArray['archive_type'] == "template" ) {
            $out .= "{{".$mArray['archive_template']['name'];
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
function getReferences( &$page ) {
    global $DEADLINK_TAGS, $linksAnalyzed, $ARCHIVE_TAGS, $IGNORE_TAGS;
    $tArray = array_merge( $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS );
    $returnArray = array();
    preg_match_all( '/<ref(.*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})?(?<\/ref>)?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?}})?/i', $page->get_text( true ), $params );
    foreach( $params[0] as $tid=>$fullmatch ) {
        $linksAnalyzed++;
        if( !isset( $returnArray[$tid] ) ) {
            $returnArray[$tid]['link_type'] = "reference";
            //Fetch parsed reference content
            $returnArray[$tid]['reference'] = getLinkDetails( $params[2][$tid], $params[3][$tid].$params[5][$tid] ); 
            $returnArray[$tid]['string'] = $params[0][$tid];
        }
        //Fetch reference parameters
        if( !empty( $params[1][$tid] ) ) $returnArray[$tid]['reference']['parameters'] = getReferenceParameters( $params[1][$tid] );
        if( empty( $params[2][$tid] ) && empty( $params[3][$tid] ) ) {
            unset( $returnArray[$tid] );
            continue;
        }
    }
    return $returnArray;   
}

//Gather and parse all external links including references
function getExternalLinks( &$page ) {
    global $DEADLINK_TAGS, $linksAnalyzed, $ARCHIVE_TAGS, $IGNORE_TAGS, $CITATION_TAGS;
    $tArray = array_merge( $DEADLINK_TAGS, $ARCHIVE_TAGS, $IGNORE_TAGS );
    $returnArray = array();
    preg_match_all( '/(<ref(.*?)>(.*?)(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})?<\/ref>(('.str_replace( "\}\}", "", implode( '|', $tArray ) ).').*?}})?|\[{1}((?:https?:)?\/\/.*?)\s?.*?\]{1}.*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*\}\})?|(('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).').*?\}\}).*?(('.str_replace( "\}\}", "", implode( '|', $tArray ) ) .').*?\}\})?)/i', $page->get_text( true ), $params );
    foreach( $params[0] as $tid=>$fullmatch ) {
        $linksAnalyzed++;
        if( !empty( $params[2][$tid] ) || !empty( $params[2][$tid] ) || !empty( $params[3][$tid] ) ) {
            $returnArray[$tid]['link_type'] = "reference";
            //Fetch parsed reference content
            $returnArray[$tid]['reference'] = getLinkDetails( $params[3][$tid], $params[4][$tid].$params[6][$tid] ); 
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
            $returnArray[$tid]['externallink'] = getLinkDetails( $params[0][$tid], $params[9][$tid] ); 
            $returnArray[$tid]['string'] = $params[0][$tid];
        } elseif( !empty( $params[11][$tid] ) || !empty( $params[13][$tid] ) ) {
            $returnArray[$tid]['link_type'] = "template";
            //Fetch parsed reference content
            $returnArray[$tid]['template'] = getLinkDetails( $params[11][$tid], $params[13][$tid] );
            $returnArray[$tid]['name'] = str_replace( "{{", "", $params[12][$tid] );
            $returnArray[$tid]['string'] = $params[0][$tid];
        }
    }
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
function getLinkDetails( $linkString, $remainder ) {
    global $ARCHIVE_TAGS, $CITATION_TAGS, $IGNORE_TAGS, $DEADLINK_TAGS, $TOUCH_ARCHIVE, $VERIFY_DEAD;
    $returnArray = array();
    $returnArray['link_string'] = $linkString;
    $returnArray['remainder'] = $remainder;
    if( preg_match( '/('.str_replace( "\}\}", "", implode( '|', $IGNORE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $remainder, $params ) || preg_match( '/('.str_replace( "\}\}", "", implode( '|', $IGNORE_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        return array( 'ignore' => true );
    }
    if( strpos( $linkString, "web.archive.org" ) !== false && !preg_match( '/('.str_replace( "\}\}", "", implode( '|', $CITATION_TAGS ) ).')\s*?\|(.*?(\{\{.*?\}\}.*?)*?)\}\}/i', $linkString, $params ) ) {
        $returnArray['has_archive'] = true;
        $returnArray['is_archive'] = true;
        $returnArray['archive_type'] = "link";
        $returnArray['link_type'] = "x";
        preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14}|\*)\/(\S*)\s/i', $linkString, $returnArray['url'] );
        if( $returnArray['url'][1] != "*" ) $returnArray['archive_time'] = strtotime( $returnArray['url'][1] );
        else $returnArray['archive_time'] = "x";
        $returnArray['archive_url'] = trim( $returnArray['url'][0] );
        $returnArray['url'] = $returnArray['url'][2];
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
        preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)/i', $returnArray['link_template']['parameters']['url'], $params2 );
        $returnArray['archive_time'] = strtotime( $params2[1] );
        $returnArray['archive_url'] = trim( $params2[0] );
        $returnArray['url'] = $params2[2];
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
                if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[1] );
                    $returnArray['archive_url'] = $params3[0];
                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                } else {
                    $returnArray['archive_type'] = "invalid";
                } 
            } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[1] );
                    $returnArray['archive_url'] = $params3[0];
                } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                    $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                } else {
                    $returnArray['archive_type'] = "invalid";
                }
            } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                    $returnArray['archive_type'] = "invalid";
                    $returnArray['archive_time'] = strtotime( $params3[1] );
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
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        } 
                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
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
        if( isset( $returnArray['link_template']['parameters']['accessdate'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['accessdate'] );
        elseif( isset( $returnArray['link_template']['parameters']['access-date'] ) ) $returnArray['access_time'] = strtotime( $returnArray['link_template']['parameters']['access-date'] );
        else $returnArray['access_time'] = "x";
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
    } elseif( preg_match( '/((?:https?:)?\/\/.*?)\s/i', $linkString, $params ) ) {
        $returnArray['url'] = $params[1];
        $returnArray['link_type'] = "link"; 
        $returnArray['access_time'] = "x";
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
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters'][1], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters'][1]}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        } 
                    } elseif( isset( $returnArray['archive_template']['parameters']['site'] ) ) {
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['site'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
                            $returnArray['archive_url'] = $params3[0];
                        } elseif( !isset( $returnArray['archive_template']['parameters']['date'] ) ) {
                            $returnArray['archive_url'] = $returnArray['archive_url'] = "https://web.archive.org/web/*/{$returnArray['archive_template']['parameters']['site']}";
                        } else {
                            $returnArray['archive_type'] = "invalid";
                        }
                    } elseif( isset( $returnArray['archive_template']['parameters']['url'] ) ) {
                        if( preg_match( '/https?\:\/\/web\.archive\.org\/web\/(\d{14})\/(\S*)\s?/i', $returnArray['archive_template']['parameters']['url'], $params3 ) ) {
                            $returnArray['archive_type'] = "invalid";
                            $returnArray['archive_time'] = strtotime( $params3[1] );
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

//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
function getTemplateParameters( $templateString ) {
    $templateString = trim( $templateString );
    $returnArray = array();
    $tArray = array();
    while( true ) {
        $pipepos = strpos( $templateString, "|" );
        while( ( strpos( $templateString, "{{" ) < $pipepos && strpos( $templateString, "}}" ) > $pipepos ) || ( strpos( $templateString, "[[" ) < $pipepos && strpos( $templateString, "]]" ) > $pipepos ) ) $pipepos = strpos( $templateString, "|", $pipepos + 1 );
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
    global $DEAD_RULES, $site;
    $referenceArray['is_dead'] = false;
    if( isset( $referenceArray['url'] ) ) {
        $page = $site->get_http()->get( $referenceArray['url'] );
        $code = $site->get_http()->get_HTTP_code();
        foreach( $DEAD_RULES as $rule ) if( strpos( $referenceArray['url'], $rule['url'] ) ) {
            if( isset( $rule['expiry'] ) ) {
                if( $referenceArray['access_time'] != "x" && strtotime( $referenceArray['access_time'] + $rule['expiry'] ) < time() ) $referenceArray['is_dead'] = true; 
            }
            if( isset( $rule['verifytitle'] ) && isset( $referenceArray['link_template'] ) && isset( $referenceArray['link_template']['parameters']['title'] ) ) {
                if( strpos( strtolower( $page ), strtolower( $referenceArray['link_template']['parameters']['title'] ) ) === false ) $referenceArray['is_dead'] = true;
            }
            if( isset( $rule['deadcode'] ) ){
                if( $code == $rule['deadcode'] ) $referenceArray['is_dead'] = true;
            }
        }
        if( $code != 200 ) $referenceArray['is_dead'] = true;    
    } else {
        $referenceArray['is_dead'] = false;
    }
    return $referenceArray['is_dead'];     
}

//Submit an archive request
function requestArchive( $url ) {
    global $site, $linksArchived;
    $site->get_http()->get( "http://web.archive.org/save/$url" );
    $code = $site->get_http()->get_HTTP_code();
    $linksArchived++;
    if( $code == 200 ) return true;
    else return false;
}

function isArchived( $url ) {
    global $site;
    $data = $site->get_http()->get( "http://archive.org/wayback/available?url=$url" );
    $data = json_decode( $data, true );
    return !empty( $data['archived_snapshots'] );
}

function retrieveArchive( $url, $time = null ) {
    global $site;
    $returnArray = array();
    $data = $site->get_http()->get( "http://archive.org/wayback/available?url=$url".( !is_null( $time ) ? "&timestamp=".date( 'YmdHis', $time ) : "" ) );
    $data = json_decode( $data, true );
    if( empty( $data['archived_snapshots'] ) ) return false;
    else {
        $returnArray['archive_url'] = $data['archived_snapshots']['closest']['url'];
        $returnArray['archive_time'] = strtotime( $data['archived_snapshots']['closest']['timestamp'] );  
    }
    return $returnArray;
}
?>
