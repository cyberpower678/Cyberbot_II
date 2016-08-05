<?php
/*
	Copyright (c) 2016, Maximilian Doerr
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

set_include_path( get_include_path().PATH_SEPARATOR.dirname(__FILE__).DIRECTORY_SEPARATOR );
date_default_timezone_set( "UTC" );
ini_set('memory_limit','1G');
echo "----------STARTING UP SCRIPT----------\nStart Timestamp: ".date('r')."\n\n";
require_once( 'deadlink.config.inc.php' );

if( !API::botLogon() ) exit( 1 );

DB::checkDB();

$config = [
	'link_scan' => 0,
	'dead_only' => 2,
	'tag_override' => 1,
	'page_scan' => 0,
	'archive_by_accessdate' => 1,
	'touch_archive' => 0,
	'notify_on_talk' => 1,
	'notify_on_talk_only' => 0,
	'notify_error_on_talk' => 1,
	'talk_message_header' => "Links modified on main page",
	'talk_message' => "Please review the links modified on the main page...",
	'talk_error_message' => "There were problems archiving a few links on the page.",
	'talk_error_message_header' => "Notification of problematic links",
	'deadlink_tags' => array( "{{dead-link}}" ),
	'citation_tags' => array( "{{cite web}}" ),
	'wayback_tags' => array( "{{wayback}}" ),
	'archiveis_tags' => array( "{{archiveis}}" ),
	'memento_tags' => array( "{{memento}}" ),
	'webcite_tags' => array( "{{webcite}}" ),
	'ignore_tags' => array( "{{cbignore}}" ),
	'paywall_tags' => array( "{{paywall}}" ),
	'archive_tags' => array(),
	'ic_tags' => array(),
	'verify_dead' => 1,
	'archive_alive'=> 1,
	'mladdarchive' => "{link}->{newarchive}",
	'mlmodifyarchive' => "{link}->{newarchive}<--{oldarchive}",
	'mlfix' => "{link}",
	'mltagged' => "{link}",
	'mltagremoved' => "{link}",
	'mldefault' => "{link}",
	'plerror' => "{problem}: {error}",
	'maineditsummary' => "Fixing dead links",
	'errortalkeditsummary' => "Errors encountered during archiving",
	'talkeditsummary' => "Links have been altered"
];

$runpagecount = 0;
$lastpage = false;
if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID ) ) $lastpage = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID ) );
if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."c" ) ) {
	$tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."c" ) );
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
if( $lastpage === false || empty( $lastpage ) || is_null( $lastpage ) ) $lastpage = false;

while( true ) {
	echo "----------RUN TIMESTAMP: ".date('r')."----------\n\n";
	$runstart = time();
	if( !file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats" ) ) {
		$pagesAnalyzed = 0;
		$linksAnalyzed = 0;
		$linksFixed = 0;
		$linksTagged = 0;
		$pagesModified = 0;
		$linksArchived = 0;
	} else {
		$tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats" ) );
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
	$iteration = 0;
	//Get started with the run
	do {
		$config_text = API::getPageText( "User:".USERNAME."/Dead-links" );

		preg_match_all( '/\*(.*?)\s*=\s*(\d+)/i', $config_text, $toggleParams );
		preg_match_all( '/\*(.*?)\s*=\s*\"(.*?)\"/i', $config_text, $stringParams );

		foreach( $toggleParams[1] as $id=>$variable ) {
			$variable = strtolower( $variable );
			if( isset( $config[$variable] ) ) $config[$variable] = $toggleParams[2][$id];
		}
		foreach( $stringParams[1] as $id=>$variable ) {
			$variable = strtolower( $variable );
			if( isset( $config[$variable] ) ) $config[$variable] = $stringParams[2][$id];
			if( strpos( $variable, "tags" ) !== false ) {
				if( !empty( $config[$variable] ) ) $config[$variable] = explode( ';', $config[$variable] );
					else $config[$variable] = array();
			}
		}

		if( isset( $overrideConfig ) && is_array( $overrideConfig ) ) {
			foreach( $overrideConfig as $variable=>$value ) {
				if( isset( $config[variable] ) ) $config[variable] = $value;
			}
		}

		API::escapeTags( $config );
		$config['archive_tags'] = array_merge(
			$config['wayback_tags'], $config['archiveis_tags'], $config['memento_tags'], $config['webcite_tags']
		);

		$iteration++;
		if( $iteration !== 1 ) {
			$lastpage = false;
			$pages = false;
		}
		//fetch the pages we want to analyze and edit.  This fetching process is done in batches to preserve memory.
		if( DEBUG === true && $debugStyle == "test" ) {	 //This fetches a specific page for debugging purposes
			echo "Fetching test pages...\n";
			$pages = array( $debugPage );
		} elseif( $config['page_scan'] == 0 ) {					   //This fetches all the articles, or a batch of them.
			echo "Fetching";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
			echo " article pages...\n";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
				 $pages = API::getAllArticles( 5000, $return );
				 $return = $pages[1];
				 $pages = $pages[0];
			} elseif( $iteration !== 1 || $pages === false ) {
				$pages = API::getAllArticles( 5000, $return );
				$return = $pages[1];
				$pages = $pages[0];
				file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );
			} else {
				if( $lastpage !== false ) {
					foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
					$pages = array_slice( $pages, $tcount + 1 );
				}
			}
			echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n";
		} elseif( $config['page_scan'] == 1 ) {					   //This fetches only articles with a deadlink tag in it, or a batch of them
			echo "Fetching";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) echo " ".$debugStyle;
			echo " articles with links marked as dead...\n";
			if( DEBUG === true && is_int( $debugStyle ) && LIMITEDRUN === false ) {
				$pages = API::getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $config['deadlink_tags'] ) ) ) ), $debugStyle, $return );
				$return = $pages[1];
				$pages = $pages[0];
			} elseif( $iteration !== 1 || $pages === false ) {
				$pages = API::getTaggedArticles( str_replace( "{{", "Template:", str_replace( "}}", "", str_replace( "\\", "", implode( "|", $config['deadlink_tags'] ) ) ) ), 5000, $return );
				$return = $pages[1];
				$pages = $pages[0];
				file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."c", serialize( array( 'pages' => $pages, 'return' => $return ) ) );
			} else {
				if( $lastpage !== false ) {
					foreach( $pages as $tcount => $tpage ) if( $lastpage['title'] == $tpage['title'] ) break;
					$pages = array_slice( $pages, $tcount );
				}
			}
			echo "Round $iteration: Fetched ".count($pages)." articles!!\n\n";
		}

		//Begin page analysis
		if( WORKERS === false || DEBUG === true ) {
			foreach( $pages as $tid => $tpage ) {
				$pagesAnalyzed++;
				$runpagecount++;
				if( WORKERS === false ) {
					$commObject = new API( $tpage['title'], $tpage['pageid'], $config );
					$tmp = PARSERCLASS;
					$parser = new $tmp( $commObject );
					$stats = $parser->analyzePage();
					$commObject->closeResources();
					$parser = $commObject = null;
				} else {
					$testbot[$tid] = new ThreadedBot( $tpage['title'], $tpage['pageid'], $config, "test" );
					$testbot[$tid]->run();
					$stats = $testbot[$tid]->result;
				}
				if( $stats['pagemodified'] === true ) $pagesModified++;
				$linksAnalyzed += $stats['linksanalyzed'];
				$linksArchived += $stats['linksarchived'];
				$linksFixed += $stats['linksrescued'];
				$linksTagged += $stats['linkstagged'];
				if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
				if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
			}
		} else {
			if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/" ) &&  $handle = opendir( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers" ) ) {
				 while( false !== ( $entry = readdir( $handle ) ) ) {
					if( $entry == "." || $entry == ".." ) continue;
					$tmp = unserialize( file_get_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/$entry" ) );
					if( $tmp === false ) {
						$tmp = null;
						unlink( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/$entry" );
						continue;
					}
					$pagesAnalyzed++;
					if( $tmp['pagemodified'] === true ) $pagesModified++;
					$linksAnalyzed += $tmp['linksanalyzed'];
					$linksArchived += $tmp['linksarchived'];
					$linksFixed += $tmp['linksrescued'];
					$linksTagged += $tmp['linkstagged'];
					$tmp = null;
					unlink( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/$entry" );
				}
				unset( $tmp );
				file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
			}
			if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/" ) ) closedir( $handle );
			$workerQueue = new Pool( $workerLimit );
			foreach( $pages as $tid => $tpage ) {
				$pagesAnalyzed++;
				$runpagecount++;
				echo "Submitted {$tpage['title']}, job ".($tid+1)." for analyzing...\n";
				$workerQueue->submit( new ThreadedBot( $tpage['title'], $tpage['pageid'], $config, $tid ) );
				if( LIMITEDRUN === true && is_int( $debugStyle ) && $debugStyle === $runpagecount ) break;
			}
			$workerQueue->shutdown();
			$workerQueue->collect(
			function( $thread ) {
				global $pagesModified, $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged;
				$stats = $thread->result;
				if( $stats['pagemodified'] === true ) $pagesModified++;
				$linksAnalyzed += $stats['linksanalyzed'];
				$linksArchived += $stats['linksarchived'];
				$linksFixed += $stats['linksrescued'];
				$linksTagged += $stats['linkstagged'];
				$stats = null;
				unset( $stats );
				return $thread->isGarbage();
			});
			if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/" ) &&  $handle = opendir( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers" ) ) {
				 while( false !== ( $entry = readdir( $handle ) ) ) {
					if( $entry == "." || $entry == ".." ) continue;
					unlink( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/$entry" );
				}
			}
			if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."workers/" ) ) closedir( $handle );
			echo "STATUS REPORT:\nLinks analyzed so far: $linksAnalyzed\nLinks archived so far: $linksArchived\nLinks fixed so far: $linksFixed\nLinks tagged so far: $linksTagged\nPages modified so far: $pagesModified\n\n";
			file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats", serialize( array( 'linksAnalyzed' => $linksAnalyzed, 'linksArchived' => $linksArchived, 'linksFixed' => $linksFixed, 'linksTagged' => $linksTagged, 'pagesModified' => $pagesModified, 'pagesAnalyzed' => $pagesAnalyzed, 'runstart' => $runstart ) ) );
		}
		unset( $pages );
	} while( !empty( $return ) && DEBUG === false && LIMITEDRUN === false );
	$pages = false;
	$runend = time();
	echo "Printing log report, and starting new run...\n\n";
	if( DEBUG === false && LIMITEDRUN === false ) DB::generateLogReport();
	if( file_exists( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats" ) && LIMITEDRUN === false ) unlink( IAPROGRESS.WIKIPEDIA.UNIQUEID."stats" );
	if( DEBUG === false && LIMITEDRUN === false ) sleep(10);
	if( DEBUG === true || LIMITEDRUN === true ) exit(0);
}