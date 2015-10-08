<?php

namespace tests\unit;

use AspectMock\Test as test;
use esia\Request;


class RequestTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $url = 'http://google.com';
    protected $token = 'qwdqwdh1872hf8q0whcq8wgd0812gd102d';

    public function testConstruct()
    {
        $request = $this->prepareRequest();

        $this->assertEquals($this->url, $request->url);
        $this->assertEquals($this->token, $request->token);
    }

    public function testCall()
    {

        $request = $this->prepareRequest();

        // check if curl is not installed
        test::func('esia', 'curl_init', false);
        $response = $request->call('stub');
        $this->assertNull($response);

        // check if correct call
        test::clean();
        test::func('esia', 'curl_exec', '{}');
        $response = $request->call('stub');
        $this->assertTrue($response instanceof \stdClass);


    }

    protected function prepareRequest()
    {
        return new Request($this->url, $this->token);
    }

    protected function _before()
    {
    }

    protected function _after()
    {
    }


}