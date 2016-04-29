<?php

require_once dirname(__FILE__) . '/../IABot/checkIfDead.php';

class checkIfDeadTest extends PHPUnit_Framework_TestCase {

	public function testDeadlinksTrue() {
		$obj = new checkIfDead();
		$urls = array(
					'https://en.wikipedia.org/nothing',
					'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580',
					'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html'
				);
		$result = $obj->checkDeadlinks( $urls );
		$expected = array( true, true, true );
		$this->assertEquals( $result['results'], $expected );
	}


	public function testDeadlinksFalse() {
		$obj = new checkIfDead();
		$urls = array(
					'https://en.wikipedia.org/wiki/Main_Page',
					'https://en.wikipedia.org/w/index.php?title=Republic_of_India',
					'https://astraldynamics.com',
					'http://www.eonline.com/au/news/386489/2013-grammy-awards-winners-the-complete-list'
				);
		$result = $obj->checkDeadlinks( $urls );
		$expected = array( false, false, false, false );
		$this->assertEquals( $result['results'], $expected );
	}

}

?>
