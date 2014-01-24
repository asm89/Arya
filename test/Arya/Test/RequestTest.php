<?php

namespace Arya\Test;

use Arya\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {

    private function getRequiredServerVars() {
        $_server = array(
            'SERVER_PROTOCOL' => 'HTTP/1.0',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index.php',
            'REQUEST_URI_PATH' => '/index.php',
            'HTTPS' => 'off',
        );

        $_get = array();
        $_post = array();
        $_files = array();
        $_cookie = array();
        $_input = NULL;

        return array($_server, $_get, $_post, $_files, $_cookie, $_input);
    }

    public function testGeneral() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();

        $_server['HTTP_MY_HEADER'] = 'my value';

        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);

        $this->assertSame('1.0', $request['SERVER_PROTOCOL']);
        $this->assertSame($_server['REQUEST_URI'], $request['REQUEST_URI']);
        $this->assertSame(parse_url($_server['REQUEST_URI'], PHP_URL_PATH), $request['REQUEST_URI']);
        $this->assertFalse($request['HTTPS']);
        $this->assertFalse($request->isEncrypted());

        $this->assertEmpty($request->getAllQueryParameters());
        $this->assertEmpty($request->getAllFormFields());
        $this->assertEmpty($request->getAllCookies());
        $this->assertEmpty($request->getBody());
        $this->assertEmpty($request->getBodyStream());

        $this->assertFalse($request->hasBody());

        $expectedVars = array(
            'SERVER_PROTOCOL' => '1.0',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index.php',
            'REQUEST_URI_PATH' => '/index.php',
            'HTTPS' => FALSE,
            'HTTP_MY_HEADER' => 'my value'
        );
        $this->assertSame($expectedVars, $request->all());

        $this->assertTrue($request->hasHeader('My-Header'));
        $this->assertSame('my value', $request->getHeader('My-Header'));
        $this->assertSame(array('MY-HEADER' => 'my value'), $request->getAllHeaders());
    }

    public function testContentLength() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_server['CONTENT_LENGTH'] = 42;
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasHeader('Content-Length'));
        $this->assertSame(42, $request->getHeader('Content-Length'));
    }

    public function testContentType() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_server['CONTENT_TYPE'] = 'multipart/form-data';
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertSame('multipart/form-data', $request->getHeader('Content-Type'));
    }
    
    /**
     * @expectedException \DomainException
     */
    public function testGetHeaderThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->hasHeader('Zanzibar'));
        $request->getHeader('Zanzibar');
    }

    /**
     * @expectedException \DomainException
     */
    public function testGetQueryParameterThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->hasQueryParameter('Zanzibar'));
        $request->getQueryParameter('Zanzibar');
    }

    public function testGetQueryParameter() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_get['myVar'] = 42;
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasQueryParameter('myVar'));
        $this->assertSame(42, $request->getQueryParameter('myVar'));
    }

    /**
     * @expectedException \DomainException
     */
    public function testGetFormFieldThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->hasFormField('Zanzibar'));
        $request->getFormField('Zanzibar');
    }

    public function testGetFormField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_post['myVar'] = 42;
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasFormField('myVar'));
        $this->assertSame(42, $request->getFormField('myVar'));
    }

    /**
     * @expectedException \DomainException
     */
    public function testGetCookieThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->hasCookie('Zanzibar'));
        $request->getCookie('Zanzibar');
    }

    public function testGetCookie() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_cookie['myVar'] = 42;
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasCookie('myVar'));
        $this->assertSame(42, $request->getCookie('myVar'));
    }

    /**
     * @expectedException \DomainException
     */
    public function testGetThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->has('ZANZIBAR'));
        $request->get('ZANZIBAR');
    }

    /**
     * @expectedException \DomainException
     */
    public function testOffsetGetThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->has('ZANZIBAR'));
        $var = $request['ZANZIBAR'];
    }

    public function testOffsetUnset() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue(isset($request['SERVER_PROTOCOL']));
        unset($request['SERVER_PROTOCOL']);
        $this->assertFalse(isset($request['SERVER_PROTOCOL']));
    }

    /**
     * @expectedException \DomainException
     */
    public function testGetFormFileThrowsOnBadField() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertFalse($request->hasFormFile('ZANZIBAR'));
        $request->getFormFile('ZANZIBAR');
    }

    public function testGetFormFile() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_files = array('test' => 42);
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertTrue($request->hasFormFile('test'));
        $this->assertEquals(42, $request->getFormFile('test'));
    }

    public function testGetAllFormFiles() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $files = array('test' => 42);
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);
        $this->assertEquals($_files, $request->getAllFormFiles());
    }

    public function testIteration() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);

        $this->assertSame('SERVER_PROTOCOL', $request->key());

        foreach ($request as $request) {
            //
        }
    }

    public function testVarAlteration() {
        $field = 'HTTP_MY_HEADER';
        $originalValue = 'my value';

        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();
        $_server[$field] = $originalValue;
        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);

        $this->assertTrue($request->has($field));
        $this->assertSame($originalValue, $request[$field]);
        $this->assertSame($originalValue, $request->get($field));
        $request[$field] = 'altered';
        $this->assertSame('altered', $request->get($field));
        $this->assertSame('altered', $request[$field]);

        $request->set($field, 'another change');
        $this->assertSame('another change', $request[$field]);
    }



    public function testBody() {
        list($_server, $_get, $_post, $_files, $_cookie, $_input) = $this->getRequiredServerVars();

        $body = 'Hello, world!';
        $encodedBody = base64_encode($body);
        $_input = fopen("data://text/plain;base64,{$encodedBody}", 'r');

        $request = new Request($_server, $_get, $_post, $_files, $_cookie, $_input);

        $this->assertTrue($request->hasBody());
        $this->assertEquals($body, $request->getBody());
        $this->assertEquals($body, stream_get_contents($request->getBodyStream()));

        // Call again to get coverage retrieving cached body data
        $this->assertEquals($body, $request->getBody());
    }
}
