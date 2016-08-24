<?php

require_once dirname( __FILE__ ) . '/../../IABot/Parser/parse.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

class parseTest extends PHPUnit_Framework_TestCase {

	function __construct() {
		parent::__construct();
		$api = new API();
		$this->parser = $this->getMockForAbstractClass( Parser::class, [ $api ] );
		$this->reflection = new \ReflectionClass( get_class( $this->parser ) );
	}

	public function testFetchTemplateRegex() {
		$placeholder = '{{{{templates}}}}';
		$method = $this->getPrivateMethod( 'fetchTemplateRegex' );
		$regexOptional = $this->getPrivateProperty( 'templateRegexOptional' );
		$regexMandatory = $this->getPrivateProperty( 'templateRegexMandatory' );

		$escapedTemplateArray = [ '{{cite web', 'param=foo', 'param2=bar}}' ];
		$regex = $method->invokeArgs( $this->parser, [ $escapedTemplateArray ] );
		$testRegex = str_replace( $placeholder, '{{cite web|param=foo|param2=bar}}', $regexOptional );
		$this->assertEquals( $regex, $testRegex );

		$escapedTemplateArray = [ '{{cite web', '', '\nparam=foo', 'param2=bar}}' ];
		$regex = $method->invokeArgs( $this->parser, [ $escapedTemplateArray, false ] );
		$testRegex = str_replace( $placeholder, '{{cite web||\nparam=foo|param2=bar}}', $regexMandatory );
		$this->assertEquals( $regex, $testRegex );
	}

	public function testFilterText() {
		$method = $this->getPrivateMethod( 'filterText' );

		$testCases = [
			'Foo<!-- example <!-- -->B<pre>blah</pre >ar' => 'FooBar',
			'< code>  <NOWIKI>something\n</nowiki >blah</code><!-- hello ---->' => '',
			'<nowiki>[[no]]<nowiki></nowiki>[[yes]]<nowiki>[[no]]</nowiki>[[yes]]</nowiki>[[yes]]' => '[[yes]][[yes]]</nowiki>[[yes]]'
		];

		foreach( $testCases as $testCase => $expected ) {
			$text = $method->invokeArgs( $this->parser, [ $testCase ] );
			$this->assertEquals( $expected, $text );
		}
	}

	public function testGetTemplateParameters() {
		$testCases = [
			'url=https://google.com|title=Foo bar|access-date=2016-07-01|7=7th param' => [
				'url' => 'https://google.com',
				'title' => 'Foo bar',
				'access-date' => '2016-07-01',
				'7' => '7th param'
			],
			'url={{URL|example.com}}|blah\n|publisher=[[The Onion|Onion]]| title=Hello{{!}}World' => [
				'url' => '{{URL|example.com}}',
				'2' => 'blah\n',
				'publisher' => '[[The Onion|Onion]]',
				'title' => 'Hello{{!}}World'
			]
		];

		foreach( $testCases as $testCase => $expected ) {
			$params = $this->parser->getTemplateParameters( $testCase );
			$this->assertEquals( $expected, $params );
		}
	}

	// helpers to make private methods and properties accessible
	private function getPrivateMethod( $methodName ) {
		$method = $this->reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method;
	}

	private function getPrivateProperty( $propertyName ) {
		$property = $this->reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property->getValue( $this->parser );
	}
}

/**
 * Dummy class to pass to Parser
 */
class API {
}
