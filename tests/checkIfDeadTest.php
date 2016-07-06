<?php

require_once dirname(__FILE__) . '/../IABot/checkIfDead.php';

class checkIfDeadTest extends PHPUnit_Framework_TestCase {

	public function testDeadlinksTrue() {
		$obj = new checkIfDead();
		$urls = array(
					'https://en.wikipedia.org/nothing',
					'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580',
					'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html',
					'http://203.221.255.21/opacs/TitleDetails?displayid=137394&collection=all&displayid=0&fieldcode=2&from=BasicSearch&genreid=0&ITEMID=$VARS.getItemId()&original=$VARS.getOriginal()&pageno=1&phrasecode=1&searchwords=Lara%20Saint%20Paul%20&status=2&subjectid=0&index='
				);
		$result = $obj->checkDeadlinks( $urls );
		$expected = array( true, true, true, true );
		$this->assertEquals( $expected, $result['results'] );
	}


	public function testDeadlinksFalse() {
		$obj = new checkIfDead();
		$urls = array(
					'https://en.wikipedia.org/wiki/Main_Page',
					'https://en.wikipedia.org/w/index.php?title=Republic_of_India',
					'https://astraldynamics.com',
					// 'http://www.eonline.com/au/news/386489/2013-grammy-awards-winners-the-complete-list', // unreliable, randomly returns 405 error
					'http://news.bbc.co.uk/2/hi/uk_news/england/coventry_warwickshire/6236900.stm',
					'http://napavalleyregister.com/news/napa-pipe-plant-loads-its-final-rail-car/article_695e3e0a-8d33-5e3b-917c-07a7545b3594.html',
					'http://content.onlinejacc.org/cgi/content/full/41/9/1633',
					'http://flysunairexpress.com/#about',
					'ftp://ftp.rsa.com/pub/pkcs/ascii/layman.asc'
				);
		$result = $obj->checkDeadlinks( $urls );
		$expected = array( false, false, false, false, false, false, false, false );
		$this->assertEquals( $expected, $result['results'] );
	}


	public function testCleanUrl() {
		$obj = new checkIfDead();

		// workaround to make private function testable
		$reflection = new \ReflectionClass( get_class( $obj ) );
		$method = $reflection->getMethod('cleanUrl');
		$method->setAccessible( true );

		$this->assertEquals( $method->invokeArgs( $obj, array( 'http://google.com?q=blah' ) ), 'google.com?q=blah' );
		$this->assertEquals( $method->invokeArgs( $obj, array( 'https://www.google.com/' ) ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, array( 'ftp://google.com/#param=1' ) ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, array( '//google.com' ) ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, array( 'www.google.www.com' ) ), 'google.www.com' );
	}

}

?>
