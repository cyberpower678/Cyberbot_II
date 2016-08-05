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
* DB object
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
/**
* DB class
* Manages all DB related parts of the bot
* @author Maximilian Doerr (Cyberpower678)
* @license https://www.gnu.org/licenses/gpl.txt
* @copyright Copyright (c) 2016, Maximilian Doerr
*/
class DB {

	/**
	 * Stores the cached database for a fetched page
	 *
	 * @var array
	 * @access public
	 */
	public $dbValues = array();

	/**
	* Duplicate of dbValues except it remains unchanged
	*
	* @var array
	* @access protected
	*/
	protected $odbValues = array();

	/**
	* Stores the mysqli db resource
	*
	* @var mysqli
	* @access protected
	*/
	protected $db;

	/**
	* Stores the cached DB values for a given page
	*
	* @var array
	* @access protected
	*/
	protected $cachedPageResults = array();

	/**
	* Stores the API object
	*
	* @var API
	* @access public
	*/
	public $commObject;

	/**
	* Constructor of the DB class
	*
	* @param API $commObject
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function __construct( API $commObject ) {
		$this->db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		$this->commObject = $commObject;
		if( $this->db === false ) {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
		$res = mysqli_query( $this->db, "SELECT externallinks_global.url_id, externallinks_global.paywall_id, url, archive_url, has_archive, live_state, unix_timestamp(last_deadCheck) AS last_deadCheck, archivable, archived, archive_failure, unix_timestamp(access_time) AS access_time, unix_timestamp(archive_time) AS archive_time, paywall_status, reviewed, notified
										 FROM externallinks_".WIKIPEDIA."
										 LEFT JOIN externallinks_global ON externallinks_global.url_id = externallinks_".WIKIPEDIA.".url_id
										 LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id = externallinks_paywall.paywall_id
										 WHERE `pageid` = '{$this->commObject->pageid}';" );
		if( $res !== false ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$this->cachedPageResults[] = $result;
			}
			mysqli_free_result( $res );
		}
	}

	/**
	* mysqli escape an array of values including the keys
	*
	* @param array $values Values of the mysqli query
	* @access protected
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return array Sanitized values
	*/
	protected function sanitizeValues( $values ) {
		$returnArray = array();
		foreach( $values as $id=>$value ) {
			if( !is_null( $value ) && ($id != "access_time" && $id != "archive_time" && $id != "last_deadCheck") ) $returnArray[mysqli_escape_string( $this->db, $id )] = mysqli_escape_string( $this->db, $value );
			elseif( !is_null( $value ) ) $returnArray[mysqli_escape_string( $this->db, $id )] = mysqli_escape_string( $this->db, ( $value != 0 ? date( 'Y-m-d H:i:s', $value ) : "0000-00-00 00:00:00" ) );
		}
		return $returnArray;
	}

	/**
	* Flags all dbValues that have changed since they were stored
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function checkForUpdatedValues() {
		foreach( $this->dbValues as $tid=>$values ) {
			foreach( $values as $id=>$value ) {
				if( $id == "url_id" || $id == "paywall_id" ) continue;
				if( !array_key_exists( $id, $this->odbValues[$tid] ) || $this->odbValues[$tid][$id] != $value ) {
					switch( $id ) {
						case "notified":
						if( !isset( $this->dbValues[$tid]['createlocal'] ) ) $this->dbValues[$tid]['updatelocal'] = true;
						break;
						case "paywall_status":
						if( !isset( $this->dbValues[$tid]['createpaywall'] ) ) $this->dbValues[$tid]['updatepaywall'] = true;
						break;
						default:
						if( !isset( $this->dbValues[$tid]['createglobal'] ) ) $this->dbValues[$tid]['updateglobal'] = true;
						break;
					}
				}
			}
		}
	}

	/**
	* Insert contents of self::dbValues back into the DB
	* and delete the unused cached values
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function updateDBValues() {
		$this->checkForUpdatedValues();

		$query = "";
		$updateQueryPaywall = "";
		$updateQueryGlobal = "";
		$updateQueryLocal = "";
		$deleteQuery = "";
		$insertQueryPaywall = "";
		$insertQueryGlobal = "";
		$insertQueryLocal = "";
		if( !empty( $this->dbValues ) ) {
			foreach( $this->dbValues as $id=>$values ) {
				$url = mysqli_escape_string( $this->db, $values['url'] );
				$domain = mysqli_escape_string( $this->db, parse_url( $values['url'], PHP_URL_HOST ) );
				$values = $this->sanitizeValues( $values );
				if( isset( $values['createlocal'] ) ) {
					unset( $values['createlocal'] );
					if( isset( $values['createglobal'] ) ) {
						unset( $values['createglobal'] );
						if( isset( $values['createpaywall'] ) ) {
							unset( $values['createpaywall'] );
							if( empty( $insertQueryPaywall ) ) {
								$insertQueryPaywall = "INSERT INTO `externallinks_paywall`\n\t(`domain`, `paywall_status`)\nVALUES\n";
							}
							// Aggregate unique domain names to insert into externallinks_paywall
							if( !isset( $tipAssigned ) || !in_array( $domain, $tipAssigned ) ) {
								$tipValues[] = array( 'domain' => $domain, 'paywall_status' => (isset( $values['paywall_status'] ) ? $values['paywall_status'] : null) );
								$tipAssigned[] = $domain;
							}
						}
						$tigFields = array( 'reviewed', 'url', 'archive_url', 'has_archive', 'live_state', 'last_deadCheck', 'archivable', 'archived', 'archive_failure', 'access_time', 'archive_time', 'paywall_id' );
						$insertQueryGlobal = "INSERT INTO `externallinks_global`\n\t(`".implode( "`, `", $tigFields )."`)\nVALUES\n";
						if( !isset( $tigAssigned ) || !in_array( $values['url'], $tigAssigned ) ) {
							$temp = array();
							foreach( $tigFields as $field ) {
								if( $field == "paywall_id" ) continue;
								if( isset( $values[$field] ) ) $temp[$field] = $values[$field];
							}
							$temp['domain'] = $domain;
							$tigValues[] = $temp;
							$tigAssigned[] = $values['url'];
						}
					}
					$tilFields = array( 'notified', 'pageid', 'url_id' );
					$insertQueryLocal = "INSERT INTO `externallinks_".WIKIPEDIA."`\n\t(`".implode( "`, `", $tilFields )."`)\nVALUES\n";
					$temp = array();
					foreach( $tilFields as $field ) {
						if( $field == "url_id" ) continue;
						if( isset( $values[$field] ) ) $temp[$field] = $values[$field];
					}
					$temp['url'] = $values['url'];
					$tilValues[] = $temp;
				}
				if( isset( $values['updatepaywall'] ) ) {
					unset( $values['updatepaywall'] );
					if( empty( $updateQueryPaywall ) ) {
						$tupfields = array( 'paywall_status' );
						$updateQueryPaywall = "UPDATE `externallinks_paywall`\n";
					}
					$tupValues[] = $values;
				}
				if( isset( $values['updateglobal'] ) ) {
					unset( $values['updateglobal'] );
					if( empty( $updateQueryGlobal ) ) {
						$tugfields = array( 'archive_url', 'has_archive', 'live_state', 'last_deadCheck', 'archivable', 'archived', 'archive_failure', 'access_time', 'archive_time', 'reviewed' );
						$updateQueryGlobal = "UPDATE `externallinks_global`\n";
					}
					$tugValues[] = $values;
				}
				if( isset( $values['updatelocal'] ) ) {
					unset( $values['updatelocal'] );
					if( empty( $updateQueryLocal ) ) {
						$tulfields = array( 'notified' );
						$updateQueryLocal = "UPDATE `externallinks_".WIKIPEDIA."`\n";
					}
					$tulValues[] = $values;
				}
			}
			if( !empty( $insertQueryPaywall ) ) {
				$comma = false;
				foreach( $tipValues as $value ) {
					if( $comma === true ) $insertQueryPaywall .= "),\n";
					$insertQueryPaywall .= "\t(";
					$insertQueryPaywall .= "'{$value['domain']}', ";
					if( is_null( $value['paywall_status'] ) ) $insertQueryPaywall .= "DEFAULT";
					else $insertQueryPaywall .= "'{$value['paywall_status']}'";
					$comma = true;
				}
				$insertQueryPaywall .= ");\n";
				$query .= $insertQueryPaywall;
			}
			if( !empty( $insertQueryGlobal ) ) {
				$comma = false;
				foreach( $tigValues as $value ) {
					if( $comma === true ) $insertQueryGlobal .= "),\n";
					$insertQueryGlobal .= "\t(";
					foreach( $tigFields as $field ) {
						if( $field == "paywall_id" ) continue;
						if( isset( $value[$field] ) ) $insertQueryGlobal .= "'{$value[$field]}', ";
						else $insertQueryGlobal .= "DEFAULT, ";
					}
					$insertQueryGlobal .= "(SELECT paywall_id FROM externallinks_paywall WHERE `domain` = '{$value['domain']}')";
					$comma = true;
				}
				$insertQueryGlobal .= ");\n";
				$query .= $insertQueryGlobal;
			}
			if( !empty( $insertQueryLocal ) ) {
				$comma = false;
				foreach( $tilValues as $value ) {
					if( $comma === true ) $insertQueryLocal .= "),\n";
					$insertQueryLocal .= "\t(";
					foreach( $tilFields as $field ) {
						if( $field == "pageid" ) continue;
						if( $field == "url_id" ) continue;
						if( isset( $value[$field] ) ) $insertQueryLocal .= "'{$value[$field]}', ";
						else $insertQueryLocal .= "DEFAULT, ";
					}
					$insertQueryLocal .= "'{$this->commObject->pageid}', (SELECT url_id FROM externallinks_global WHERE `url` = '{$value['url']}')";
					$comma = true;
				}
				$insertQueryLocal .= ");\n";
				$query .= $insertQueryLocal;
			}
			if( !empty( $updateQueryPaywall ) ) {
				$updateQueryPaywall .= "\tSET ";
				$IDs = array();
				$updateQueryPaywall .= "`paywall_status` = CASE `paywall_id`\n";
				foreach( $tupValues as $value ) {
					if( isset( $value['paywall_status'] ) ) $updateQueryPaywall .= "\t\tWHEN '{$value['paywall_id']}' THEN '{$value['paywall_status']}'\n";
					else $updateQueryPaywall .= "\t\tWHEN '{$value['paywall_id']}' THEN DEFAULT\n";
					$IDs[] = $value['paywall_id'];
				}
				$updateQueryPaywall .= "\tEND\n";
				$updateQueryPaywall .= "WHERE `paywall_id` IN ('".implode( "', '", $IDs )."');\n";
				$query .= $updateQueryPaywall;
			}
			if( !empty( $updateQueryGlobal ) ) {
				$updateQueryGlobal .= "\tSET ";
				$IDs = array();
				foreach( $tugfields as $field ) {
					$updateQueryGlobal .= "`$field` = CASE `url_id`\n";
					foreach( $tugValues as $value ) {
						if( isset( $value[$field] ) ) $updateQueryGlobal .= "\t\tWHEN '{$value['url_id']}' THEN '{$value[$field]}'\n";
						else $updateQueryGlobal .= "\t\tWHEN '{$value['url_id']}' THEN NULL\n";
						if( !in_array( $value['url_id'], $IDs ) ) $IDs[] = $value['url_id'];
					}
					$updateQueryGlobal .= "\tEND,\n\t";
				}
				$updateQueryGlobal = substr( $updateQueryGlobal, 0, strlen( $updateQueryGlobal ) - 7 )."\tEND\n";
				$updateQueryGlobal .= "WHERE `url_id` IN ('".implode( "', '", $IDs )."');\n";
				$query .= $updateQueryGlobal;
			}
			if( !empty( $updateQueryLocal ) ) {
				$updateQueryLocal .= "\tSET ";
				$IDs = array();
				foreach( $tulfields as $field ) {
					$updateQueryLocal .= "`$field` = CASE `url_id`\n";
					foreach( $tulValues as $value ) {
						if( isset( $value[$field] ) ) $updateQueryLocal .= "\t\tWHEN '{$value['url_id']}' THEN '{$value[$field]}'\n";
						else $updateQueryLocal .= "\t\tWHEN '{$value['url_id']}' THEN NULL\n";
						if( !in_array( $value['url_id'], $IDs ) ) $IDs[] = $value['url_id'];
					}
					$updateQueryLocal .= "\tEND,\n\t";
				}
				$updateQueryLocal = substr( $updateQueryLocal, 0, strlen( $updateQueryLocal ) - 7 )."\tEND\n";
				$updateQueryLocal .= "WHERE `url_id` IN ('".implode( "', '", $IDs )."') AND `pageid` = '{$this->commObject->pageid}';\n";
				$query .= $updateQueryLocal;
			}
		}
		if( !empty( $this->cachedPageResults ) ) {
			$urls = array();
			foreach( $this->cachedPageResults as $id=>$values ) {
				$values = $this->sanitizeValues( $values );
				if( !isset( $values['nodelete'] ) ) {
					$urls[] = $values['url_id'];
				}
			}
			if( !empty( $urls ) ) $deleteQuery .= "DELETE FROM `externallinks_".WIKIPEDIA."` WHERE `url_id` IN ('".implode( "', '", $urls )."') AND `pageid` = '{$this->commObject->pageid}'; ";
			$query .= $deleteQuery;
		}
		if( $query !== "" ) {
			$res = mysqli_multi_query( $this->db, $query );
			if( $res === false ) {
				echo "ERROR: ".mysqli_errno( $this->db ).": ".mysqli_error( $this->db )."\n";
			}
			while( mysqli_more_results( $this->db ) ) {
				$res = mysqli_next_result( $this->db );
				if( $res === false ) {
					echo "ERROR: ".mysqli_errno( $this->db ).": ".mysqli_error( $this->db )."\n";
				}
			}
		}
	}

	/**
	* Sets the notification status to notified
	*
	* @param mixed $tid $dbValues index to modify
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return bool True on success, false on failure/already set
	*/
	public function setNotified( $tid ) {
		if( isset( $this->dbValues[$tid] ) ) {
			if( $this->dbValues[$tid]['notified'] == 1 ) return false;
			$this->dbValues[$tid]['notified'] = 1;
			return true;
		} else return false;
	}

	/**
	 * Post details about a failed edit attempt to the log.
	 * Kills the program if database can't connect.
	 *
	 * @param string $title Page title
	 * @param string $text Wikitext to be posted
	 * @param string $failReason Reason edit failed
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return bool True on success, false on failure
	 */
	public static function logEditFailure( $title, $text, $failReason ) {
		if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
			$query = "INSERT INTO externallinks_editfaillog (`wiki`, `worker_id`, `page_title`, `attempted_text`, `failure_reason`) VALUES ('".WIKIPEDIA."', '".UNIQUEID."', '".mysqli_escape_string( $db, $title )."', '".mysqli_escape_string( $db, $text )."', '".mysqli_escape_string( $db, $failReason )."');";
			if( !mysqli_query( $db, $query ) ) {
				echo "ERROR: Failed to post edit error to DB.\n";
				return false;
			} else return true;
		} else {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
		mysqli_close( $db );
		unset( $db );
	}

	/**
	* Checks for the existence of the needed tables
	* and creates them if they don't exist.
	* Program dies on failure.
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public static function checkDB() {
		if( $db = mysqli_connect( HOST, USER, PASS, DB, PORT ) ) {
			self::createPaywallTable( $db );
			self::createGlobalELTable( $db );
			self::createELTable( $db );
			self::createLogTable( $db );
			self::createEditErrorLogTable( $db );
		} else {
			echo "Unable to connect to the database.  Exiting...";
			exit(20000);
		}
		mysqli_close( $db );
		unset( $db );
	}

	/**
	 * Create the logging table
	 * Kills the program on failure
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @param mysqli $db DB resource
	 * @return void
	 */
	public static function createLogTable( $db ) {
		if( mysqli_query( $db, "CREATE TABLE IF NOT EXISTS `externallinks_log` (
								  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(45) NOT NULL,
								  `worker_id` VARCHAR(255) NULL DEFAULT NULL,
								  `run_start` TIMESTAMP NOT NULL,
								  `run_end` TIMESTAMP NOT NULL,
								  `pages_analyzed` INT UNSIGNED NOT NULL,
								  `pages_modified` INT UNSIGNED NOT NULL,
								  `sources_analyzed` BIGINT(12) UNSIGNED NOT NULL,
								  `sources_rescued` INT UNSIGNED NOT NULL,
								  `sources_tagged` INT UNSIGNED NOT NULL,
								  `sources_archived` BIGINT(12) UNSIGNED NOT NULL,
								  PRIMARY KEY (`log_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `RUNLENGTH` (`run_end` ASC, `run_start` ASC),
								  INDEX `PANALYZED` (`pages_analyzed` ASC),
								  INDEX `PMODIFIED` (`pages_modified` ASC),
								  INDEX `SANALYZED` (`sources_analyzed` ASC),
								  INDEX `SRESCUED` (`sources_rescued` ASC),
								  INDEX `STAGGED` (`sources_tagged` ASC),
								  INDEX `SARCHIVED` (`sources_archived` ASC))
								AUTO_INCREMENT = 0;
							  ") ) echo "A log table exists\n\n";
		else {
			echo "Failed to create a log table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	* Create the paywall table
	* Kills the program on failure
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @param mysqli $db DB resource
	* @return void
	*/
	public static function createPaywallTable( $db ) {
		if( mysqli_query( $db, "CREATE TABLE IF NOT EXISTS `externallinks_paywall` (
								  `paywall_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `domain` VARCHAR(255) NOT NULL,
								  `paywall_status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
								  PRIMARY KEY (`paywall_id` ASC),
								  UNIQUE INDEX `domain_UNIQUE` (`domain` ASC),
								  INDEX `PAYWALLSTATUS` (`paywall_status` ASC));
							  ") ) echo "The paywall table exists\n\n";
		else {
			echo "Failed to create a paywall table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the edit error log table
	 * Kills the program on failure
	 *
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @param mysqli $db DB resource
	 * @return void
	 */
	public static function createEditErrorLogTable( $db ) {
		if( mysqli_query( $db, "CREATE TABLE IF NOT EXISTS `externallinks_editfaillog` (
								  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(255) NOT NULL,
								  `worker_id` VARCHAR(255) NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `page_title` VARCHAR(255) NOT NULL,
								  `attempted_text` BLOB NOT NULL,
								  `failure_reason` VARCHAR(1000) NOT NULL,
								  PRIMARY KEY (`log_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `WORKERID` (`worker_id` ASC),
								  INDEX `TIMESTAMP` (`timestamp` ASC),
								  INDEX `REASON` (`failure_reason` ASC),
								  INDEX `PAGETITLE` (`page_title` ASC));
						       ") ) echo "The edit error log table exists\n\n";
		else {
			echo "Failed to create an edit error log table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
}

	/**
	* Create the global externallinks table
	* Kills the program on failure
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @param mysqli $db DB resource
	* @return void
	*/
	public static function createGlobalELTable( $db ) {
		if( mysqli_query( $db, "CREATE TABLE IF NOT EXISTS `externallinks_global` (
								  `url_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `paywall_id` INT UNSIGNED NOT NULL,
								  `url` VARCHAR(767) NOT NULL,
								  `archive_url` BLOB NULL,
								  `has_archive` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  `live_state` TINYINT UNSIGNED NOT NULL DEFAULT '4',
								  `last_deadCheck` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
								  `archivable` TINYINT UNSIGNED NOT NULL DEFAULT '1',
								  `archived` TINYINT UNSIGNED NOT NULL DEFAULT '2',
								  `archive_failure` BLOB NULL DEFAULT NULL,
								  `access_time` TIMESTAMP NOT NULL,
								  `archive_time` TIMESTAMP NULL DEFAULT NULL,
								  `reviewed` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  PRIMARY KEY (`url_id` ASC),
								  UNIQUE INDEX `url_UNIQUE` (`url` ASC),
								  INDEX `LIVE_STATE` (`live_state` ASC),
								  INDEX `LAST_DEADCHECK` (`last_deadCheck` ASC),
								  INDEX `PAYWALLID` (`paywall_id` ASC),
								  INDEX `REVIEWED` (`reviewed` ASC));
							  ") ) echo "The global external links table exists\n\n";
		else {
			echo "Failed to create a global external links table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	* Create the wiki specific externallinks table
	* Kills the program on failure
	*
	* @access public
	* @static
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @param mysqli $db DB resource
	* @return void
	*/
	public static function createELTable( $db ) {
		if( mysqli_query( $db, "CREATE TABLE IF NOT EXISTS `externallinks_".WIKIPEDIA."` (
								  `pageid` BIGINT UNSIGNED NOT NULL,
								  `url_id` BIGINT UNSIGNED NOT NULL,
								  `notified` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  PRIMARY KEY (`pageid` ASC, `url_id` ASC),
								  INDEX `URLID` (`url_id` ASC));
							  ") ) echo "The ".WIKIPEDIA." external links table exists\n\n";
		else {
			echo "Failed to create an external links table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	* Retrieves specific information regarding a link and stores it in self::dbValues
	* Attempts to retrieve it from cache first
	*
	* @param string $link URL to fetch info about
	* @param int $tid Key ID to preserve array keys
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr

	* @return void
	*/
	public function retrieveDBValues( $link, $tid ) {
		foreach( $this->cachedPageResults as $i=>$value ) {
			if( $value['url'] == $link['url']) {
				$this->dbValues[$tid] = $value;
				$this->cachedPageResults[$i]['nodelete'] = true;
				if( isset( $this->dbValues[$tid]['nodelete'] ) ) unset( $this->dbValues[$tid]['nodelete'] );
				break;
			}
		}

		if( !isset( $this->dbValues[$tid] ) ) {
			$res = mysqli_query( $this->db, "SELECT externallinks_global.url_id, externallinks_global.paywall_id, url, archive_url, has_archive, live_state, unix_timestamp(last_deadCheck) AS last_deadCheck, archivable, archived, archive_failure, unix_timestamp(access_time) AS access_time, unix_timestamp(archive_time) AS archive_time, paywall_status, reviewed FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id = externallinks_paywall.paywall_id WHERE `url` = '".mysqli_escape_string( $this->db, $link['url'] )."';" );
			if( mysqli_num_rows( $res ) > 0 ) {
				$this->dbValues[$tid] = mysqli_fetch_assoc( $res );
				$this->dbValues[$tid]['createlocal'] = true;
			} else {
				mysqli_free_result( $res );
				$res = mysqli_query( $this->db, "SELECT paywall_id, paywall_status FROM externallinks_paywall WHERE `domain` = '".mysqli_escape_string( $this->db, parse_url( $link['url'], PHP_URL_HOST ) )."';" );
				if( mysqli_num_rows( $res ) > 0 ) {
					$this->dbValues[$tid] = mysqli_fetch_assoc( $res );
					$this->dbValues[$tid]['createlocal'] = true;
					$this->dbValues[$tid]['createglobal'] = true;
				} else {
					$this->dbValues[$tid]['createpaywall'] = true;
					$this->dbValues[$tid]['createlocal'] = true;
					$this->dbValues[$tid]['createglobal'] = true;
					$this->dbValues[$tid]['paywall_status'] = 0;
				}
				$this->dbValues[$tid]['url'] = $link['url'];
				if( $link['has_archive'] === true ) {
					$this->dbValues[$tid]['archivable'] = $this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
					$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
					$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
					$this->dbValues[$tid]['archivable'] = 1;
					$this->dbValues[$tid]['archived'] = 1;
				}
				$this->dbValues[$tid]['last_deadCheck'] = 0;
				$this->dbValues[$tid]['live_state'] = 4;
			}
			mysqli_free_result( $res );
		}

		$this->odbValues[$tid] = $this->dbValues[$tid];

		//If the link has been reviewed, lock the DB entry, otherwise, allow overwrites
		if( !isset( $this->dbValues[$tid]['reviewed'] ) || $this->dbValues[$tid]['reviewed'] == 0 ) {
			if ($link['has_archive'] === true && $link['archive_url'] != $this->dbValues[$tid]['archive_url']) {
				$this->dbValues[$tid]['archivable'] = $this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
				$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
				$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
				$this->dbValues[$tid]['archivable'] = 1;
				$this->dbValues[$tid]['archived'] = 1;
			}
		}
		//Flag the domain as a paywall if the paywall tag is found
		if( $link['tagged_paywall'] === true ) {
			$this->dbValues[$tid]['paywall_status'] = 1;
		}
		//Set the live state to 5 is it is a paywall.
		if( $this->dbValues[$tid]['paywall_status'] == 1 ) {
			$this->dbValues[$tid]['live_state'] = 5;
		}
	}

	/**
	 * Generates a log entry and posts it to the bot log on the DB
	 * @access public
	 * @static
	 * @author Maximilian Doerr (Cyberpower678)
	 * @license https://www.gnu.org/licenses/gpl.txt
	 * @copyright Copyright (c) 2016, Maximilian Doerr
	 * @return void
	 * @global $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $pagesAnalyzed, $pagesModified
	 */
	public static function generateLogReport() {
		global $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $pagesAnalyzed, $pagesModified;
		$db = mysqli_connect( HOST, USER, PASS, DB, PORT );
		$query = "INSERT INTO externallinks_log ( `wiki`, `worker_id`, `run_start`, `run_end`, `pages_analyzed`, `pages_modified`, `sources_analyzed`, `sources_rescued`, `sources_tagged`, `sources_archived` )\n";
		$query .= "VALUES ('".WIKIPEDIA."', '".UNIQUEID."', '".date( 'Y-m-d H:i:s', $runstart )."', '".date( 'Y-m-d H:i:s', $runend )."', '$pagesAnalyzed', '$pagesModified', '$linksAnalyzed', '$linksFixed', '$linksTagged', '$linksArchived');";
		mysqli_query( $db, $query );
		mysqli_close( $db );
	}

	/**
	* close the DB handle
	*
	* @access public
	* @author Maximilian Doerr (Cyberpower678)
	* @license https://www.gnu.org/licenses/gpl.txt
	* @copyright Copyright (c) 2016, Maximilian Doerr
	* @return void
	*/
	public function closeResource() {
		mysqli_close( $this->db );
		$this->commObject = null;
	}
}