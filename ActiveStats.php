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
    $db = new Database( 'wikidatawiki.labsdb', $toolserver_username, $toolserver_password, 'wikidatawiki_p' );
    $admins = $site->allusers( null, array('sysop'), null, false, array(), -1 );
    $bureaucrats = $site->allusers( null, array('bureaucrat'), null, false, array(), -1 );
    $oversighters = $site->allusers( null, array('oversight'), null, false, array(), -1 );
    
    $cratright = array();
    $adminright = array();
    
    $outarray = array();
    $timestamp = date( 'Ymdhis', strtotime('-6 months', time()) );
    $out = "{| class=\"wikitable\"\n|-\n! Administrators !! Bureaucrats !! Oversighters\n";
    //get the information
    foreach( $admins as $user ) {
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $uid = $db->select(
            'user',
            'user_id',
            array(
                'user_name' => $user['name']
            )
        );
        $uid = $uid[0]['user_id'];
        $logquery['letitle'] = "User:".$user['name'];
        $result = $site->listHandler( $logquery );
        $since = "";
        foreach( $result as $entry ) {
            if( $entry['rights']['old'] == str_replace( 'sysop', '', $entry['rights']['old'] ) ) 
                if( $entry['rights']['new'] != str_replace( 'sysop', '', $entry['rights']['new'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        if( $since == "" ) {
            $logquery['letitle'] .= "@wikidatawiki";
            $result = $sitemeta->listHandler( $logquery );
            foreach( $result as $entry ) {
                if( $entry['rights']['old'] == str_replace( 'sysop', '', $entry['rights']['old'] ) )
                    if( $entry['rights']['new'] != str_replace( 'sysop', '', $entry['rights']['new'] ) ) $since = strtotime($entry['timestamp']);
                if( $since != "" ) break;
            }
        }
        $temp = 0;
        foreach( $bureaucrats as $user2 ) if( $user['userid'] == $user2['userid'] )  {
             $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect') AND `log_timestamp` > '$timestamp'";
             $logquery['leend'] = date( 'Ymdhis', strtotime('-6 months', time()) );
             unset( $logquery['letitle'] );
             $logquery['leuser'] = $user['name'];
             $result = $site->listHandler( $logquery );
             $cratright[$user['name']] = 0;
             $adminright[$user['name']] = 0;
             foreach( $result as $entry ) {
                 $entry['rights']['old'] = explode( ',', $entry['rights']['old'] );
                 $entry['rights']['new'] = explode( ',', $entry['rights']['new'] );
                 $diff = array_diff( $entry['rights']['old'], $entry['rights']['new'] );
                 if( in_array_recursive( 'flood', $diff ) || in_array_recursive( 'bot', $diff ) || in_array_recursive( 'translationadmin', $diff ) ) $cratright[$user['name']]++;
                 else {
                     $diff = array_diff( $entry['rights']['new'], $entry['rights']['old'] );
                     if( in_array_recursive( 'flood', $diff ) || in_array_recursive( 'bot', $diff ) || in_array_recursive( 'translationadmin', $diff ) || in_array_recursive( 'sysop', $diff ) || in_array_recursive( 'bureaucrat', $diff ) ) $cratright[$user['name']]++;
                     else $adminright[$user['name']]++;
                 }
                  
             }
             unset( $logquery['leend'] );
             unset( $logquery['leuser'] );
             break;
        } else $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect') AND `log_timestamp` > '$timestamp'";
        if( !isset( $adminright[$user['name']] ) ) $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp'";
        else $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp'";
        $res = $db->query( $sql );
        $res = $res[0]['count'];
        $res2 = $db->query( $sql2 );
        $res2 = $res2[0]['count'];
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res2+=count($result)+$temp;
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['admin'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since );
    }
    foreach( $bureaucrats as $user ) {
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $temp = 0;
        $uid = $db->select(
            'user',
            'user_id',
            array(
                'user_name' => $user['name']
            )
        );
        $uid = $uid[0]['user_id'];
        $logquery['letitle'] = "User:".$user['name'];
        $result = $site->listHandler( $logquery );
        $since = "";
        $temp = 0;
        foreach( $result as $entry ) {
            if( $entry['rights']['old'] == str_replace( 'bureaucrat', '', $entry['rights']['old'] ) ) 
                if( $entry['rights']['new'] != str_replace( 'bureaucrat', '', $entry['rights']['new'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        if( $since == "" ) {
            $logquery['letitle'] .= "@wikidatawiki";
            $result = $sitemeta->listHandler( $logquery );
            foreach( $result as $entry ) {
                if( $entry['rights']['old'] == str_replace( 'bureaucrat', '', $entry['rights']['old'] ) ) 
                    if( $entry['rights']['new'] != str_replace( 'bureaucrat', '', $entry['rights']['new'] ) ) $since = strtotime($entry['timestamp']);
                if( $since != "" ) break;
            }
        }
        $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('renameuser') AND `log_timestamp` > '$timestamp'";
        $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp'";
        $res = $db->query( $sql );
        $res = $res[0]['count'];
        $res2 = $db->query( $sql2 );
        $res2 = $res2[0]['count'];
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        $res2+=$temp;
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['crat'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since );   
    } 
    foreach( $oversighters as $user ) {
        if( $user['name'] != str_ireplace( '(WM','',$user['name']) ) continue;
        $uid = $db->select(
            'user',
            'user_id',
            array(
                'user_name' => $user['name']
            )
        );
        $temp = 0;
        $uid = $uid[0]['user_id'];
        $logquery['letitle'] = "User:".$user['name']."@wikidatawiki";
        $result = $sitemeta->listHandler( $logquery );
        foreach( $result as $entry ) {
            if( $entry['rights']['old'] == str_replace( 'oversight', '', $entry['rights']['old'] ) ) 
                if( $entry['rights']['new'] != str_replace( 'oversight', '', $entry['rights']['new'] ) ) $since = strtotime($entry['timestamp']);
            if( $since != "" ) break;
        }
        $sql2 = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('suppress') AND `log_timestamp` > '$timestamp'";
        if( !isset( $adminright[$user['name']] ) ) $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'rights', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp'";
        else $sql = "SELECT COUNT(*) AS count FROM logging_userindex WHERE `log_user` = '$uid' AND `log_type` in ('delete', 'block', 'protect', 'renameuser', 'suppress') AND `log_timestamp` > '$timestamp'";        $res2 = $db->query( $sql2 );
        $res2 = $res2[0]['count'];
        $res = $db->query( $sql );
        $res = $res[0]['count'];
        $editquery['ucuser'] = $user['name'];
        $result = $site->listHandler( $editquery );
        if( isset( $cratright[$user['name']] ) ) $temp+=$cratright[$user['name']];
        if( isset( $adminright[$user['name']] ) ) $temp+=$adminright[$user['name']];
        $res+=count($result)+$temp;
        $outarray['oversight'][] = array( 'name'=>$user['name'], 'count'=>$res, 'count1'=>$res2, 'since'=>$since );   
    }
    //process the information into an output.
    $count = 0;
    if( isset( $outarray['admin'] ) ) if( count( $outarray['admin'] ) > $count ) $count = count( $outarray['admin'] ); 
    if( isset( $outarray['crat'] ) ) if( count( $outarray['crat'] ) > $count ) $count = count( $outarray['crat'] );
    if( isset( $outarray['oversight'] ) ) if( count( $outarray['oversight'] ) > $count ) $count = count( $outarray['oversight'] );
    
    echo $count;
    
    for( $i=0; $i<$count; $i++ ) {
        $out .= "|-\n";
        if( isset( $outarray['admin'] ) && isset( $outarray['admin'][$i] ) ) {
            $data = "[[User:".$outarray['admin'][$i]['name']."|".$outarray['admin'][$i]['name']."]]<br>Admin since: ".date( 'j F Y', $outarray['admin'][$i]['since'] )."<br>Admin actions: ".$outarray['admin'][$i]['count1']."<br>Total actions: ".$outarray['admin'][$i]['count']."\n";
            if( strtotime('-6 months', time()) > $outarray['admin'][$i]['since'] ) {
                if( $outarray['admin'][$i]['count'] < 10 ) $out .= $inactivecell.$data;
                if( 10 <= $outarray['admin'][$i]['count'] && $outarray['admin'][$i]['count'] <= 17 ) $out .= $lowactivecell.$data;
                if( $outarray['admin'][$i]['count'] > 17 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";
        if( isset( $outarray['crat'] ) && isset( $outarray['crat'][$i] ) ) {
            $data = "[[User:".$outarray['crat'][$i]['name']."|".$outarray['crat'][$i]['name']."]]<br>Bureaucrat since: ".date( 'j F Y', $outarray['crat'][$i]['since'] )."<br>Bureaucrat actions: ".$outarray['crat'][$i]['count1']."<br>Total actions: ".$outarray['crat'][$i]['count']."\n";
            if( strtotime('-6 months', time()) > $outarray['crat'][$i]['since'] ) {
                if( $outarray['crat'][$i]['count'] < 10 ) $out .= $inactivecell.$data;
                if( 10 <= $outarray['crat'][$i]['count'] && $outarray['crat'][$i]['count'] <= 17 ) $out .= $lowactivecell.$data;
                if( $outarray['crat'][$i]['count'] > 17 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";
        if( isset( $outarray['oversight'] ) && isset( $outarray['oversight'][$i] ) ) {
            $data = "[[User:".$outarray['oversight'][$i]['name']."|".$outarray['oversight'][$i]['name']."]]<br>Oversighter since: ".date( 'j F Y', $outarray['oversight'][$i]['since'] )."<br>Oversight actions: ".$outarray['oversight'][$i]['count1']."<br>Total: ".$outarray['oversight'][$i]['count']."\n";
            if( strtotime('-6 months', time()) > $outarray['oversight'][$i]['since'] ) {
                if( $outarray['oversight'][$i]['count'] < 10 ) $out .= $inactivecell.$data;
                if( 10 <= $outarray['oversight'][$i]['count'] && $outarray['oversight'][$i]['count'] <= 17 ) $out .= $lowactivecell.$data;
                if( $outarray['oversight'][$i]['count'] > 17 ) $out .= $activecell.$data;
            } else {
                $out .= $activecell.$data;
            }
        } else $out.="|\n";   
    }
    $out.="|}";
    
    $site->initPage( "User:Cyberpower678/ActiveStats" )->edit( $out, "Updating active admins, crats, and oversighters." );
		break;
}