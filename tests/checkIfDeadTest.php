<?php

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

}

?>
