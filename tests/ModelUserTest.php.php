<?php
/**
 * Tests the Model_User Object
 *
 * @group      ACL
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  Copyright (c) 2010 Synapse Studios
 */
Class ModelUserTest extends PHPUnit_Framework_TestCase
{
	public function providerFoo()
	{
		return array();
	}

	/**
	 * Foo
	 *
	 * @test
	 * @dataProvider providerFoo
	 */
	public function testFoo()
	{
		$this->assertSame(0, 0);
	}

}