<?php

use Arya\Application,
    Arya\Request,
    Arya\Response,
    Arya\Sessions\Session;

function testFunctionTarget() {
    return 'test';
}

$lambda = function() {
    return 'test';
};

class TestStaticClass {
    public static function get() {
        return 'test';
    }
}

class TestDependency {
    public $var = 1;
}

class TestInstanceMethod {
    private $dep1;
    public function __construct(TestDependency $dep1) {
        $dep1->var++;
        $this->dep1 = $dep1;
    }
    public function get(TestDependency $dep2) {
        return $this->dep1->var . ' | ' . $dep2->var;
    }
}

function testRouteArgsFunctionTarget($request, $arg1, $arg2, $arg3) {
    $reqArg1 = $request['ROUTE_ARGS']['arg1'];
    $reqArg2 = $request['ROUTE_ARGS']['arg2'];
    $reqArg3 = $request['ROUTE_ARGS']['arg3'];

    return sprintf("%s | %s | %d | %s | %s | %d", $arg1, $arg2, $arg3, $reqArg1, $reqArg2, $reqArg3);
}

function testGeneratesOutputFunctionTarget() {
    echo "this output will result in a 500 Internal Server Error";

    return "test (you'll never see this)";
}

function testComplexResponseFunctionTarget(Request $request) {
    $response = new Response;

    return $response->setStatus(234)
        ->setReasonPhrase('Custom Reason')
        ->setHeader('X-My-Header', '1')
        ->addHeader('X-My-Header', '2')
        ->setBody('zanzibar!')
    ;
}

function beforeAll($request) {
    $request['BEFORE_ALL_TEST'] = 42;
}

function afterAll($request, $response) {
    $response->addHeader('X-Before-Test', $request['BEFORE_ALL_TEST']);
}

function beforeSpecific($request) {
    $request['BEFORE_ALL_SPECIFIC_TEST'] = 'test';
}

function afterSpecific($request, $response) {
    $response->addHeader('X-Before-Specific-Test', $request['BEFORE_ALL_SPECIFIC_TEST']);
}

function afterWithUriFilter($response) {
    $response->setHeader('X-Zanzibar', 'zanzibar!');
}

function fatalFunction() {
    $obj->nonexistent();
}

function exceptionFunction() {
    throw new \Exception('test');
}





$app = (new Application)
    ->route('GET', '/test-function-target', 'testFunctionTarget')
    ->route('GET', '/test-lambda-target', $lambda)
    ->route('GET', '/test-static-target', 'TestStaticClass::get')
    ->route('GET', '/test-instance-method-target', 'TestInstanceMethod::get')
    ->route('GET', '/$arg1/$arg2/$#arg3', 'testRouteArgsFunctionTarget')
    ->route('GET', '/generates-output', 'testGeneratesOutputFunctionTarget')
    ->route('GET', '/complex-response', 'testComplexResponseFunctionTarget')
    ->route('GET', '/zanzibar/test', 'testFunctionTarget')
    ->route('GET', '/fatal', 'fatalFunction')
    ->route('GET', '/exception', 'exceptionFunction')
    ->route('GET', '/test-route-specific-middleware', 'testFunctionTarget')
        ->beforeRoute('beforeSpecific')
        ->afterRoute('afterSpecific')
    ->before('beforeAll')
    ->after('afterAll')
    ->after('afterWithUriFilter', $options = array('uri' => '/zanzibar/*'))
    ->run()
;
