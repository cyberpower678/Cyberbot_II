<?php

/*
	Copyright (c) 2016, Maximilian Doerr

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	IABot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with IABot.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
* @file
* Parser object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* Parser class
* Allows for the parsing on project specific wiki pages
* @abstract
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
abstract class Parser {

	/**
	* The API class
	*
	* @var API
	* @access public
	*/
	public $commObject;

	/**
	* The checkIfDead class
	*
	* @var checkIfDead
	* @access protected
	*/
	protected $deadCheck;

	/**
	 * The Regex for fetching templates with parameters being optional
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexOptional = '/({{{{templates}}}})[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?))?\}\}/i';

	/**
	 * The Regex for fetching templates with parameters being mandatory
	 *
	 * @var string
	 * @access protected
	 */
	protected $templateRegexMandatory = '/({{{{templates}}}})[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?)\}\}/i';

	/**
	* Parser class constructor
	*
	* @param API $commObject
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;
		$this->deadCheck = new checkIfDead();
	}

	/**
	* Master page analyzer function.  Analyzes the entire page's content,
	* retrieves specified URLs, and analyzes whether they are dead or not.
	* If they are dead, the function acts based on onwiki specifications.
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array containing analysis statistics of the page
	*/
	public function analyzePage() {
		if( DEBUG === false || LIMITEDRUN === true ) file_put_contents( IAPROGRESS.WIKIPEDIA.UNIQUEID, serialize( array( 'title' => $this->commObject->page, 'id' => $this->commObject->pageid ) ) );
		unset($tmp);
		if( WORKERS === false ) echo "Analyzing {$this->commObject->page} ({$this->commObject->pageid})...\n";
		$modifiedLinks = array();
		$archiveProblems = array();
		$archived = 0;
		$rescued = 0;
		$notrescued = 0;
		$tagged = 0;
		$analyzed = 0;
		$newlyArchived = array();
		$timestamp = date( "Y-m-d\TH:i:s\Z" );
		$history = array();
		$newtext = $this->commObject->content;

		if( $this->commObject->config['link_scan'] == 0 ) $links = $this->getExternalLinks();
		else $links = $this->getReferences();
		$analyzed = $links['count'];
		unset( $links['count'] );

		//Process the links
		$checkResponse = $archiveResponse = $fetchResponse = $toArchive = $toFetch = array();
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;
				if( ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->config['archive_alive'] == 1 ) {
					if( $reference === false ) $toArchive[$tid] = $link['url'];
					else $toArchive["$tid:$id"] = $link['url'];
				}
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );
		}
		if( !empty( $toArchive ) ) {
			$checkResponse = $this->commObject->isArchived( $toArchive );
			$checkResponse = $checkResponse['result'];
			$toArchive = array();
		}
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;

				//Create a flag that marks the source as being improperly formatting and needing fixing
				$invalidEntry = (( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) || ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )) && $link['link_type'] != "x";
				//Create a flag that determines basic clearance to edit a source.
				$linkRescueClearance = ((($this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false) && $link['permanent_dead'] === false) || $invalidEntry === true) && $link['link_type'] != "x";
				//DEAD_ONLY = 0; Modify ALL links clearance flag
				$dead0 = $this->commObject->config['dead_only'] == 0 && !($link['tagged_dead'] === true && $link['is_dead'] === false && $this->commObject->config['tag_override'] == 0);
				//DEAD_ONLY = 1; Modify only tagged links clearance flag
				$dead1 = $this->commObject->config['dead_only'] == 1 && ($link['tagged_dead'] === true && ($link['is_dead'] === true || $this->commObject->config['tag_override'] == 1));
				//DEAD_ONLY = 2; Modify all dead links clearance flag
				$dead2 = $this->commObject->config['dead_only'] == 2 && (($link['tagged_dead'] === true && $this->commObject->config['tag_override'] == 1) || $link['is_dead'] === true);

				if( $reference === true && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->config['archive_alive'] == 1 && !$checkResponse["$tid:$id"] ) {
					$toArchive["$tid:$id"] = $link['url'];
				} elseif( $reference === false && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->config['archive_alive'] == 1 && !$checkResponse[$tid] ) {
					$toArchive[$tid] = $link['url'];
				}
				if( ($linkRescueClearance === true && ($dead0 === true || $dead1 === true || $dead2 === true)) || $invalidEntry === true ) {
					if( $reference === false ) $toFetch[$tid] = array( $link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ? ( $link['access_time'] != "x" ? $link['access_time'] : null ) : null ) );
					else $toFetch["$tid:$id"] = array( $link['url'], ( $this->commObject->config['archive_by_accessdate'] == 1 ? ( $link['access_time'] != "x" ? $link['access_time'] : null ) : null ) );
				}
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );

		}
		$errors = array();
		if( !empty( $toArchive ) ) {
			$archiveResponse = $this->commObject->requestArchive( $toArchive );
			$errors = $archiveResponse['errors'];
			$archiveResponse = $archiveResponse['result'];
		}
		if( !empty( $toFetch ) ) {
			$fetchResponse = $this->commObject->retrieveArchive( $toFetch );
			$fetchResponse = $fetchResponse['result'];
		}
		foreach( $links as $tid=>$link ) {
			if( $link['link_type'] == "reference" ) $reference = true;
			else $reference = false;
			$id = 0;
			do {
				if( $reference === true ) $link = $links[$tid]['reference'][$id];
				else $link = $link[$link['link_type']];
				if( isset( $link['ignore'] ) && $link['ignore'] === true ) break;

				//Create a flag that marks the source as being improperly formatting and needing fixing
				$invalidEntry = (( $link['has_archive'] === true && $link['archive_type'] == "invalid" ) || ( $link['tagged_dead'] === true && $link['tag_type'] == "invalid" )) && $link['link_type'] != "x";
				//Create a flag that determines basic clearance to edit a source.
				$linkRescueClearance = ((($this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false) && $link['permanent_dead'] === false) || $invalidEntry === true) && $link['link_type'] != "x";
				//DEAD_ONLY = 0; Modify ALL links clearance flag
				$dead0 = $this->commObject->config['dead_only'] == 0 && !($link['tagged_dead'] === true && $link['is_dead'] === false && $this->commObject->config['tag_override'] == 0);
				//DEAD_ONLY = 1; Modify only tagged links clearance flag
				$dead1 = $this->commObject->config['dead_only'] == 1 && ($link['tagged_dead'] === true && ($link['is_dead'] === true || $this->commObject->config['tag_override'] == 1));
				//DEAD_ONLY = 2; Modify all dead links clearance flag
				$dead2 = $this->commObject->config['dead_only'] == 2 && (($link['tagged_dead'] === true && $this->commObject->config['tag_override'] == 1) || $link['is_dead'] === true);
				//Tag remove clearance flag
				$tagremoveClearance = $link['tagged_dead'] === true && $link['is_dead'] === false && $this->commObject->config['tag_override'] == 0;

				if( $reference === true && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->config['archive_alive'] == 1 && !$checkResponse["$tid:$id"] ) {
					if( $archiveResponse["$tid:$id"] === true ) {
						$archived++;
					} elseif( $archiveResponse["$tid:$id"] === false ) {
						$archiveProblems["$tid:$id"] = $link['url'];
					}
				} elseif( $reference === false && ( $link['is_dead'] !== true && $link['tagged_dead'] !== true ) && $this->commObject->config['archive_alive'] == 1 && !$checkResponse[$tid] ) {
					if( $archiveResponse[$tid] === true ) {
						$archived++;
					} elseif( $archiveResponse[$tid] === false ) {
						$archiveProblems[$tid] = $link['url'];
					}
				}

				if( ($linkRescueClearance === true && ($dead0 === true || $dead1 === true || $dead2 === true)) || $invalidEntry === true ) {
					if( ($reference === false && ($temp = $fetchResponse[$tid]) !== false) || ($reference === true && ($temp = $fetchResponse["$tid:$id"]) !== false) ) {
						$rescued++;
						$this->rescueLink( $link, $modifiedLinks, $temp, $tid, $id );
					} else {
						$notrescued++;
						if( $link['tagged_dead'] !== true ) $link['newdata']['tagged_dead'] = true;
						else continue;
						$tagged++;
						$this->noRescueLink( $link, $modifiedLinks, $tid, $id );
					}
				} elseif( $tagremoveClearance ) {
					$rescued++;
					$modifiedLinks["$tid:$id"]['type'] = "tagremoved";
					$modifiedLinks["$tid:$id"]['link'] = $link['url'];
					$link['newdata']['tagged_dead'] = false;
				}
				if( isset( $link['template_url'] ) ) {
					$link['url'] = $link['template_url'];
					unset( $link['template_url'] );
				}
				if( $reference === true ) $links[$tid]['reference'][$id] = $link;
				else $links[$tid][$links[$tid]['link_type']] = $link;
			} while( $reference === true && isset( $links[$tid]['reference'][++$id] ) );

			if( Parser::newIsNew( $links[$tid] ) ) {
				$links[$tid]['newstring'] = $this->generateString( $links[$tid] );
				$newtext = str_replace( $links[$tid]['string'], $links[$tid]['newstring'], $newtext );
			}
		}
		$archiveResponse = $checkResponse = $fetchResponse = null;
		unset( $archiveResponse, $checkResponse, $fetchResponse );
		if( WORKERS === true ) {
			echo "Analyzed {$this->commObject->page} ({$this->commObject->pageid})\n";
		}
		echo "Rescued: $rescued; Tagged dead: $tagged; Archived: $archived; Memory Used: ".(memory_get_usage( true )/1048576)." MB; Max System Memory Used: ".(memory_get_peak_usage(true)/1048576)." MB\n";
		if( !empty( $archiveProblems ) && $this->commObject->config['notify_error_on_talk'] == 1 ) {
			$out = "";
			foreach( $archiveProblems as $id=>$problem ) {
				$magicwords = array();
				$magicwords['problem'] = $problem;
				$magicwords['error'] = $errors[$id];
				$out .= "* ".$this->commObject->getConfigText( "plerror", $magicwords )."\n";
			}
			$body = $this->commObject->getConfigText( "talk_error_message", array( 'problematiclinks' => $out ) )."~~~~";
			API::edit( "Talk:{$this->commObject->page}", $body, $this->commObject->getConfigText( "errortalkeditsummary", array() ), false, true, "new", $this->commObject->getConfigText( "talk_error_message_header", array() ) );
		}
		$pageModified = false;
		if( $this->commObject->content != $newtext ) {
			$pageModified = true;
			$magicwords = array();
			$magicwords['namespacepage'] = $this->commObject->page;
			$magicwords['linksmodified'] = $tagged+$rescued;
			$magicwords['linksrescued'] = $rescued;
			$magicwords['linksnotrescued'] = $notrescued;
			$magicwords['linkstagged'] = $tagged;
			$magicwords['linksarchived'] = $archived;
			$magicwords['linksanalyzed'] = $analyzed;
			$magicwords['pageid'] = $this->commObject->pageid;
			$magicwords['title'] = urlencode($this->commObject->page);
			$magicwords['logstatus'] = "fixed";
			if( $this->commObject->config['notify_on_talk_only'] == 0 ) $revid = API::edit( $this->commObject->page, $newtext, $this->commObject->getConfigText( "maineditsummary", $magicwords ), false, $timestamp );
			else $magicwords['logstatus'] = "posted";
			if( isset( $revid ) ) {
				$magicwords['diff'] = str_replace( "api.php", "index.php", API )."?diff=prev&oldid=$revid";
				$magicwords['revid'] = $revid;
			} else {
				$magicwords['diff'] = "";
				$magicwords['revid'] = "";
			}
			if( ($this->commObject->config['notify_on_talk'] == 1 && $revid !== false) || $this->commObject->config['notify_on_talk_only'] == 1 ) {
				$out = "";
				$editTalk = false;
				foreach( $modifiedLinks as $tid=>$link ) {
					if( $this->commObject->config['notify_on_talk_only'] == 1 && !$this->commObject->db->setNotified( $tid ) ) continue;
					$magicwords2 = array();
					$magicwords2['link'] = $link['link'];
					if( isset( $link['oldarchive'] ) ) $magicwords2['oldarchive'] = $link['oldarchive'];
					if( isset( $link['newarchive'] ) ) $magicwords2['newarchive'] = $link['newarchive'];
					$out .= "*";
					switch( $link['type'] ) {
						case "addarchive":
							$out .= $this->commObject->getConfigText( "mladdarchive", $magicwords2 );
							$editTalk = true;
						break;
						case "modifyarchive":
							$out .= $this->commObject->getConfigText( "mlmodifyarchive", $magicwords2 );
							$editTalk = true;
						break;
						case "fix":
							$out .= $this->commObject->getConfigText( "mlfix", $magicwords2 );
						break;
						case "tagged":
							$out .= $this->commObject->getConfigText( "mltagged", $magicwords2 );
						break;
						case "tagremoved":
							$out .= $this->commObject->getConfigText( "mltagremoved", $magicwords2 );
						break;
						default:
							$out .= $this->commObject->getConfigText( "mldefault", $magicwords2 );
							$editTalk = true;
						break;
					}
					$out .= "\n";
				}
				$magicwords['modifiedlinks'] = $out;
				$header = $this->commObject->getConfigText( "talk_message_header", $magicwords );
				$body = $this->commObject->getConfigText( "talk_message", $magicwords )."~~~~";
				if( $editTalk === true ) API::edit( "Talk:{$this->commObject->page}", $body, $this->commObject->getConfigText( "talkeditsummary", $magicwords ), false, false, true, "new", $header );
			}
			$this->commObject->logCentralAPI( $magicwords );
		}
		$this->commObject->db->updateDBValues();

		echo "\n";

		$newtext = $history = null;
		unset( $this->commObject, $newtext, $history, $res, $db );
		$returnArray = array( 'linksanalyzed'=>$analyzed, 'linksarchived'=>$archived, 'linksrescued'=>$rescued, 'linkstagged'=>$tagged, 'pagemodified'=>$pageModified );
		return $returnArray;
	}

	/**
	* Parses a given refernce/external link string and returns details about it.
	*
	* @param string $linkString Primary reference string
	* @param string $remainder Left over stuff that may apply
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array	Details about the link
	*/
	public function getLinkDetails( $linkString, $remainder ) {
		$returnArray = array();
		$returnArray['link_string'] = $linkString;
		$returnArray['remainder'] = $remainder;
		$returnArray['has_archive'] = false;
		$returnArray['link_type'] = "x";
		$returnArray['tagged_dead'] = false;
		$returnArray['is_archive'] = false;
		$returnArray['access_time'] = false;
		$returnArray['tagged_paywall'] = false;
		$returnArray['is_paywall'] = false;
		$returnArray['permanent_dead'] = false;

		//Check if there are tags flagging the bot to ignore the source
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $remainder, $params ) || preg_match( $this->fetchTemplateRegex( $this->commObject->config['ignore_tags'] ), $linkString, $params ) ) {
			return array( 'ignore' => true );
		}
		if( !preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ), $linkString, $params ) && preg_match( '/((?:https?:|ftp:)?\/\/([!#$&-;=?-Z_a-z~]|%[0-9a-f]{2})+)/i', $linkString, $params ) ) {
			$this->analyzeBareURL( $returnArray, $params );
		} elseif( preg_match( $this->fetchTemplateRegex( $this->commObject->config['citation_tags'], false ), $linkString, $params ) ) {
			if( $this->analyzeCitation( $returnArray, $params ) ) return array( 'ignore' => true );
		}
		//Check the source remainder
		$this->analyzeRemainder( $returnArray, $remainder );

		//Check for the presence of a paywall tag
		if( preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $remainder, $params ) || preg_match( $this->fetchTemplateRegex( $this->commObject->config['paywall_tags'] ), $linkString, $params ) ) {
			$returnArray['tagged_paywall'] = true;
		}

		//If there is no url after this then this source is useless.
		if( !isset( $returnArray['url'] ) ) return array( 'ignore' => true );

		//Resolve templates, into URLs
		//If we can't resolve them, then ignore this link, as it will be fruitless to handle them.
		if( strpos( $returnArray['url'], "{{" ) !== false ) {
			preg_match( '/\{\{[\s\S\n]*\|?([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\}/i', $returnArray['url'], $params );
			$returnArray['template_url'] = $returnArray['url'];
			$returnArray['url'] = API::resolveExternalLink( $returnArray['template_url'] );
			if( $returnArray['url'] === false ) $returnArray['url'] = API::resolveExternalLink( "https:".$returnArray['template_url'] );
			if( $returnArray['url'] === false ) return array( 'ignore' => true );
		}
		//Filter out HTML comments
		$returnArray['url'] = preg_replace( '/\<\!\-\-(.|\n)*?\-\-\>/i', "", $returnArray['url'] );
		//Extract nonsense stuff from the URL, probably due to a misuse of wiki syntax
		//If a url isn't found, it means it's too badly formatted to be of use, so ignore

		if( (($returnArray['link_type'] === "template" || (strpos( $returnArray['url'], "[" ) &&
				strpos( $returnArray['url'], "]" ))) &&
				preg_match( '/(?:[a-z0-9\+\-\.]*:)?\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\/\?\#\[\]]+)*\/?(?:\?[^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?/i', $returnArray['url'], $match )) ||
                preg_match( '/(?:[a-z0-9\+\-\.]*:)\/\/(?:(?:[^\s\/\?\#\[\]@]*@)?(?:\[[0-9a-f]*?(?:\:[0-9a-f]*)*\]|\d+\.\d+\.\d+\.\d+|[^\:\s\/\?\#\[\]@]+)(?:\:\d+)?)(?:\/[^\s\/\?\#\[\]]+)*\/?(?:\?[^\s\#\[\]]*)?(?:\#([^\s\#\[\]]*))?/i', $returnArray['url'], $match ) ) {
			$returnArray['url'] = $match[0];
			if( isset( $match[1] ) ) $returnArray['fragment'] = $match[1];
			else $returnArray['fragment'] = null;
		} else {
			return array( 'ignore' => true );
		}

		if( $returnArray['access_time'] === false ) {
			$returnArray['access_time'] = "x";
		}
		if( !isset( $returnArray['ignore'] ) && isset( $returnArray['archive_time'] ) && $returnArray['archive_time'] === false ) {
			$returnArray['archive_time'] = strtotime( preg_replace( '/(?:https?:)?\/?\/?(web.)?archive\.org\/(web\/)?(\d{14})\/(\S*)\s?/i', '$3', $returnArray['archive_url'] ) );
		}

		return $returnArray;
	}

	/**
	* Generate a string to replace the old string
	*
	* @param array $link Details about the new link including newdata being injected.
	* @access public
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string New source string
	*/
	public abstract function generateString( $link );

	/**
	 * Generates an appropriate archive template if it can.
	 *
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @param $link Current link being modified
	 * @param $temp Current temp result from fetchResponse
	 * @return bool If successful or not
	 */
	protected abstract function generateNewArchiveTemplate( &$link, &$temp );

	/**
	* Look for stored access times in the DB, or update the DB with a new access time
	* Adds access time to the link details.
	*
	* @param array $links A collection of links with respective details
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Returns the same array with the access_time parameters updated
	*/
	public function updateAccessTimes( $links ) {
		$toGet = array();
		foreach( $links as $tid=>$link ) {
			if( !isset( $this->commObject->db->dbValues[$tid]['createglobal'] ) && $link['access_time'] == "x" ) $links[$tid]['access_time'] = $this->commObject->db->dbValues[$tid]['access_time'];
			elseif( $link['access_time'] == "x" ) {
				$toGet[$tid] = $link['url'];
			} else {
				$this->commObject->db->dbValues[$tid]['access_time'] = $link['access_time'];
			}
		}
		if( !empty( $toGet ) ) $toGet = $this->commObject->getTimesAdded( $toGet );
		foreach( $toGet as $tid=>$time ) {
			$this->commObject->db->dbValues[$tid]['access_time'] = $links[$tid]['access_time'] = $time;
		}
		return $links;
	}

	/**
	* Update the link details array with values stored in the DB, and vice versa
	* Updates the dead status of the given link
	*
	* @param array $link Array of link with details
	* @param int $tid Array key to preserve index keys
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Returns the same array with updated values, if any
	*/
	public function updateLinkInfo( $links ) {
		$toCheck = array();
		foreach( $links as $tid => $link ) {
			if( ( $this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false ) && $this->commObject->config['verify_dead'] == 1 && $this->commObject->db->dbValues[$tid]['live_state'] !== 0 && $this->commObject->db->dbValues[$tid]['live_state'] !== 5 && (time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200) ) $toCheck[$tid] = $link['url'];
		}
		$results = $this->deadCheck->checkDeadlinks( $toCheck );
		$results = $results['results'];
		foreach( $links as $tid => $link ) {
			$link['is_dead'] = null;
			if( ( $this->commObject->config['touch_archive'] == 1 || $link['has_archive'] === false ) && $this->commObject->config['verify_dead'] == 1 ) {
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 && $this->commObject->db->dbValues[$tid]['live_state'] != 5 && (time() - $this->commObject->db->dbValues[$tid]['last_deadCheck'] > 259200) ) {
					$link['is_dead'] = $results[$tid];
					$this->commObject->db->dbValues[$tid]['last_deadCheck'] = time();
					if( $link['tagged_dead'] === false && $link['is_dead'] === true ) {
						$this->commObject->db->dbValues[$tid]['live_state']--;
					} elseif( $link['tagged_dead'] === false && $link['is_dead'] === false && $this->commObject->db->dbValues[$tid]['live_state'] != 3 ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					} elseif( $link['tagged_dead'] === true && ( $this->commObject->config['tag_override'] == 1 || $link['is_dead'] === true ) ) {
						$this->commObject->db->dbValues[$tid]['live_state'] = 0;
					} else {
						$this->commObject->db->dbValues[$tid]['live_state'] = 3;
					}
				}
				if( $this->commObject->db->dbValues[$tid]['live_state'] == 0 ) $link['is_dead'] = true;
				if( $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) $link['is_dead'] = false;
				if( !isset( $this->commObject->db->dbValues[$tid]['live_state'] ) || $this->commObject->db->dbValues[$tid]['live_state'] == 4 || $this->commObject->db->dbValues[$tid]['live_state'] == 5 ) $link['is_dead'] = null;
			}
			if( $link['tagged_dead'] === true && $this->commObject->config['tag_override'] == 1 && $this->commObject->db->dbValues[$tid]['live_state'] != 0 ) {
				$this->commObject->db->dbValues[$tid]['live_state'] = 0;
			}
			$links[$tid] = $link;
		}
		return $links;
	}

	/**
	* Read and parse the reference string.
	* Extract the reference parameters
	*
	* @param string $refparamstring reference string
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Contains the parameters as an associative array
	*/
	public function getReferenceParameters( $refparamstring ) {
		$returnArray = array();
		preg_match_all( '/(\S*)\s*=\s*(".*?"|\'.*?\'|\S*)/i', $refparamstring, $params );
		foreach( $params[0] as $tid => $tvalue ) {
			$returnArray[$params[1][$tid]] = $params[2][$tid];
		}
		return $returnArray;
	}

	//Parsing engine of templates.  This parses the body string of a template, respecting embedded templates and wikilinks.
	/**
	* Fetch the parameters of the template
	*
	* @param string $templateString String of the template without the {{example bit
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Template parameters with respective values
	*/
	public function getTemplateParameters( $templateString ) {
		$errorSetting = error_reporting();
		$returnArray = array();
		$tArray = array();
		if( empty( $templateString ) ) return $returnArray;
		$templateString = trim( $templateString );
		//Suppress errors for this functions.  While it almost never throws an error,
		//some misformatted templates cause the template parser to throw up.
		//In all cases however, a failure to properly parse the template will always
		//result in false being returned, error or not.  No sense in cluttering the output.
		error_reporting( 0 );
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
				if( $loopcount >= 500 )
				{
					//re-enable error reporting
					error_reporting( $errorSetting );
					return false;
				}
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
		//re-enable error reporting
		error_reporting( $errorSetting );
		return $returnArray;
	}

	/**
	* Destroys the class
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __destruct() {
		$this->deadCheck = null;
		$this->commObject = null;
	}

		/**
	* Parses the pages for refences, citation templates, and bare links.
	*
	* @param bool $referenceOnly
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array All parsed links
	*/
	protected function parseLinks( $referenceOnly = false ) {
		$returnArray = array();
		$tArray = array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'], $this->commObject->config['ignore_tags'], $this->commObject->config['ic_tags'], $this->commObject->config['paywall_tags'] );
		$scrapText = $this->commObject->content;
        //Filter out the comments and plaintext rendered markup.
        $filteredText = $this->filterText( $this->commObject->content );
		$regex = '/<\/ref\s*?>\s*?((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ).')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?))?\}\})*)/i';
		$tid = 0;
		while( preg_match( '/<ref([^\/]*?)>/i', $scrapText, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = $match[0][1];
			if( ($endoffset = strpos( $scrapText, "</ref", $offset )) === false ) break;
			if( preg_match( $regex, $scrapText, $match1, PREG_OFFSET_CAPTURE, $endoffset ) ) {
				$endoffset = $match1[0][1];
				$scrappy = substr( $scrapText, $offset, $endoffset-$offset );
				$fullmatch = $scrappy.$match1[0][0];
				$returnArray[$tid]['string'] = $fullmatch;
				$returnArray[$tid]['remainder'] = $match1[1][0];
				$returnArray[$tid]['type'] = "reference";
			} else break;

			$returnArray[$tid]['parameters'] = $this->getReferenceParameters( $match[1][0] );
			$returnArray[$tid]['link_string'] = str_replace( $match[0][0], "", $scrappy );
			$scrappy = $returnArray[$tid]['link_string'];
			$returnArray[$tid]['contains'] = array();
			while( ($temp = $this->getNonReference( $scrappy )) !== false ) {
				$returnArray[$tid]['contains'][] = $temp;
			}
            //If the filtered match is no where to be found, then it's being rendered in plaintext or is a comment
            //We want to leave those alone.
			if( strpos( $filteredText, $this->filterText( $fullmatch ) ) !== false ) {
                $tid++;
                $filteredText = str_replace( $this->filterText( $fullmatch ), "", $filteredText );
            } else {
                unset( $returnArray[$tid] );
            }
			$scrapText = str_replace( $fullmatch, "", $scrapText );
		}
		if( $referenceOnly === false ) {
			while( ($temp = $this->getNonReference( $scrapText )) !== false ) {
			    if( strpos( $filteredText, $this->filterText( $temp['string'] ) ) !== false ) {
                    $returnArray[] = $temp;
                    $filteredText = str_replace( $this->filterText( $temp['string'] ), "", $filteredText );
                }
			}
		}
		return $returnArray;
	}

	/**
	* Fetch all links in an article
	*
	* @param bool $referenceOnly Fetch references only
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details about every link on the page
	*/
	public function getExternalLinks( $referenceOnly = false ) {
		$linksAnalyzed = 0;
		$returnArray = array();
		$toCheck = array();
		$parseData = $this->parseLinks( $referenceOnly );
		$lastLink = array( 'tid'=>null, 'id'=>null );
		$currentLink = array( 'tid'=>null, 'id'=>null );
		foreach( $parseData as $tid=>$parsed ){
			if( empty( $parsed['link_string'] ) && empty( $parsed['remainder'] ) ) continue;
			if( $parsed['type'] == "reference" && empty( $parsed['contains'] ) ) continue;
			$returnArray[$tid]['link_type'] = $parsed['type'];
			$returnArray[$tid]['string'] = $parsed['string'];
			if( $parsed['type'] == "reference" ) {
				foreach( $parsed['contains'] as $parsedlink ) $returnArray[$tid]['reference'][] = array_merge( $this->getLinkDetails( $parsedlink['link_string'], $parsedlink['remainder'].$parsed['remainder'] ), array( 'string'=>$parsedlink['string'] ) );
			} else {
				$returnArray[$tid][$parsed['type']] = $this->getLinkDetails( $parsed['link_string'], $parsed['remainder'] );
			}
			if( $parsed['type'] == "reference" ) {
				if( !empty( $parsed['parameters'] ) ) $returnArray[$tid]['reference']['parameters'] = $parsed['parameters'];
				$returnArray[$tid]['reference']['link_string'] = $parsed['link_string'];
			}
			if( $parsed['type'] == "template" ) {
				$returnArray[$tid]['template']['name'] = $parsed['name'];
			}
			if( !isset( $returnArray[$tid][$parsed['type']]['ignore'] ) || $returnArray[$tid][$parsed['type']]['ignore'] === false ) {
				if( $parsed['type'] == "reference" ) {
					foreach( $returnArray[$tid]['reference'] as $id=>$link ) {
						if( !is_int( $id ) || isset( $link['ignore'] ) ) continue;
						$currentLink['tid'] = $tid;
						$currentLink['id'] = $id;
						if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
							$toCheck["{$lastLink['tid']}:{$lastLink['id']}"] = $returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
							continue;
						}
						$linksAnalyzed++;
						$this->commObject->db->retrieveDBValues( $returnArray[$tid]['reference'][$id], "$tid:$id" );
						$toCheck["$tid:$id"] = $returnArray[$tid]['reference'][$id];
						$lastLink['tid'] = $tid;
						$lastLink['id'] = $id;
					}
				} else {
					$currentLink['tid'] = $tid;
					$currentLink['id'] = null;
					if( $this->isConnected( $lastLink, $currentLink, $returnArray ) ) {
						$toCheck[$lastLink['tid']] = $returnArray[$lastLink['tid']][$parseData[$lastLink['tid']]['type']];
						continue;
					}
					$linksAnalyzed++;
					$this->commObject->db->retrieveDBValues( $returnArray[$tid][$parsed['type']], $tid );
					$toCheck[$tid] = $returnArray[$tid][$parsed['type']];
					$lastLink['tid'] = $tid;
					$lastLink['id'] = null;
				}
			}
		}
		$toCheck = $this->updateAccessTimes( $toCheck );
		$toCheck = $this->updateLinkInfo( $toCheck );
		foreach( $toCheck as $tid=>$link ) {
			if( is_int( $tid ) ) $returnArray[$tid][$returnArray[$tid]['link_type']] = $link;
			else {
				$tid = explode( ":", $tid );
				$returnArray[$tid[0]][$returnArray[$tid[0]]['link_type']][$tid[1]] = $link;
			}
		}
		$returnArray['count'] = $linksAnalyzed;
		return $returnArray;
	}

	/**
	 * Determines if 2 seperate but close together links have a connection to each other.
	 * If so, the link contained in $currentLink will be merged to the previous one.
	 *
	 * @param array $lastLink Index information of last link looked at
	 * @param array $currentLink index of the current link looked at
	 * @param array $returnArray The array of links to look at and modify
	 * @return bool True if the 2 links are related.
	 * @access public
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 */
	public function isConnected( $lastLink, $currentLink, &$returnArray ) {
		//If one is in a reference and the other is not, there can't be a connection.
		if( (!is_null( $lastLink['id'] ) xor !is_null( $currentLink['id'] )) === true ) return false;
		//If the reference IDs are different, also no connection.
		if( (!is_null( $lastLink['id'] ) && !is_null( $currentLink['id'] )) && $lastLink['tid'] !== $currentLink['tid'] ) return false;
		if( is_null( $lastLink['tid'] ) ) return false;
		if( !is_null( $lastLink['id'] ) ) {
			$link = $returnArray[$lastLink['tid']]['reference'][$lastLink['id']];
		} else {
			$link = $returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']];
		}
		if( !is_null( $currentLink['id'] ) ) {
			$temp = $returnArray[$currentLink['tid']]['reference'][$currentLink['id']];
		} else {
			$temp = $returnArray[$currentLink['tid']][$returnArray[$currentLink['tid']]['link_type']];
		}
		if( $link['url'] == $temp['url'] && $temp['is_archive'] === true ) {
			$link['archive_string'] = $temp['link_string'];
			if( ($tstart = strpos( $this->commObject->content, $link['archive_string'] )) !== false && ($lstart = strpos( $this->commObject->content, $link['link_string'] )) !== false ) {
				$link['string'] = substr( $this->commObject->content, $lstart, $tstart-$lstart+strlen( $link['archive_string'].$temp['remainder'] ) );
				$link['remainder'] = str_replace( $link['link_string'], "", $link['string'] );
			}
			if( $temp['has_archive'] === true ) {
				$link['has_archive'] = true;
				$link['archive_type'] = $temp['archive_type'];
				if( $link['archive_type'] == "template" ) {
					$link['archive_template'] = $temp['archive_template'];
				}
				$link['archive_url'] = $temp['archive_url'];
				$link['archive_time'] = $temp['archive_time'];
				if( !isset( $temp['archive_host'] ) ) $link['archive_host'] = $temp['archive_host'];
				if( $link['archive_type'] == "link" ) $link['archive_type'] = "invalid";
				if( $link['archive_type'] == "stray" ) $link['archive_type'] = "template";
			}
			if( $temp['tagged_paywall'] === true ) {
				$link['tagged_paywall'] = true;
			}
			if( $temp['is_paywall'] === true ) {
				$link['is_paywall'] = true;
			}
			if( $temp['permanent_dead'] ===true ) {
				$link['permanent_dead'] = true;
			}
			if( !is_null( $lastLink['id'] ) ) {
				$returnArray[$lastLink['tid']]['reference'][$lastLink['id']] = $link;
			} else {
				$returnArray[$lastLink['tid']][$returnArray[$lastLink['tid']]['link_type']] = $link;
			}
			if( !is_null( $currentLink['id'] ) ) {
				unset( $returnArray[$currentLink['tid']]['reference'][$currentLink['id']] );
			} else {
				unset( $returnArray[$currentLink['tid']] );
			}
			return true;
		}
		return false;
	}

	/**
	* Fetches all references only
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details about every reference found
	*/
	public function getReferences() {
		return $this->getExternallinks( true );
	}

	/**
	* Fetches the first non-reference it finds in the supplied text and returns it.
	* This function will remove the text it found in the passed parameter.
	*
	* @param string $scrapText Text to look at.
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Details of the first non-reference found.  False on failure.
	*/
	protected function getNonReference( &$scrapText = "" ) {
		$returnArray = array();
		$tArray = array_merge( $this->commObject->config['deadlink_tags'], $this->commObject->config['archive_tags'], $this->commObject->config['ignore_tags'], $this->commObject->config['ic_tags'], $this->commObject->config['paywall_tags'] );
		$regex = '/(('.str_replace( "\}\}", "", implode( '|', $this->commObject->config['citation_tags'] ) ).')[\s\n]*\|([\n\s\S]*?(\{\{[\s\S\n]*?\}\}[\s\S\n]*?)*?)\}\})\s*?((\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ).')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?))?\}\})*)/i';
		if( preg_match( $regex, $scrapText, $match ) ) {
			$returnArray['string'] = $match[0];
			$returnArray['link_string'] = $match[1];
			$returnArray['remainder'] = $match[5];
			$returnArray['type'] = "template";
			$returnArray['name'] = str_replace( "{{", "", $match[2] );
			$scrapText = str_replace( $returnArray['string'], "", $scrapText );
			return $returnArray;
		}
		$archiveTemplate = preg_match( '/(\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ).')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?))?\}\})+/i', $scrapText, $archiveMatch, PREG_OFFSET_CAPTURE );
		$bareLink = preg_match( '/[\[]?((?:https?:|ftp:)?\/\/([!#$&-;=?-Z_a-z~]|%[0-9a-f]{2})+)/i', $scrapText, $bareMatch, PREG_OFFSET_CAPTURE );
		if( ($archiveTemplate && $bareLink && $archiveMatch[0][1] > $bareMatch[0][1]) || ($bareLink && !$archiveTemplate) ) {
			$start = 0;
			$returnArray['type'] = "externallink";
			$start = strpos( $scrapText, $bareMatch[0][0], $start );
			if( substr( $bareMatch[0][0], 0, 1 ) == "[" && strpos( $scrapText, "]", $start ) !== false ) {
				$end = strpos( $scrapText, "]", $start ) + 1;
			} else {
				$start = strpos( $scrapText, $bareMatch[1][0] );
				$end = $start + strlen( $bareMatch[1][0] );
			}
			$returnArray['link_string'] = substr( $scrapText, $start, $end-$start );
			$returnArray['remainder'] = "";
			if( preg_match( '/\s*(\s*('.str_replace( "\}\}", "", implode( '|', $tArray ) ).')[\s\n]*(?:\|([\n\s\S]*?(\{\{[\s\S\n]*\}\}[\s\S\n]*?)*?))?\}\})+/i', $scrapText, $match, PREG_OFFSET_CAPTURE, $end ) ) {
				if( !preg_match( $regex, substr( $scrapText, $end, $match[0][1]-$end ), $garbage ) && !preg_match( '/[\[]?((?:https?:|ftp:)?\/\/([!#$&-;=?-Z_a-z~]|%[0-9a-f]{2})+)/i', substr( $scrapText, $end, $match[0][1]-$end ), $garbage ) ) {
                    $match = substr( $scrapText, $end, $match[0][1]-$end + strlen($match[0][0] ) );
                    $end += strlen( $match );
                    $returnArray['remainder'] = trim( $match );
                }

			}
			$returnArray['string'] = trim( substr( $scrapText, $start, $end-$start ) );
			$scrapText = str_replace( $returnArray['string'], "", $scrapText );
			return $returnArray;
		} elseif( ($archiveTemplate && $bareLink && $archiveMatch[0][1] < $bareMatch[0][1]) || (!$bareLink && $archiveTemplate) ) {
			$returnArray['remainder'] = $archiveMatch[0][0];
			$returnArray['link_string'] = "";
			$returnArray['string'] = $archiveMatch[0][0];
			$returnArray['type'] = "stray";
			$returnArray['name'] = str_replace( "{{", "", $archiveMatch[2][0] );
			$scrapText = str_replace( $returnArray['string'], "", $scrapText );
			return $returnArray;
		}
		return false;
	}

    /**
     * Filters out the text that does not get rendered normally.
     * This includes comments, and plaintext formatting.
     *
     * @param string $text String to filter
     * @return string Filtered text.
     * @access protected
     * @author Maximilian Doerr (Cyberpower678)
     * @license https://www.gnu.org/licenses/gpl.txt
     * @copyright Copyright (c) 2016, Maximilian Doerr
     */
	protected function filterText( $text ) {
	    $text = preg_replace( '/\<\s*source(?:.|\n)*?\<\/source\s*\>/i', "", $text );
        $text = preg_replace( '/\<\s*syntaxhighlight(?:.|\n)*?\<\/syntaxhighlight\s*\>/i', "", $text );
        $text = preg_replace( '/\<\s*code(?:.|\n)*?\<\/code\s*\>/i', "", $text );
        $text = preg_replace( '/\<\s*nowiki(?:.|\n)*?\<\/nowiki\s*\>/i', "", $text );
        $text = preg_replace( '/\<\s*pre(?:.|\n)*?\<\/pre\s*\>/i', "", $text );
        $text = preg_replace( '/\<\!\-\-(?:.|\n)*?\-\-\>/i', "", $text );
        return $text;
    }

	/**
	* Analyzes the bare link
	*
	* @param array $returnArray Array being generated
	* @param string $linkString Link string being parsed
	* @param array $params Extracted URL from link string
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected function analyzeBareURL( &$returnArray, &$params ) {

		$returnArray['url'] = $params[1];
		$returnArray['link_type'] = "link";
		$returnArray['access_time'] = "x";
		$returnArray['is_archive'] = false;
		$returnArray['tagged_dead'] = false;
		$returnArray['has_archive'] = false;

		//If this is a bare archive url
		if( $this->isArchive( $returnArray['url'], $returnArray ) ) {
			$returnArray['has_archive'] = true;
			$returnArray['is_archive'] = true;
			$returnArray['archive_type'] = "link";
			$returnArray['link_type'] = "x";
			$returnArray['access_time'] = $returnArray['archive_time'];
		}
	}

	/**
	 * Generates a regex that detects the given list of escaped templates.
	 *
	 * @param array $escapedTemplateArray A list of bracketed templates that have been escaped to search for.
	 * @param bool $optional Make the reqex not require additional template parameters.
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return string Generated regex
	 */
	protected function fetchTemplateRegex( $escapedTemplateArray, $optional = true ) {
		$escapedTemplateArray = implode( '|', $escapedTemplateArray );
		$escapedTemplateArray = str_replace( "\}\}", "", $escapedTemplateArray );
		if( $optional === true ) $returnRegex = $this->templateRegexOptional;
		else $returnRegex = $this->templateRegexMandatory;
		$returnRegex = str_replace( "{{{{templates}}}}", $escapedTemplateArray, $returnRegex );
		return $returnRegex;
	}

	/**
	 * Merge the new data in a custom array_merge function
	 * @access public
	 * @param array $link An array containing details and newdata about a specific reference.
	 * @param bool $recurse Is this function call a recursive call?
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return array Merged data
	 */
	public static function mergeNewData( $link, $recurse = false ) {
		$returnArray = array();
		if( $recurse !== false ) {
			foreach( $link as $parameter => $value ) {
				if( isset( $recurse[$parameter] ) && !is_array( $recurse[$parameter] ) && !is_array( $value ) ) $returnArray[$parameter] = $recurse[$parameter];
				elseif( isset($recurse[$parameter] ) && is_array( $recurse[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = self::mergeNewData( $value, $recurse[$parameter] );
				elseif( isset( $recurse[$parameter] ) ) $returnArray[$parameter] = $recurse[$parameter];
				else $returnArray[$parameter] = $value;
			}
			foreach( $recurse as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
			return $returnArray;
		}
		if( isset( $link['newdata'] ) ) {
			$newdata = $link['newdata'];
			unset( $link['newdata'] );
		} else $newdata = array();
		foreach( $link as $parameter => $value ) {
			if( isset( $newdata[$parameter] ) && !is_array( $newdata[$parameter] )  && !is_array( $value ) ) $returnArray[$parameter] = $newdata[$parameter];
			elseif( isset( $newdata[$parameter] ) && is_array( $newdata[$parameter] ) && is_array( $value ) ) $returnArray[$parameter] = self::mergeNewData( $value, $newdata[$parameter] );
			elseif( isset( $newdata[$parameter] ) ) $returnArray[$parameter] = $newdata[$parameter];
			else $returnArray[$parameter] = $value;
		}
		foreach( $newdata as $parameter => $value ) if( !isset( $returnArray[$parameter]) ) $returnArray[$parameter] = $value;
		return $returnArray;
	}

	/**
	 * Verify that newdata is actually different from old data
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @param mixed $link
	 * @return bool Whether the data in the link array contains new data from the old data.
	 */
	public static function newIsNew( $link ) {
		$t = false;
		if( $link['link_type'] == "reference" ) {
			foreach( $link['reference'] as $tid => $tlink) {
				if( isset( $tlink['newdata'] ) ) foreach( $tlink['newdata'] as $parameter => $value ) {
					if( !isset( $tlink[$parameter] ) || $value != $tlink[$parameter] ) $t = true;
				}
			}
		}
		elseif( isset( $link[$link['link_type']]['newdata'] ) ) foreach( $link[$link['link_type']]['newdata'] as $parameter => $value ) {
			if( !isset( $link[$link['link_type']][$parameter] ) || $value != $link[$link['link_type']][$parameter] ) $t = true;
		}
		return $t;
	}

	/**
	 * Determine if the URL is a common archive, and attempts to resolve to original URL.
	 *
	 * @param string $url The URL to test
	 * @param array $data The data about the URL to pass back
	 * @access protected
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return bool True if it is an archive
	 */
	protected function isArchive( $url, &$data ) {
		if( strpos( $url, "archive.org" ) !== false ) {
			$resolvedData = API::resolveWaybackURL( $url );
		} elseif( strpos( $url, "archive.is" ) !== false || strpos( $url, "archive.today" ) !== false ) {
			$resolvedData = API::resolveArchiveIsURL( $url );
		} elseif( strpos( $url, "mementoweb.org" ) !== false ) {
			$resolvedData = API::resolveMementoURL( $url );
		} elseif( strpos( $url, "webcitation.org" ) !== false ) {
			$resolvedData = API::resolveWebCiteURL( $url );
		} else return false;
		if( !isset( $resolvedData['url'] ) ) return false;
		if( !isset( $resolvedData['archive_url'] ) ) return false;
		if( !isset( $resolvedData['archive_time'] ) ) return false;
		if( !isset( $resolvedData['archive_host'] ) ) return false;
		if( $this->isArchive( $resolvedData['url'], $temp ) ) {
			$data['url'] = $temp['url'];
			$data['archive_url'] = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		} else {
			$data['url'] = $resolvedData['url'];
			$data['archive_url'] = $resolvedData['archive_url'];
			$data['archive_time'] = $resolvedData['archive_time'];
			$data['archive_host'] = $resolvedData['archive_host'];
		}
		return true;
	}

	protected function getArchiveHost( $url ) {
		if( strpos( $url, "archive.org" ) !== false ) {
			return "wayback";
		} elseif( strpos( $url, "archive.is" ) !== false || strpos( $url, "archive.today" ) !== false ) {
			return "archiveis";
		} elseif( strpos( $url, "mementoweb.org" ) !== false ) {
			return "memento";
		} elseif( strpos( $url, "webcitation.org" ) !== false ) {
			return "webcite";
		} else return "unknown";
	}

	/**
	 * Rescue a link
	 *
	 * @param array $link Link being analyzed
	 * @param array $modifiedLinks Links that were modified
	 * @param array $temp Cached result value from archive retrieval function
	 * @access protected
	 * @abstract
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 */
	protected abstract function rescueLink( &$link, &$modifiedLinks, &$temp, $tid, $id );

	/**
	* Modify link that can't be rescued
	*
	* @param array $link Link being analyzed
	* @param array $modifiedLinks Links modified array
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected abstract function noRescueLink( &$link, &$modifiedLinks, $tid, $id );

	/**
	* Get page date formatting standard
	*
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return string Format to be fed in time()
	*/
	protected abstract function retrieveDateFormat();

	/**
	* Analyze the citation template
	*
	* @param array $returnArray Array being generated in master function
	* @param string $params Citation template regex match breakdown
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected abstract function analyzeCitation( &$returnArray, &$params );

	/**
	* Analyze the remainder string
	*
	* @param array $returnArray Array being generated in master function
	* @param string $remainder Remainder string
	* @access protected
	* @abstract
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	protected abstract function analyzeRemainder( &$returnArray, &$remainder );
}