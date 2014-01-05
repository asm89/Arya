<?php

namespace Arya\Test;

use Arya\AppRouteProxy;

class AppRouteProxyTest extends \PHPUnit_Framework_TestCase {

    function testMagicCallDelegatesToApplication() {
        $app = $this->getMock('Arya\Application');
        $app->expects($this->once())->method('execute');
        $arp = new AppRouteProxy($app, 'GET', '/some-uri');
        $arp->execute();
    }

    /**
     * @expectedException \BadMethodCallException
     */
    function testMagicCallThrowsOnUnknownApplicationMethod() {
        $app = $this->getMock('Arya\Application');
        $arp = new AppRouteProxy($app, 'GET', '/some-uri');
        $arp->nonexistentMethod();
    }

    /**
     * @dataProvider provideMiddleware
     */
    function testBeforeRouteDelegatesToApplicationBeforeMethod($middleware, $method, $uri, $priority) {
        $app = $this->getMock('Arya\Application');
        $arp = new AppRouteProxy($app, $method, $uri);
        $app->expects($this->once())
            ->method('before')
            ->with($middleware, array(
                'method' => $method,
                'uri' => $uri,
                'priority' => $priority
            ))
        ;
        $returnValue = $arp->beforeRoute($middleware, $priority);
        $this->assertInstanceOf('Arya\AppRouteProxy', $returnValue);
    }

    /**
     * @dataProvider provideMiddleware
     */
    function testAfterRouteDelegatesToApplicationAfterMethod($middleware, $method, $uri, $priority) {
        $app = $this->getMock('Arya\Application');
        $arp = new AppRouteProxy($app, $method, $uri);
        $app->expects($this->once())
            ->method('after')
            ->with($middleware, array(
                'method' => $method,
                'uri' => $uri,
                'priority' => $priority
            ))
        ;
        $returnValue = $arp->afterRoute($middleware, $priority);
        $this->assertInstanceOf('Arya\AppRouteProxy', $returnValue);
    }

    /**
     * @dataProvider provideMiddleware
     */
    function testFinalizeRouteDelegatesToApplicationFinalizeMethod($middleware, $method, $uri, $priority) {
        $app = $this->getMock('Arya\Application');
        $arp = new AppRouteProxy($app, $method, $uri);
        $app->expects($this->once())
            ->method('finalize')
            ->with($middleware, array(
                'method' => $method,
                'uri' => $uri,
                'priority' => $priority
            ))
        ;
        $returnValue = $arp->finalizeRoute($middleware, $priority);
        $this->assertInstanceOf('Arya\AppRouteProxy', $returnValue);
    }

    function provideMiddleware() {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $middleware = 'test';
        $method = 'GET';
        $uri = '/uri';
        $priority = 42;

        $return[] = array($middleware, $method, $uri, $priority);

        // 1 -------------------------------------------------------------------------------------->

        $middleware = 'some_other_middleware';
        $method = 'POST';
        $uri = '/some-uri';
        $priority = 99;

        $return[] = array($middleware, $method, $uri, $priority);

        // x -------------------------------------------------------------------------------------->

        return $return;
    }

}