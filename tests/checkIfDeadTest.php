<?php

require_once dirname(__FILE__) . '/../IABot/checkIfDead.php';

class checkIfDeadTest extends PHPUnit_Framework_TestCase {

	public function testDeadLinkFalse() {
		$obj = new checkIfDead();
		$res = $obj->checkDeadlink( 'https://en.wikipedia.org/wiki/Main_Page' );
		$this->assertFalse( $res );
	}


	public function testDeadLinkTrue() {
		$obj = new checkIfDead();
		$res = $obj->checkDeadlink( 'https://en.wikipedia.org/nothing' );
		$this->assertTrue( $res );
	}


	public function testDeadLinkRedirect() {
		$obj = new checkIfDead();
		$res = $obj->checkDeadlink( 'https://en.wikipedia.org/w/index.php?title=Republic_of_India' );
		$this->assertFalse( $res );
	}

	public function testRedirectToRoot() {
		$obj = new checkIfDead();
		$res = $obj->checkDeadlink( 'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580' );
		$this->assertTrue( $res );
	}

	public function testRedirectToRootWithSubdomain() {
		$obj = new checkIfDead();
		$res = $obj->checkDeadlink( 'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html' );
		$this->assertTrue( $res );
	}

}

?>
