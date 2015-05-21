<?php

ini_set('memory_limit','5G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( '/data/project/cyberbot/Peachy/Init.php' );
require_once('/data/project/cyberbot/database.inc');

$site = Peachy::newWiki( "wikidata" );
$site->set_runpage( null );
$sitemeta = Peachy::newWiki( "meta" );

$active = '#ccffcc';
$lowactive = '#ffcccc';
$inactive = '#CCCCCC';

$activecell = "| style=\"background:$active\" | ";
$lowactivecell = "| style=\"background:$lowactive\" | ";
$inactivecell = "! style=\"background:$inactive\" | ";

$logquery = array( 'list'=>'logevents', '_code'=>'le', '_limit'=>-1, 'leprop'=>'timestamp|details', 'letype'=>'rights', 'ledir'=>'older' );
$editquery = array( 'list'=>'usercontribs', 'ucstart'=>date( 'Ymdhis', strtotime('-6 months', time()) ), 'ucdir'=>'newer', 'ucnamespace'=>'8', '_limit'=>-1, '_code'=>'uc' );

while(true) {
    echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
    $db = mysqli_connect( 'wikidatawiki.labsdb', $toolserver_username, $toolserver_password, 'wikidatawiki_p' );
    $admins = $site->allusers( null, array('sysop'), null, false, array(), -1 );
    $bureaucrats = $site->allusers( null, array('bureaucrat'), null, false, array(), -1 );
    $oversighters = $site->allusers( null, array('oversight'), null, false, array(), -1 );
    
    $cratright = array();
    $adminright = array();
    
    $outarray = array();
    $timestamp = date( 'Ymdhis', strtotime('-6 months', time()) );
    $out = "<big>This is a table of Administrators, Bureaucrats, and Oversighters.<br>This table provides information such as when the person became that type of user, how many actions they have performed with in the last timeframe, and if they are inactive as defined by the rules on Wikidata.<br>This table has the following parameters:<br>Activity timeframe: '''6 months'''<br>Inactivity: ''Defined as less than '''5''' admin actions''</big>\n\n{| class=\"wikitable\"\n|-\n| style=\"background:#ccffcc\" | Active\n| style=\"background:#ffcccc\" | Slipping into Inactivity\n! style=\"background:#CCCCCC\" | Inactive\n|}\n{| class=\"wikitable\"\n|-\n! Administrators !! Bureaucrats !! Oversighters\n";
    //get the information
    
    echo "Checking administrators...\n\n";
    foreach( $admins as $user ) {
        echo "Fetching administrator \"{$user['name']}\"...\n";
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $tmp = mysqli_query( $db, "SELECT user_id FROM user WHERE `user_name` = '{$user['name']}';" );
        $uid = mysqli_fetch_assoc( $tmp );
        $uid = $uid['user_id'];
        mysqli_free_result( $tmp );
        $logquery['letitle'] = "User:".$user['name'];
        $result = $site->listHandler( $logquery );
        $since = "";
        foreach( $result as $entry ) {
            if( $entry['params']['oldgroups'] == str_replace( 'sysop', '', $entry['params']['oldgroups'] ) ) 
                if( $entry['params']['newgroups'] != str_replace( 'sysop', '', $entry['params']['newgroups'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        if( $since == "" ) {
            $logquery['letitle'] .= "@wikidatawiki";
            $result = $sitemeta->listHandler( $logquery );
            foreach( $result as $entry ) {
                if( $entry['params']['oldgroups'] == str_replace( 'sysop', '', $entry['params']['oldgroups'] ) )
                    if( $entry['params']['newgroups'] != str_replace( 'sysop', '', $entry['params']['newgroups'] ) ) $since = strtotime($entry['timestamp']);
                if( $since != "" ) break;
            }
        }
        if( $since == "" ) {
            $since = "Unknown";
        }
        $temp = 0;
        foreach( $bureaucrats as $user2 ) if( $user['userid'] == $user2['userid'] )  {
             $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect') AND `log_timestamp` > '$timestamp';";
             $logquery['leend'] = date( 'Ymdhis', strtotime('-6 months', time()) );
             unset( $logquery['letitle'] );
             $logquery['leuser'] = $user['name'];
             $result = $site->listHandler( $logquery );
             $cratright[$user['name']] = 0;
             $adminright[$user['name']] = 0;
             foreach( $result as $entry ) {
                 $diff = array_diff( $entry['params']['oldgroups'], $entry['params']['newgroups'] );
                 if( in_array( 'flood', $diff ) || in_array( 'bot', $diff ) || in_array( 'translationadmin', $diff ) ) $cratright[$user['name']]++;
                 else {
                     $diff = array_diff( $entry['params']['newgroups'], $entry['params']['oldgroups'] );
                     if( in_array( 'flood', $diff ) || in_array( 'bot', $diff ) || in_array( 'translationadmin', $diff ) || in_array( 'sysop', $diff ) || in_array( 'bureaucrat', $diff ) ) $cratright[$user['name']]++;
                     else $adminright[$user['name']]++;
                 }
                  
             }
             unset( $logquery['leend'] );
             unset( $logquery['leuser'] );
             break;
        } else $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect') AND `log_timestamp` > '$timestamp';";
        if( !isset( $adminright[$user['name']] ) ) $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp';";
        else $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp';";
        $tmp = mysqli_query( $db, $sql );
        $res = mysqli_fetch_assoc( $tmp );
        $res = $res['count'];
        mysqli_free_result( $tmp );
        $tmp = mysqli_query( $db, $sql2 );
        $res2 = mysqli_fetch_assoc( $tmp );
        $res2 = $res2['count'];
        mysqli_free_result( $tmp );
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res2+=count($result)+$temp;
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['admin'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since );
        echo "Administrator since $since; Administrator actions: $res2\n\n";
    }
    echo "Checking bureaucrats...\n\n";
    foreach( $bureaucrats as $user ) {
        echo "Fetching bureaucrat \"{$user['name']}\"...\n";
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $temp = 0;
        $tmp = mysqli_query( $db, "SELECT user_id FROM user WHERE `user_name` = '{$user['name']}';" );
        $uid = mysqli_fetch_assoc( $tmp );
        $uid = $uid['user_id'];
        mysqli_free_result( $tmp );
        $logquery['letitle'] = "User:".$user['name'];
        $result = $site->listHandler( $logquery );
        $since = "";
        $temp = 0;
        foreach( $result as $entry ) {
            if( $entry['params']['oldgroups'] == str_replace( 'bureaucrat', '', $entry['params']['oldgroups'] ) ) 
                if( $entry['params']['newgroups'] != str_replace( 'bureaucrat', '', $entry['params']['newgroups'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        if( $since == "" ) {
            $logquery['letitle'] .= "@wikidatawiki";
            $result = $sitemeta->listHandler( $logquery );
            foreach( $result as $entry ) {
                if( $entry['params']['oldgroups'] == str_replace( 'bureaucrat', '', $entry['params']['oldgroups'] ) ) 
                    if( $entry['params']['newgroups'] != str_replace( 'bureaucrat', '', $entry['params']['newgroups'] ) ) $since = strtotime($entry['timestamp']);
                if( $since != "" ) break;
            }
        }
        if( $since == "" ) {
            $since = "Unknown";
        }
        $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('renameuser') AND `log_timestamp` > '$timestamp';";
        $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp';";
        $tmp = mysqli_query( $db, $sql );
        $res = mysqli_fetch_assoc( $tmp );
        $res = $res['count'];
        mysqli_free_result( $tmp );
        $tmp = mysqli_query( $db, $sql2 );
        $res2 = mysqli_fetch_assoc( $tmp );
        $res2 = $res2['count'];
        mysqli_free_result( $tmp );
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        $res2+=$temp;
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['crat'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since ); 
        echo "Bureaucrat since $since; Bureaucrat actions: $res2\n\n";  
    }
    echo "Checking oversighters...\n\n"; 
    foreach( $oversighters as $user ) {
        echo "Fetching oversighter \"{$user['name']}\"...\n";
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $tmp = mysqli_query( $db, "SELECT user_id FROM user WHERE `user_name` = '{$user['name']}';" );
        $uid = mysqli_fetch_assoc( $tmp );
        $uid = $uid['user_id'];
        mysqli_free_result( $tmp );
        $temp = 0;
        $logquery['letitle'] = "User:".$user['name']."@wikidatawiki";
        $result = $sitemeta->listHandler( $logquery );
        foreach( $result as $entry ) {
            if( $entry['params']['oldgroups'] == str_replace( 'oversight', '', $entry['params']['oldgroups'] ) ) 
                if( $entry['params']['newgroups'] != str_replace( 'oversight', '', $entry['params']['newgroups'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        if( $since == "" ) {
            $since = "Unknown";
        }
        $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('suppress') AND `log_timestamp` > '$timestamp';";
        if( !isset( $adminright[$user['name']] ) ) $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp';";
        else $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp';";
        $tmp = mysqli_query( $db, $sql );
        $res = mysqli_fetch_assoc( $tmp );
        $res = $res['count'];
        mysqli_free_result( $tmp );
        $tmp = mysqli_query( $db, $sql2 );
        $res2 = mysqli_fetch_assoc( $tmp );
        $res2 = $res2['count'];
        mysqli_free_result( $tmp );
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['oversight'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since ); 
        echo "Oversighter since $since; Oversight actions: $res2\n\n";  
    }
    //process the information into an output.
    echo "Generating output...";
    $count = 0;
    if( isset( $outarray['admin'] ) ) if( count( $outarray['admin'] ) > $count ) $count = count( $outarray['admin'] ); 
    if( isset( $outarray['crat'] ) ) if( count( $outarray['crat'] ) > $count ) $count = count( $outarray['crat'] );
    if( isset( $outarray['oversight'] ) ) if( count( $outarray['oversight'] ) > $count ) $count = count( $outarray['oversight'] );
    
    for( $i=0; $i<$count; $i++ ) {
        $out .= "|-\n";
        if( isset( $outarray['admin'] ) && isset( $outarray['admin'][$i] ) ) {
            $data = "[[User:".$outarray['admin'][$i]['name']."|".$outarray['admin'][$i]['name']."]]<br>Admin since: ".( $outarray['admin'][$i]['since'] != "Unknown" ? date( 'j F Y', $outarray['admin'][$i]['since'] ) : "Unknown" )."<br>Admin actions: ".$outarray['admin'][$i]['count1']."<br>Total actions: ".$outarray['admin'][$i]['count']."\n";
            if( $outarray['admin'][$i]['since'] == "Unknown" || strtotime('-6 months', time()) > $outarray['admin'][$i]['since'] ) {
                if( $outarray['admin'][$i]['count'] < 5 ) $out .= $inactivecell.$data;
                if( 5 <= $outarray['admin'][$i]['count'] && $outarray['admin'][$i]['count'] <= 12 ) $out .= $lowactivecell.$data;
                if( $outarray['admin'][$i]['count'] > 12 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";
        if( isset( $outarray['crat'] ) && isset( $outarray['crat'][$i] ) ) {
            $data = "[[User:".$outarray['crat'][$i]['name']."|".$outarray['crat'][$i]['name']."]]<br>Bureaucrat since: ".( $outarray['crat'][$i]['since'] != "Unknown" ? date( 'j F Y', $outarray['crat'][$i]['since'] ) : "Unknown" )."<br>Bureaucrat actions: ".$outarray['crat'][$i]['count1']."<br>Total actions: ".$outarray['crat'][$i]['count']."\n";
            if( $outarray['crat'][$i]['since'] == "Unknown" || strtotime('-6 months', time()) > $outarray['crat'][$i]['since'] ) {
                if( $outarray['crat'][$i]['count'] < 5 ) $out .= $inactivecell.$data;
                if( 5 <= $outarray['crat'][$i]['count'] && $outarray['crat'][$i]['count'] <= 12 ) $out .= $lowactivecell.$data;
                if( $outarray['crat'][$i]['count'] > 12 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";
        if( isset( $outarray['oversight'] ) && isset( $outarray['oversight'][$i] ) ) {
            $data = "[[User:".$outarray['oversight'][$i]['name']."|".$outarray['oversight'][$i]['name']."]]<br>Oversighter since: ".( $outarray['oversight'][$i]['since'] != "Unknown" ? date( 'j F Y', $outarray['oversight'][$i]['since'] ) : "Unknown" )."<br>Oversight actions: Blocked Information<br>Total: ".$outarray['oversight'][$i]['count']."\n";
            if( $outarray['oversight'][$i]['since'] == "Unknown" || strtotime('-6 months', time()) > $outarray['oversight'][$i]['since'] ) {
                if( $outarray['oversight'][$i]['count'] < 5 ) $out .= $inactivecell.$data;
                if( 5 <= $outarray['oversight'][$i]['count'] && $outarray['oversight'][$i]['count'] <= 12 ) $out .= $lowactivecell.$data;
                if( $outarray['oversight'][$i]['count'] > 12 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";   
    }
    $out.="|}";
    echo "Done\n\n";
    
    echo "Posting table...\n";
    $site->initPage( "User:Cyberpower678/ActiveStats" )->edit( $out, "Updating active admins, crats, and oversighters." );
	mysqli_close( $db );
    echo "Done\n\nProgram complete!\n";
    break;
}