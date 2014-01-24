<?php

use Artax\Client, Artax\Request;

namespace Arya\Test;

class IntegrationTest extends \PHPUnit_Framework_TestCase {

    private static $client;
    private static $baseUri;

    /**
     * @requires PHP 5.4
     */
    public static function setupBeforeClass() {
        self::$client = new \Artax\Client;
        self::$baseUri = sprintf('http://%s:%d', WEB_SERVER_HOST, WEB_SERVER_PORT);
    }

    /**
     * @requires PHP 5.4
     */
    public function testFunctionTarget() {
        $uri = self::$baseUri . '/test-function-target';
        $response = self::$client->request($uri);
        $this->assertEquals('test', $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function testLambdaTarget() {
        $uri = self::$baseUri . '/test-lambda-target';
        $response = self::$client->request($uri);
        $this->assertEquals('test', $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function testStaticTarget() {
        $uri = self::$baseUri . '/test-static-target';
        $response = self::$client->request($uri);
        $this->assertEquals('test', $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function testInstanceMethodTarget() {
        $uri = self::$baseUri . '/test-instance-method-target';
        $response = self::$client->request($uri);
        $this->assertEquals('2 | 1', $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function testRouteArgs() {
        $uri = self::$baseUri . '/arg1/arg2/42';
        $response = self::$client->request($uri);
        $this->assertEquals('arg1 | arg2 | 42 | arg1 | arg2 | 42', $response->getBody());
    }

    /**
     * @requires PHP 5.4
     */
    public function test404OnUnmatchedNumericRouteArg() {
        $uri = self::$baseUri . '/arg1/arg2/should-be-numeric-but-isnt';
        $response = self::$client->request($uri);
        $this->assertEquals(404, $response->getStatus());
    }

    /**
     * @requires PHP 5.4
     */
    public function test404OnUnmatchedRoute() {
        $uri = self::$baseUri . '/some-route-that-clearly-doesnt-exist';
        $response = self::$client->request($uri);
        $this->assertEquals(404, $response->getStatus());
    }

    /**
     * @requires PHP 5.4
     */
    public function test500OnTargetOutput() {
        $uri = self::$baseUri . '/generates-output';
        $response = self::$client->request($uri);
        $this->assertEquals(500, $response->getStatus());
    }

    /**
     * @requires PHP 5.4
     */
    public function testComplexResponse() {
        $uri = self::$baseUri . '/complex-response';
        $response = self::$client->request($uri);
        $this->assertEquals(234, $response->getStatus());
        $this->assertEquals('Custom Reason', $response->getReason());
        $this->assertTrue($response->hasHeader('X-My-Header'));
        $myHeaders = $response->getHeader('X-My-Header');
        $this->assertEquals(2, count($myHeaders));
        list($header1, $header2) = $myHeaders;
        $this->assertEquals(1, $header1);
        $this->assertEquals(2, $header2);
        $this->assertEquals('zanzibar!', $response->getBody());

    }


}





































