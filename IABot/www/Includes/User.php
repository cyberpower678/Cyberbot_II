<?php

/*
	Copyright (c) 2015-2018, Maximilian Doerr

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

class User {

	protected $availableFlags = [
		'alteraccesstime',
		'alterarchiveurl',
		'analyzepage',
		'blacklistdomains',
		'blacklisturls',
		'blockuser',
		'botsubmitlimit5000',
		'botsubmitlimit50000',
		'botsubmitlimitnolimit',
		'changefpreportstatus',
		'changebqjob',
		'changedomaindata',
		'changeglobalpermissions',
		'changemassbq',
		'changepermissions',
		'changeurldata',
		'configurecitationrules',
		'configuresystemsetup',
		'configurewiki',
		'deblacklistdomains',
		'deblacklisturls',
		'definearchivetemplates',
		'defineusergroups',
		'definewiki',
		'dewhitelistdomains',
		'dewhitelisturls',
		'highapilimit',
		'fpruncheckifdeadreview',
		'invokebot',
		'overridearchivevalidation',
		'overridelockout',
		'reportfp',
		'submitbotjobs',
		'togglerunpage',
		'unblockuser',
		'unblockme',
		'viewbotqueue',
		'viewfpreviewpage',
		'viewmetricspage',
		'whitelistdomains',
		'whitelisturls'
	];

	protected $oauthObject;

	protected $dbObject;

	protected $loggedOn = false;

	protected $username;

	protected $userID;

	protected $userLinkID;

	protected $wiki;

	protected $registered;

	protected $editCount;

	protected $lastLogon;

	protected $lastAction;

	protected $flags = [];

	protected $assignedFlags = [];

	protected $globalRight = [];

	protected $assignedGroups = [];

	protected $globalGroup = [];

	protected $groups = [];

	protected $addFlags = [];

	protected $removeFlags = [];

	protected $addGroups = [];

	protected $removeGroups = [];

	protected $wikiGroups = [];

	protected $wikiRights = [];

	protected $blocked = false;

	protected $blockSource;

	protected $language;

	protected $hasEmail;

	protected $email;

	protected $emailHash;

	protected $emailNewFPReport = false;

	protected $emailBlockStatus = false;

	protected $emailPermissionStatus = false;

	protected $emailFPReportFixed = false;

	protected $emailFPReportDeclined = false;

	protected $emailFPReportOpened = false;

	protected $emailBQComplete = false;

	protected $emailBQKilled = false;

	protected $emailBQSuspended = false;

	protected $emailBQUnsuspended = false;

	protected $allowAnalytics = false;

	protected $useOneTab = false;

	protected $defaultWiki = null;

	protected $defaultLanguage = null;

	protected $theme = null;

	protected $groupsNeedDefining = null;

	protected $debug = false;

	public function __construct( DB2 $db, OAuth $oauth, $user = false, $wiki = false ) {
		global $accessibleWikis;
		$this->dbObject = $db;
		$this->oauthObject = $oauth;
		if( $wiki === false ) $this->wiki = $this->oauthObject->getWiki();
		else $this->wiki = $wiki;

		$this->defineGroups();

		global $userGroups;
		if( isset( $_SESSION['setlang'] ) ) {
			$_SESSION['lang'] = $_SESSION['setlang'];
		}
		if( isset( $_SESSION['lang'] ) ) {
			$this->language = $_SESSION['lang'];
		} else $this->language = $accessibleWikis[$this->wiki]['language'];

		if( $this->oauthObject->getCSRFToken() !== false && $this->oauthObject->isLoggedOn() === false ) {
			$url = "oauthcallback.php?action=login&wiki=" . WIKIPEDIA . "&returnto=https://" .
			       $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			if( defined( 'GUIFULLAUTH' ) ) $url .= "&fullauth=1";
			header( "Location: $url" );
			exit( 0 );
		}

		if( $user === false && $this->oauthObject->isLoggedOn() === true ) {
			$this->loggedOn = true;
			$this->username = $this->oauthObject->getUsername();
			$this->userID = $this->oauthObject->getUserID();
			$this->registered = $this->oauthObject->getRegistrationEpoch();
			if( $this->registered === false ) $this->registered = 1;
			$this->lastLogon = $this->oauthObject->getAuthTimeEpoch();
			$this->editCount = $this->oauthObject->getEditCount();
			if( $this->oauthObject->isBlocked() === true ) {
				$this->blocked = true;
				$this->blockSource = "wiki";
			}
			$this->wikiGroups = $this->oauthObject->getGroupRights();
			$this->wikiRights = $this->oauthObject->getRights();
			$this->debug = isset( $_SESSION['debug'] );
			$changeUserArray = [];
			$dbUser = $this->dbObject->getUser( $this->userID, $this->wiki );
			if( !empty( $dbUser ) ) {
				if( $dbUser['user_id'] != $this->userID ) {
					var_dump( $dbUser );
					die( "User mismatch during authentication.  Process aborted..." );
				}
				if( $dbUser['wiki'] != $this->wiki ) {
					var_dump( $dbUser );
					die( "Wiki mismatch during authentication.  Process aborted..." );
				}
				if( $dbUser['user_name'] !== $this->username ) $changeUserArray['user_name'] = $this->username;
				if( $dbUser['blocked'] == 1 ) {
					$this->blocked = true;
					$this->blockSource = "internal";
				}
				$_SESSION['user_link_id'] = $this->userLinkID = $dbUser['user_link_id'];
				$this->lastAction = strtotime( $dbUser['last_action'] );
				if( strtotime( $dbUser['last_login'] ) !== $this->lastLogon ) $changeUserArray['last_login'] =
					date( 'Y-m-d H:i:s', $this->lastLogon );
				foreach( array_merge( $dbUser['rights']['local'], $dbUser['rights']['global'] ) as $right ) {
					$this->compileFlags( $right );
					if( isset( $userGroups[$right] ) ) {
						$this->assignedGroups[] = $right;
						$this->groups[] = $right;
						if( in_array( $right, $dbUser['rights']['global'] ) ) $this->globalGroup[] = $right;
					} else {
						$this->assignedFlags[] = $right;
						if( in_array( $right, $dbUser['rights']['global'] ) ) $this->globalRight[] = $right;
					}
				}
				if( isset( $_SESSION['setlang'] ) ) {
					$changeUserArray['language'] = $_SESSION['setlang'];
				} else {
					$this->language = $dbUser['language'];
				}

				if( $dbUser['user_email'] !== null ) {
					$this->email = $dbUser['user_email'];
					$this->emailHash = $dbUser['user_email_confirm_hash'];
				}
				if( $dbUser['user_email_confirmed'] == 1 ) {
					$this->hasEmail = true;
				}
				if( $dbUser['user_email_fpreport'] == 1 ) $this->emailNewFPReport = true;
				if( $dbUser['user_email_blockstatus'] == 1 ) $this->emailBlockStatus = true;
				if( $dbUser['user_email_permissions'] == 1 ) $this->emailPermissionStatus = true;
				if( $dbUser['user_email_fpreportstatusfixed'] == 1 ) $this->emailFPReportFixed = true;
				if( $dbUser['user_email_fpreportstatusdeclined'] == 1 ) $this->emailFPReportDeclined = true;
				if( $dbUser['user_email_fpreportstatusopened'] == 1 ) $this->emailFPReportOpened = true;
				if( $dbUser['user_email_bqstatuscomplete'] == 1 ) $this->emailBQComplete = true;
				if( $dbUser['user_email_bqstatuskilled'] == 1 ) $this->emailBQKilled = true;
				if( $dbUser['user_email_bqstatussuspended'] == 1 ) $this->emailBQSuspended = true;
				if( $dbUser['user_email_bqstatusresume'] == 1 ) $this->emailBQUnsuspended = true;
				if( $dbUser['user_allow_analytics'] == 1 ) $this->allowAnalytics = true;
				if( $dbUser['user_new_tab_one_tab'] == 1 ) $this->useOneTab = true;
				if( $dbUser['user_default_wiki'] != null ) $this->defaultWiki = $dbUser['user_default_wiki'];
				if( $dbUser['user_default_language'] != null ) $this->defaultLanguage =
					$dbUser['user_default_language'];
				$this->theme = $dbUser['user_default_theme'];

				if( unserialize( $dbUser['data_cache'] ) != [
						'registration_epoch' => $this->registered,
						'editcount'          => $this->editCount,
						'wikirights'         => $this->oauthObject->getRights(),
						'wikigroups'         => $this->oauthObject->getGroupRights(),
						'blockwiki'          => $this->oauthObject->isBlocked()
					]
				) {
					$changeUserArray['data_cache'] = serialize( [
						                                            'registration_epoch' => $this->registered,
						                                            'editcount'          => $this->editCount,
						                                            'wikirights'         => $this->oauthObject->getRights(
						                                            ),
						                                            'wikigroups'         => $this->oauthObject->getGroupRights(
						                                            ),
						                                            'blockwiki'          => $this->oauthObject->isBlocked(
						                                            )
					                                            ]
					);
				}
				if( !empty( $changeUserArray ) ) $this->dbObject->changeUser( $this->userID, $this->wiki,
				                                                              $changeUserArray
				);
				$this->compileFlags();
			} else {
				$this->dbObject->createUser( $this->userID, $this->wiki, $this->username, $this->lastLogon,
				                             $this->language, serialize( [
					                                                         'registration_epoch' => $this->registered,
					                                                         'editcount'          => $this->editCount,
					                                                         'wikirights'         => $this->oauthObject->getRights(
					                                                         ),
					                                                         'wikigroups'         => $this->oauthObject->getGroupRights(
					                                                         ),
					                                                         'blockwiki'          => $this->oauthObject->isBlocked(
					                                                         )
				                                                         ]
				                             ),
					( isset( $_SESSION['user_link_id'] ) ? $_SESSION['user_link_id'] : false )
				);
				$this->compileFlags();
			}
		} else {
			$this->loggedOn = false;
			$dbUser = $this->dbObject->getUser( $user, $this->wiki );
			if( !empty( $dbUser ) ) {
				$this->userID = $dbUser['user_id'];
				$this->username = $dbUser['user_name'];
				$dataCache = unserialize( $dbUser['data_cache'] );
				$this->userLinkID = $dbUser['user_link_id'];
				if( $dataCache !== false ) {
					if( $dataCache['blockwiki'] === true ) {
						$this->blocked = true;
						$this->blockSource = "wiki";
					}
					$this->wikiGroups = $dataCache['wikigroups'];
					$this->wikiRights = $dataCache['wikirights'];
					$this->editCount = $dataCache['editcount'];
					if( $dataCache['registration_epoch'] !== false ) $this->registered =
						$dataCache['registration_epoch'];
					else $this->registered = 1;
					foreach( array_merge( $dbUser['rights']['local'], $dbUser['rights']['global'] ) as $right ) {
						$this->compileFlags( $right );
						if( isset( $userGroups[$right] ) ) {
							$this->groups[] = $right;
							$this->assignedGroups[] = $right;
							if( in_array( $right, $dbUser['rights']['global'] ) ) $this->globalGroup[] = $right;
						} else {
							$this->assignedFlags[] = $right;
							if( in_array( $right, $dbUser['rights']['global'] ) ) $this->globalRight[] = $right;
						}
					}
					$this->compileFlags();
				}
				$this->lastLogon = strtotime( $dbUser['last_login'] );
				$this->lastAction = strtotime( $dbUser['last_action'] );
				if( $dbUser['blocked'] == 1 ) {
					$this->blocked = true;
					$this->blockSource = "internal";
				}
				if( $dbUser['user_email'] !== null ) {
					$this->email = $dbUser['user_email'];
					$this->emailHash = $dbUser['user_email_confirm_hash'];
				}
				if( $dbUser['user_email_confirmed'] == 1 ) {
					$this->hasEmail = true;
				}
				if( $dbUser['user_email_fpreport'] == 1 ) $this->emailNewFPReport = true;
				if( $dbUser['user_email_blockstatus'] == 1 ) $this->emailBlockStatus = true;
				if( $dbUser['user_email_permissions'] == 1 ) $this->emailPermissionStatus = true;
				if( $dbUser['user_email_fpreportstatusfixed'] == 1 ) $this->emailFPReportFixed = true;
				if( $dbUser['user_email_fpreportstatusdeclined'] == 1 ) $this->emailFPReportDeclined = true;
				if( $dbUser['user_email_fpreportstatusopened'] == 1 ) $this->emailFPReportOpened = true;
				if( $dbUser['user_email_bqstatuscomplete'] == 1 ) $this->emailBQComplete = true;
				if( $dbUser['user_email_bqstatuskilled'] == 1 ) $this->emailBQKilled = true;
				if( $dbUser['user_email_bqstatussuspended'] == 1 ) $this->emailBQSuspended = true;
				if( $dbUser['user_email_bqstatusresume'] == 1 ) $this->emailBQUnsuspended = true;
				if( $dbUser['user_default_wiki'] != null ) $this->defaultWiki = $dbUser['user_default_wiki'];
				if( $dbUser['user_default_language'] != null ) $this->defaultLanguage =
					$dbUser['user_default_language'];
				if( strtotime( $dbUser['last_login'] ) !== $this->lastLogon ) $changeUserArray['last_login'] =
					date( 'Y-m-d H:i:s', $this->lastLogon );
			}
		}
	}

	public function defineGroups( $force = false ) {
		global $userGroups, $interfaceMaster;

		if( !is_null( $this->groupsNeedDefining ) && $force !== true ) return !$this->groupsNeedDefining;

		$this->groupsNeedDefining = false;
		$userGroups = DB::getConfiguration( "global", "interface-usergroups" );
		if( empty( $userGroups ) ) {
			$this->groupsNeedDefining = true;
			$userGroups = [
				'*' => [
					'inheritsgroups' => [],
					'inheritsflags'  => [],
					'assigngroups'   => [],
					'removegroups'   => [],
					'assignflags'    => [],
					'removeflags'    => [],
					'labelclass'     => "default",
					'autoacquire'    => [
						'registered'    => 0,
						'editcount'     => 0,
						'withwikigroup' => [],
						'withwikiright' => [],
						'anonymous'     => true
					]
				]
			];
			foreach( $interfaceMaster['inheritsgroups'] as $group ) {
				$userGroups[$group] = [
					'inheritsgroups' => [],
					'inheritsflags'  => $this->availableFlags,
					'assigngroups'   => [],
					'removegroups'   => [],
					'assignflags'    => $this->availableFlags,
					'removeflags'    => $this->availableFlags,
					'labelclass'     => "danger",
					'autoacquire'    => [
						'registered'    => 0,
						'editcount'     => 0,
						'withwikigroup' => [],
						'withwikiright' => []
					]
				];
			}

			return !$this->groupsNeedDefining;
		} else {
			$toTest = [ 'inheritsgroups', 'assigngroups', 'removegroups' ];
			foreach( $toTest as $test ) {
				foreach( $interfaceMaster[$test] as $group ) {
					if( !isset( $userGroups[$group] ) ) {
						$this->groupsNeedDefining = true;
						$userGroups = [
							'*' => [
								'inheritsgroups' => [],
								'inheritsflags'  => [],
								'assigngroups'   => [],
								'removegroups'   => [],
								'assignflags'    => [],
								'removeflags'    => [],
								'labelclass'     => "default",
								'autoacquire'    => [
									'registered'    => 0,
									'editcount'     => 0,
									'withwikigroup' => [],
									'withwikiright' => [],
									'anonymous'     => true
								]
							]
						];
						foreach( $interfaceMaster['inheritsgroups'] as $group ) {
							$userGroups[$group] = [
								'inheritsgroups' => [],
								'inheritsflags'  => $this->availableFlags,
								'assigngroups'   => [],
								'removegroups'   => [],
								'assignflags'    => $this->availableFlags,
								'removeflags'    => $this->availableFlags,
								'labelclass'     => "danger",
								'autoacquire'    => [
									'registered'    => 0,
									'editcount'     => 0,
									'withwikigroup' => [],
									'withwikiright' => []
								]
							];
						}

						return false;
					}
				}
				foreach( $userGroups as $group => $groupData ) {
					foreach( $groupData[$test] as $subgroup ) {
						if( !isset( $userGroups[$subgroup] ) ) {
							$this->groupsNeedDefining = true;
							$userGroups = [
								'*' => [
									'inheritsgroups' => [],
									'inheritsflags'  => [],
									'assigngroups'   => [],
									'removegroups'   => [],
									'assignflags'    => [],
									'removeflags'    => [],
									'labelclass'     => "default",
									'autoacquire'    => [
										'registered'    => 0,
										'editcount'     => 0,
										'withwikigroup' => [],
										'withwikiright' => [],
										'anonymous'     => true
									]
								]
							];
							foreach( $interfaceMaster['inheritsgroups'] as $group ) {
								$userGroups[$group] = [
									'inheritsgroups' => [],
									'inheritsflags'  => $this->availableFlags,
									'assigngroups'   => [],
									'removegroups'   => [],
									'assignflags'    => $this->availableFlags,
									'removeflags'    => $this->availableFlags,
									'labelclass'     => "danger",
									'autoacquire'    => [
										'registered'    => 0,
										'editcount'     => 0,
										'withwikigroup' => [],
										'withwikiright' => []
									]
								];
							}

							return false;
						}
					}
				}
			}
			foreach( $userGroups as $group => $groupData ) {
				if( $userGroups[$group]['autoacquire']['registered'] != 0 )
					$userGroups[$group]['autoacquire']['registered'] =
						strtotime( $groupData['autoacquire']['registered'] );
			}
		}

		return !$this->groupsNeedDefining;
	}

	protected function compileFlags( $flag = false, $perm = false ) {
		global $userGroups, $interfaceMaster;
		if( $flag !== false ) {
			if( isset( $userGroups[$flag] ) ) {
				if( $perm === false ) foreach( $userGroups[$flag]['inheritsflags'] as $tflag ) {
					if( !in_array( $tflag, $this->flags ) ) {
						$this->flags[] = $tflag;
					}
				}
				foreach( $userGroups[$flag]['assignflags'] as $tflag ) {
					if( !in_array( $tflag, $this->addFlags ) ) {
						$this->addFlags[] = $tflag;
					}
				}
				foreach( $userGroups[$flag]['removeflags'] as $tflag ) {
					if( !in_array( $tflag, $this->removeFlags ) ) {
						$this->removeFlags[] = $tflag;
					}
				}
				if( $perm === false ) foreach( $userGroups[$flag]['inheritsgroups'] as $tgroup ) {
					$this->compileFlags( $tgroup );
				}
				foreach( $userGroups[$flag]['assigngroups'] as $tgroup ) {
					if( !in_array( $tgroup, $this->addGroups ) ) {
						$this->addGroups[] = $tgroup;
						$this->compileFlags( $tgroup, true );
					}
				}
				foreach( $userGroups[$flag]['removegroups'] as $tgroup ) {
					if( !in_array( $tgroup, $this->removeGroups ) ) {
						$this->removeGroups[] = $tgroup;
						$this->compileFlags( $tgroup, true );
					}
				}
			} elseif( !in_array( $flag, $this->flags ) ) {
				$this->flags[] = $flag;
			}
		} else {
			foreach( $userGroups as $group => $rules ) {
				$rules = $rules['autoacquire'];
				$validateGroupAutoacquire = false;
				if( ( $rules['registered'] == 0 || $rules['registered'] >= $this->registered ) &&
				    ( $rules['editcount'] == 0 || $this->editCount >= $rules['editcount'] ) ) {
					if( count( $rules['withwikigroup'] ) > 0 ) foreach( $this->wikiGroups as $tgroup ) {
						if( in_array( $tgroup, $rules['withwikigroup'] ) ) $validateGroupAutoacquire = true;
					}
					if( count( $rules['withwikiright'] ) > 0 ) foreach( $this->wikiRights as $tflag ) {
						if( in_array( $tflag, $rules['withwikiright'] ) ) $validateGroupAutoacquire = true;
					}
					if( count( $rules['withwikigroup'] ) === 0 && count( $rules['withwikiright'] ) === 0 &&
					    ( $rules['registered'] != 0 || $rules['editcount'] != 0 ) )
						$validateGroupAutoacquire = true;
				}
				if( $validateGroupAutoacquire === true ) {
					$this->groups[] = $group;
					$this->compileFlags( $group );
				}
			}
			if( in_array( $this->username, $interfaceMaster['members'] ) ) {
				foreach( $interfaceMaster['inheritsflags'] as $tflag ) {
					if( !in_array( $tflag, $this->flags ) ) {
						$this->flags[] = $tflag;
					}
				}
				foreach( $interfaceMaster['inheritsgroups'] as $tgroup ) {
					$this->groups[] = $tgroup;
					$this->compileFlags( $tgroup );
				}
				foreach( $interfaceMaster['assigngroups'] as $tgroup ) {
					if( !in_array( $tgroup, $this->addGroups ) ) {
						$this->addGroups[] = $tgroup;
						$this->compileFlags( $tgroup, true );
					}
				}
				foreach( $interfaceMaster['removegroups'] as $tgroup ) {
					if( !in_array( $tgroup, $this->removeGroups ) ) {
						$this->removeGroups[] = $tgroup;
						$this->compileFlags( $tgroup, true );
					}
				}
				foreach( $interfaceMaster['assignflags'] as $tflag ) {
					if( !in_array( $tflag, $this->addFlags ) ) {
						$this->addFlags[] = $tflag;
					}
				}
				foreach( $interfaceMaster['removeflags'] as $tflag ) {
					if( !in_array( $tflag, $this->removeFlags ) ) {
						$this->removeFlags[] = $tflag;
					}
				}
			}
		}
		$this->groups = $this->hierarchySort( $this->groups );
		asort( $this->flags );
		asort( $this->assignedFlags );
	}

	public function hierarchySort( $groupArray ) {
		global $userGroups;
		$returnArray = [];
		foreach( $userGroups as $group => $junk ) {
			if( in_array( $group, $groupArray ) ) $returnArray[] = $group;
		}

		return $returnArray;
	}

	public static function getGroupFlags( $group, $perm = false ) {
		global $userGroups;
		$hasFlags = [];
		$addGroups = [];
		$removeGroups = [];
		$addFlags = [];
		$removeFlags = [];
		if( isset( $userGroups[$group] ) ) {
			if( $perm === false ) foreach( $userGroups[$group]['inheritsflags'] as $tflag ) {
				if( !in_array( $tflag, $hasFlags ) ) {
					$hasFlags[] = $tflag;
				}
			}
			foreach( $userGroups[$group]['assignflags'] as $tflag ) {
				if( !in_array( $tflag, $addFlags ) ) {
					$addFlags[] = $tflag;
				}
			}
			foreach( $userGroups[$group]['removeflags'] as $tflag ) {
				if( !in_array( $tflag, $removeFlags ) ) {
					$removeFlags[] = $tflag;
				}
			}
			if( $perm === false ) foreach( $userGroups[$group]['inheritsgroups'] as $tgroup ) {
				$subArray = self::getGroupFlags( $tgroup );
				$hasFlags = array_merge( array_diff( $hasFlags, $subArray['hasflags'] ), $subArray['hasflags'] );
				$addGroups = array_merge( array_diff( $addGroups, $subArray['addgroups'] ), $subArray['addgroups'] );
				$removeGroups =
					array_merge( array_diff( $removeGroups, $subArray['removegroups'] ), $subArray['removegroups'] );
				$addFlags = array_merge( array_diff( $addFlags, $subArray['addflags'] ), $subArray['addflags'] );
				$removeFlags =
					array_merge( array_diff( $removeFlags, $subArray['removeflags'] ), $subArray['removeflags'] );
			}
			foreach( $userGroups[$group]['assigngroups'] as $tgroup ) {
				if( !in_array( $tgroup, $addGroups ) ) {
					$addGroups[] = $tgroup;
					$subArray = self::getGroupFlags( $tgroup, true );
					$hasFlags = array_merge( array_diff( $hasFlags, $subArray['hasflags'] ), $subArray['hasflags'] );
					$addGroups =
						array_merge( array_diff( $addGroups, $subArray['addgroups'] ), $subArray['addgroups'] );
					$removeGroups =
						array_merge( array_diff( $removeGroups, $subArray['removegroups'] ), $subArray['removegroups']
						);
					$addFlags = array_merge( array_diff( $addFlags, $subArray['addflags'] ), $subArray['addflags'] );
					$removeFlags =
						array_merge( array_diff( $removeFlags, $subArray['removeflags'] ), $subArray['removeflags'] );
				}
			}
			foreach( $userGroups[$group]['removegroups'] as $tgroup ) {
				if( !in_array( $tgroup, $removeGroups ) ) {
					$removeGroups[] = $tgroup;
					$subArray = self::getGroupFlags( $tgroup, true );
					$hasFlags = array_merge( array_diff( $hasFlags, $subArray['hasflags'] ), $subArray['hasflags'] );
					$addGroups =
						array_merge( array_diff( $addGroups, $subArray['addgroups'] ), $subArray['addgroups'] );
					$removeGroups =
						array_merge( array_diff( $removeGroups, $subArray['removegroups'] ), $subArray['removegroups']
						);
					$addFlags = array_merge( array_diff( $addFlags, $subArray['addflags'] ), $subArray['addflags'] );
					$removeFlags =
						array_merge( array_diff( $removeFlags, $subArray['removeflags'] ), $subArray['removeflags'] );
				}
			}
		} else {
			return false;
		}
		asort( $hasFlags );
		asort( $addFlags );
		asort( $removeFlags );
		asort( $addGroups );
		asort( $removeGroups );

		return [
			'hasflags' => $hasFlags, 'addgroups' => $addGroups, 'removegroups' => $removeGroups,
			'addflags' => $addFlags, 'removeflags' => $removeFlags
		];
	}

	public function validatePermission( $flag, $assigned = false, $global = false ) {
		if( $assigned === false ) return in_array( $flag, $this->flags );
		elseif( $global === false ) return in_array( $flag, $this->assignedFlags );
		else return in_array( $flag, $this->globalRight );
	}

	public function validateGroup( $group, $assigned = false, $global = false ) {
		if( $assigned === false ) return in_array( $group, $this->groups );
		elseif( $global === false ) return in_array( $group, $this->assignedGroups );
		else return in_array( $group, $this->globalGroup );
	}

	public function getFlags() {
		return $this->flags;
	}

	public function getGroups() {
		return $this->groups;
	}

	public function getAllFlags() {
		return $this->availableFlags;
	}

	public function isBlocked() {
		return $this->blocked;
	}

	public function getBlockSource() {
		return $this->blockSource;
	}

	public function block() {
		$this->blocked = true;
		$this->blockSource = "internal";

		return $this->dbObject->changeUser( $this->userID, $this->wiki, [ 'blocked' => 1 ] );
	}

	public function unblock() {
		$this->blocked = false;

		return $this->dbObject->changeUser( $this->userID, $this->wiki, [ 'blocked' => 0 ] );
	}

	public function getLastAction() {
		return $this->lastAction;
	}

	public function setLastAction( $epoch ) {
		$this->lastAction = $epoch;

		return $this->dbObject->changeUser( $this->userID, $this->wiki,
		                                    [ 'last_action' => date( 'Y-m-d H:i:s', $epoch ) ]
		);
	}

	public function getLanguage() {
		return $this->language;
	}

	public function getUsername() {
		return $this->username;
	}

	public function getUserID() {
		return $this->userID;
	}

	public function getUserLinkID() {
		return $this->userLinkID;
	}

	public function getAuthTimeEpoch() {
		return $this->lastLogon;
	}

	public function getAddableFlags() {
		return $this->addFlags;
	}

	public function getRemovableFlags() {
		return $this->removeFlags;
	}

	public function getAddableGroups() {
		return $this->addGroups;
	}

	public function getRemovableGroups() {
		return $this->removeGroups;
	}

	public function getAssignedPermissions() {
		return array_merge( $this->assignedFlags, $this->assignedGroups );
	}

	public function getGlobalPermissions() {
		return array_merge( $this->globalGroup, $this->globalRight );
	}

	public function hasEmail() {
		return $this->hasEmail;
	}

	public function getEmail() {
		return $this->email;
	}

	public function validateEmailHash( $hash ) {
		return $this->emailHash == $hash;
	}

	public function getEmailNewFPReport() {
		return $this->emailNewFPReport;
	}

	public function getEmailBlockStatus() {
		return $this->emailBlockStatus;
	}

	public function getEmailPermissionsStatus() {
		return $this->emailPermissionStatus;
	}

	public function getEmailBQComplete() {
		return $this->emailBQComplete;
	}

	public function getEmailBQKilled() {
		return $this->emailBQKilled;
	}

	public function getEmailBQSuspended() {
		return $this->emailBQSuspended;
	}

	public function getEmailBQUnsuspended() {
		return $this->emailBQUnsuspended;
	}

	public function getEmailFPFixed() {
		return $this->emailFPReportFixed;
	}

	public function getEmailFPDeclined() {
		return $this->emailFPReportDeclined;
	}

	public function getEmailFPOpened() {
		return $this->emailFPReportOpened;
	}

	public function getAnalyticsPermission() {
		return $this->allowAnalytics;
	}

	public function useMultipleTabs() {
		return !$this->useOneTab;
	}

	public function getDefaultWiki() {
		return $this->defaultWiki;
	}

	public function getDefaultLanguage() {
		return $this->defaultLanguage;
	}

	public function getWiki() {
		return $this->wiki;
	}

	public function getTheme() {
		return $this->theme;
	}

	public function debugEnabled() {
		return $this->debug;
	}

	public function setTheme( $theme ) {
		switch( $theme ) {
			case "cerulean":
			case "classic":
			case "cosmo":
			case "cyborg":
			case "darkly":
			case "flatly":
			case "journal":
			case "lumen":
			case "paper":
			case "readable":
			case "sandstone":
			case "simplex":
			case "slate":
			case "solar":
			case "spacelab":
			case "superhero":
			case "united":
			case "yeti":
				return $this->theme = $theme;
			default:
				return $this->theme = null;
		}
	}
}