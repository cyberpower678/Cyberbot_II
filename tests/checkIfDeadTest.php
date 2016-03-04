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
		$res = $obj->checkDeadlink( 'http://findarticles.com/p/articles/mi_m0FCL/is_4_30/ai_66760539/' );
		$this->assertTrue( $res );
	}

}

?>
