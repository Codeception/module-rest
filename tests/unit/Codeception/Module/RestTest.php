<?php

declare(strict_types=1);

use Codeception\Configuration;
use Codeception\Exception\ModuleException;
use Codeception\Lib\Interfaces\API;
use Codeception\Lib\Interfaces\ConflictsWithModule;
use Codeception\Lib\ModuleContainer;
use Codeception\Module\REST;
use Codeception\Module\UniversalFramework;
use Codeception\Stub;
use Codeception\Test\Unit;
use Codeception\Util\Maybe;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\BrowserKit\Request as SymfonyRequest;
use Symfony\Component\BrowserKit\Response as SymfonyResponse;

/**
 * Class RestTest
 * @group appveyor
 */
final class RestTest extends Unit
{
    protected REST $module;

    protected function _setUp()
    {
        $index = Configuration::dataDir() . '/rest/index.php';

        $container = Stub::make(ModuleContainer::class);
        $connectionModule = new UniversalFramework($container, ['index' => $index]);
        $connectionModule->_initialize();

        $this->module = Stub::make(REST::class);
        $this->module->_inject($connectionModule);
        $this->module->_initialize();
        $this->module->_before(Stub::makeEmpty(\Codeception\Test\Test::class));

        $this->module->client->setServerParameters([
            'SCRIPT_FILENAME' => 'index.php',
            'SCRIPT_NAME' => 'index',
            'SERVER_NAME' => 'localhost',
            'SERVER_PROTOCOL' => 'http'
        ]);
    }

    public function testConflictsWithAPI()
    {
        $this->assertInstanceOf(ConflictsWithModule::class, $this->module);
        $this->assertSame(API::class, $this->module->_conflicts());
    }

    private function setStubResponse($response)
    {
        $connectionModule = Stub::make(UniversalFramework::class, ['_getResponseContent' => $response]);
        $this->module->_inject($connectionModule);
        $this->module->_initialize();
        $this->module->_before(Stub::makeEmpty(\Codeception\Test\Test::class));
    }

    public function testBeforeHookResetsVariables()
    {
        $this->module->haveHttpHeader('Origin', 'http://www.example.com');
        $this->module->sendGET('/rest/user/');

        $server = $this->module->client->getInternalRequest()->getServer();
        $this->assertArrayHasKey('HTTP_ORIGIN', $server);
        $this->module->_before(Stub::makeEmpty(\Codeception\Test\Test::class));
        $this->module->sendGET('/rest/user/');

        $server = $this->module->client->getInternalRequest()->getServer();
        $this->assertArrayNotHasKey('HTTP_ORIGIN', $server);
    }

    public function testGet()
    {
        $response = $this->module->sendGET('/rest/user/');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContains('davert');
        $this->module->seeResponseContainsJson(['name' => 'davert']);
        $this->module->seeResponseCodeIs(200);
        $this->module->dontSeeResponseCodeIs(404);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('"name":"davert"', $response);
    }

    public function testPost()
    {
        $response = $this->module->sendPOST('/rest/user/', ['name' => 'john']);
        $this->module->seeResponseContains('john');
        $this->module->seeResponseContainsJson(['name' => 'john']);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('"name":"john"', $response);
    }

    public function testPut()
    {
        $response = $this->module->sendPUT('/rest/user/', ['name' => 'laura']);
        $this->module->seeResponseContains('davert@mail.ua');
        $this->module->seeResponseContainsJson(['name' => 'laura']);
        $this->module->dontSeeResponseContainsJson(['name' => 'john']);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('"name":"laura"', $response);

    }

    public function testSend()
    {
        $response = $this->module->send('POST', '/rest/user/', ['name' => 'john']);
        $this->module->seeResponseContains('john');
        $this->module->seeResponseContainsJson(['name' => 'john']);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('"name":"john"', $response);
    }

    public function testGrabDataFromResponseByJsonPath()
    {
        $this->module->sendGET('/rest/user/');
        // simple assoc array
        $this->assertSame(['davert@mail.ua'], $this->module->grabDataFromResponseByJsonPath('$.email'));
        // nested assoc array
        $this->assertSame(['Kyiv'], $this->module->grabDataFromResponseByJsonPath('$.address.city'));
        // nested index array
        $this->assertSame(['DavertMik'], $this->module->grabDataFromResponseByJsonPath('$.aliases[0]'));
        // empty if data not found
        $this->assertSame([], $this->module->grabDataFromResponseByJsonPath('$.address.street'));
    }

    public function testValidJson()
    {
        $this->setStubResponse('{"xxx": "yyy"}');
        $this->module->seeResponseIsJson();
        $this->setStubResponse('{"xxx": "yyy", "zzz": ["a","b"]}');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseEquals('{"xxx": "yyy", "zzz": ["a","b"]}');
    }

    public function testInvalidJson()
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('{xxx = yyy}');
        $this->module->seeResponseIsJson();
    }

    public function testValidXml()
    {
        $this->setStubResponse('<xml></xml>');
        $this->module->seeResponseIsXml();
        $this->setStubResponse('<xml><name>John</name></xml>');
        $this->module->seeResponseIsXml();
        $this->module->seeResponseEquals('<xml><name>John</name></xml>');
    }

    public function testXmlResponseEquals()
    {
        $this->setStubResponse('<xml></xml>');
        $this->module->seeResponseIsXml();
        $this->module->seeXmlResponseEquals('<xml></xml>');
    }

    public function testInvalidXml()
    {
        $this->expectException(ExpectationFailedException::class);
        $this->setStubResponse('<xml><name>John</surname></xml>');
        $this->module->seeResponseIsXml();
    }

    public function testSeeInJsonResponse()
    {
        $this->setStubResponse(
            '{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}, "labels": null}}'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContainsJson(['name' => 'Davert']);
        $this->module->seeResponseContainsJson(['user' => ['name' => 'Davert']]);
        $this->module->seeResponseContainsJson(['ticket' => ['title' => 'Bug should be fixed']]);
        $this->module->seeResponseContainsJson(['ticket' => ['user' => ['name' => 'Davert']]]);
        $this->module->seeResponseContainsJson(['ticket' => ['labels' => null]]);
    }

    public function testSeeInJsonCollection()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":"42","tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseContainsJson(['tags' => ['web-dev', 'java']]);
        $this->module->seeResponseContainsJson(['user' => 'John Doe', 'age' => 27]);
        $this->module->seeResponseContainsJson([['user' => 'John Doe', 'age' => 27]]);
        $this->module->seeResponseContainsJson(
            [['user' => 'Blacknoir', 'age' => 42], ['user' => 'John Doe', 'age' => "27"]]
        );
    }

    public function testArrayJson()
    {
        $this->setStubResponse(
            '[{"id":1,"title": "Bug should be fixed"},{"title": "Feature should be implemented","id":2}]'
        );
        $this->module->seeResponseContainsJson(['id' => 1]);
    }

    public function testDontSeeInJson()
    {
        $this->setStubResponse('{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}}}');
        $this->module->seeResponseIsJson();
        $this->module->dontSeeResponseContainsJson(['name' => 'Davet']);
        $this->module->dontSeeResponseContainsJson(['user' => ['name' => 'Davet']]);
        $this->module->dontSeeResponseContainsJson(['user' => ['title' => 'Bug should be fixed']]);
    }

    public function testApplicationJsonIncludesJsonAsContent()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendPOST('/', ['name' => 'john']);
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertContains('application/json', $request->getServer());
        $server = $request->getServer();
        $this->assertSame('application/json', $server['HTTP_CONTENT_TYPE']);
        $this->assertJson($request->getContent());
        $this->assertEmpty($request->getParameters());
    }

    public function testApplicationJsonIncludesObjectSerialized()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendPOST('/', new JsonSerializedItem());
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertContains('application/json', $request->getServer());
        $this->assertJson($request->getContent());
    }

    /**
     * @dataProvider requestBodyAwareMethods
     */
    public function testRequestBodyIsSentAsJsonForThisMethod(string $method)
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->send($method, '/', ['name' => 'john']);
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertSame(json_encode(['name' => 'john'], JSON_THROW_ON_ERROR), $request->getContent());
    }

    /**
     * @dataProvider requestBodyAwareMethods
     */
    public function testRequestBodyIsSentUrlEncodedForThisMethod(string $method)
    {
        $this->module->send($method, '/', ['name' => 'john']);
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertSame(http_build_query(['name' => 'john']), $request->getContent());
    }

    /**
     * @return array<string, array<string>>
     */
    public function requestBodyAwareMethods(): array
    {
        return [
            'POST'   => ['POST'],
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    /**
     * @dataProvider queryParamsAwareMethods
     */
    public function testJsonRequestBodyIsNotSentForThisMethod(string $method)
    {
        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->send($method, '/', ['name' => 'john']);
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertNull($request->getContent());
        $this->assertContains('john', $request->getParameters());
    }


    /**
     * @dataProvider queryParamsAwareMethods
     */
    public function testUrlEncodedRequestBodyIsNotSentForThisMethod(string $method)
    {
        $this->module->send($method, '/', ['name' => 'john']);
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertNull($request->getContent());
        $this->assertContains('john', $request->getParameters());
    }

    public function queryParamsAwareMethods(): array
    {
        return [
            'GET'     => ['GET'],
            'HEAD'    => ['HEAD'],
            'OPTIONS' => ['OPTIONS'],
        ];
    }

    /**
     * @dataProvider queryParamsAwareMethods
     */
    public function testThrowsExceptionIfParametersIsString(string $method)
    {
        $this->expectExceptionMessage($method . ' parameters must be passed in array format');
        $this->module->send($method, '/', 'string');
    }

    /**
     * @param array|object $parameters
     * @dataProvider invalidParameterTypes
     */
    public function testThrowsExceptionIfParametersIsOfUnexpectedType($parameters)
    {
        $this->expectExceptionMessage('POST parameters must be array, string or object implementing JsonSerializable interface');
        $this->module->sendPOST('/', $parameters);
    }

    public function invalidParameterTypes(): array
    {
        return [
            'boolean'  => [true],
            'resource' => [STDERR],
            'integer'  => [5],
            'float'    => [6.6],
            'object'   => [new \LogicException('test')],
        ];
    }

    public function testThrowsExceptionIfUrlIsNotString()
    {
        $this->expectException(TypeError::class);
        $this->module->sendPOST([1]);
    }

    public function testThrowsExceptionIfParametersIsJsonSerializableButContentTypeIsNotSet()
    {
        $this->expectExceptionMessage("parameters is JsonSerializable object, but Content-Type header is not set to application/json");
        $parameters = new Maybe(['foo']);
        $this->module->sendPOST('/', $parameters);
    }

    public function testDoesntThrowExceptionIfParametersIsJsonSerializableAndContentTypeIsSet()
    {
        $parameters = new Maybe(['foo']);

        $this->module->haveHttpHeader('Content-Type', 'application/json');
        $this->module->sendPOST('/', $parameters);
        $this->assertTrue(true, 'this test fails by throwing exception');
    }

    public function testUrlIsFull()
    {
        $this->module->sendGET('/api/v1/users');
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertSame('http://localhost/api/v1/users', $request->getUri());
    }

    public function testSeeHeaders()
    {
        $response = new SymfonyResponse("", 200, [
            'Cache-Control' => ['no-cache', 'no-store'],
            'Content_Language' => 'en-US'
        ]);
        $this->module->client->mockResponse($response);
        $this->module->sendGET('/');
        $this->module->seeHttpHeader('Cache-Control');
        $this->module->seeHttpHeader('content_language', 'en-US');
        $this->module->seeHttpHeader('Content-Language', 'en-US');
        $this->module->dontSeeHttpHeader('Content-Language', 'en-RU');
        $this->module->dontSeeHttpHeader('Content-Language1');
        $this->module->seeHttpHeaderOnce('Content-Language');
        $this->assertSame('en-US', $this->module->grabHttpHeader('Content-Language'));
        $this->assertSame('no-cache', $this->module->grabHttpHeader('Cache-Control'));
        $this->assertSame(['no-cache', 'no-store'], $this->module->grabHttpHeader('Cache-Control', false));
    }

    public function testSeeHeadersOnce()
    {
        $this->shouldFail();
        $response = new SymfonyResponse("", 200, [
            'Cache-Control' => ['no-cache', 'no-store'],
        ]);
        $this->module->client->mockResponse($response);
        $this->module->sendGET('/');
        $this->module->seeHttpHeaderOnce('Cache-Control');
    }

    public function testSeeResponseJsonMatchesXpath()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseJsonMatchesXpath('//user');
    }

    public function testSeeResponseJsonMatchesJsonPath()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->seeResponseJsonMatchesJsonPath('$[*].user');
        $this->module->seeResponseJsonMatchesJsonPath('$[1].tags');
    }


    public function testDontSeeResponseJsonMatchesJsonPath()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->dontSeeResponseJsonMatchesJsonPath('$[*].profile');
    }

    public function testDontSeeResponseJsonMatchesXpath()
    {
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->dontSeeResponseJsonMatchesXpath('//status');
    }

    public function testDontSeeResponseJsonMatchesXpathFails()
    {
        $this->shouldFail();
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->dontSeeResponseJsonMatchesXpath('//user');
    }

    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2775
     */
    public function testSeeResponseJsonMatchesXPathWorksWithAmpersand()
    {
        $this->setStubResponse('{ "product":[ { "category":[ { "comment":"something & something" } ] } ] }');
        $this->module->seeResponseIsJson();
        $this->module->seeResponseJsonMatchesXpath('//comment');
    }


    public function testSeeResponseJsonMatchesJsonPathFails()
    {
        $this->shouldFail();
        $this->setStubResponse(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseJsonMatchesJsonPath('$[*].profile');
    }


    public function testStructuredJsonPathAndXPath()
    {
        $this->setStubResponse(
            '{ "store": {"book": [{ "category": "reference", "author": "Nigel Rees", '
            . '"title": "Sayings of the Century", "price": 8.95 }, { "category": "fiction", "author": "Evelyn Waugh", '
            . '"title": "Sword of Honour", "price": 12.99 }, { "category": "fiction", "author": "Herman Melville", '
            . '"title": "Moby Dick", "isbn": "0-553-21311-3", "price": 8.99 }, { "category": "fiction", '
            . '"author": "J. R. R. Tolkien", "title": "The Lord of the Rings", "isbn": "0-395-19395-8", '
            . '"price": 22.99 } ], "bicycle": {"color": "red", "price": 19.95 } } }'
        );
        $this->module->seeResponseIsJson();
        $this->module->seeResponseJsonMatchesXpath('//book/category');
        $this->module->seeResponseJsonMatchesJsonPath('$..book');
        $this->module->seeResponseJsonMatchesJsonPath('$.store.book[2].author');
        $this->module->dontSeeResponseJsonMatchesJsonPath('$.invalid');
        $this->module->dontSeeResponseJsonMatchesJsonPath('$.store.book.*.invalidField');
    }

    public function testApplicationJsonSubtypeIncludesObjectSerialized()
    {
        $this->module->haveHttpHeader('Content-Type', 'application/resource+json');
        $this->module->sendPOST('/', new JsonSerializedItem());
        /** @var SymfonyRequest $request **/
        $request = $this->module->client->getRequest();
        $this->assertContains('application/resource+json', $request->getServer());
        $this->assertJson($request->getContent());
    }

    public function testJsonTypeMatches()
    {
        $this->setStubResponse('{"xxx": "yyy", "user_id": 1}');
        $this->module->seeResponseMatchesJsonType(['xxx' => 'string', 'user_id' => 'integer:<10']);
        $this->module->dontSeeResponseMatchesJsonType(['xxx' => 'integer', 'user_id' => 'integer:<10']);
    }

    public function testJsonTypeMatchesWithJsonPath()
    {
        $this->setStubResponse('{"users": [{ "name": "davert"}, {"id": 1}]}');
        $this->module->seeResponseMatchesJsonType(['name' => 'string'], '$.users[0]');
        $this->module->seeResponseMatchesJsonType(['id' => 'integer'], '$.users[1]');
        $this->module->dontSeeResponseMatchesJsonType(['id' => 'integer'], '$.users[0]');
    }

    public function testMatchJsonTypeFailsWithNiceMessage()
    {
        $this->setStubResponse('{"xxx": "yyy", "user_id": 1}');
        try {
            $this->module->seeResponseMatchesJsonType(['zzz' => 'string']);
            $this->fail('it had to throw exception');
        } catch (AssertionFailedError $assertionFailedError) {
            $this->assertSame('Key `zzz` doesn\'t exist in {"xxx":"yyy","user_id":1}', $assertionFailedError->getMessage());
        }
    }

    public function testDontMatchJsonTypeFailsWithNiceMessage()
    {
        $this->setStubResponse('{"xxx": "yyy", "user_id": 1}');
        try {
            $this->module->dontSeeResponseMatchesJsonType(['xxx' => 'string']);
            $this->fail('it had to throw exception');
        } catch (AssertionFailedError $e) {
            $this->assertSame('Unexpectedly response matched: {"xxx":"yyy","user_id":1}', $e->getMessage());
        }
    }

    public function testSeeResponseIsJsonFailsWhenResponseIsEmpty()
    {
        $this->shouldFail();
        $this->setStubResponse('');
        $this->module->seeResponseIsJson();
    }

    public function testSeeResponseIsJsonFailsWhenResponseIsInvalidJson()
    {
        $this->shouldFail();
        $this->setStubResponse('{');
        $this->module->seeResponseIsJson();
    }

    public function testSeeResponseJsonMatchesXpathCanHandleResponseWithOneElement()
    {
        $this->setStubResponse('{"success": 1}');
        $this->module->seeResponseJsonMatchesXpath('//success');
    }

    public function testSeeResponseJsonMatchesXpathCanHandleResponseWithTwoElements()
    {
        $this->setStubResponse('{"success": 1, "info": "test"}');
        $this->module->seeResponseJsonMatchesXpath('//success');
    }

    public function testSeeResponseJsonMatchesXpathCanHandleResponseWithOneSubArray()
    {
        $this->setStubResponse('{"array": {"success": 1}}');
        $this->module->seeResponseJsonMatchesXpath('//array/success');
    }


    public function testSeeResponseJsonXpathEvaluatesToBoolean()
    {
        $this->setStubResponse('{"success": 1}');
        $this->module->seeResponseJsonXpathEvaluatesTo('count(//success) > 0', true);
    }
 
    public function testSeeResponseJsonXpathEvaluatesToNumber()
    {
        $this->setStubResponse('{"success": 1}');
        $this->module->seeResponseJsonXpathEvaluatesTo('count(//success)', 1.0);
    }

    public function testDontSeeResponseJsonXpathEvaluatesToBoolean()
    {
        $this->setStubResponse('{"success": 1}');
        $this->module->dontSeeResponseJsonXpathEvaluatesTo('count(//success) > 0', false);
    }
 
    public function testDontSeeResponseJsonXpathEvaluatesToNumber()
    {
        $this->setStubResponse('{"success": 1}');
        $this->module->dontSeeResponseJsonXpathEvaluatesTo('count(//success)', 0.0);
    }

    public function testSeeBinaryResponseEquals()
    {
        $data = base64_decode('/9j/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=');
        $this->setStubResponse($data);
        $this->module->seeBinaryResponseEquals(sha1($data));
    }

    public function testDontSeeBinaryResponseEquals()
    {
        $data = base64_decode('/9j/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=');
        $this->setStubResponse($data);
        $this->module->dontSeeBinaryResponseEquals('024f615102cdb3c8c7cf75cdc5a83d15');
    }

    public function testAmDigestAuthenticatedThrowsExceptionWithFunctionalModules()
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Not supported by functional modules');
        $this->module->amDigestAuthenticated('username', 'password');
    }

    public function testCanResetHTTPAuthenticated()
    {
        $this->module->amHttpAuthenticated('user', 'pass');
        $this->module->sendGET('/rest/user/');

        $server = $this->module->client->getRequest()->getServer();
        $this->assertArrayHasKey('PHP_AUTH_USER', $server);
        $this->assertArrayHasKey('PHP_AUTH_PW', $server);
        $this->module->setServerParameters([]);
        $this->module->sendGET('/rest/user/');

        $server = $this->module->client->getRequest()->getServer();
        $this->assertArrayNotHasKey('PHP_AUTH_USER', $server);
        $this->assertArrayNotHasKey('PHP_AUTH_PW', $server);
    }

    public function testHaveServerParameter()
    {
        $this->module->haveServerParameter('my', 'param');
        $this->module->sendGET('/rest/user/');

        $server = $this->module->client->getRequest()->getServer();
        $this->assertArrayHasKey('my', $server);
    }

    /**
     * @param $schema
     * @param $response
     * @param $outcome
     * @param $error
     *
     * @dataProvider schemaAndResponse
     */

    public function testSeeResponseIsValidOnJsonSchemachesJsonSchema(string $schema, string $response, bool $outcome, string $error)
    {
        $response = file_get_contents(codecept_data_dir($response));
        $this->setStubResponse($response);

        if (!$outcome) {
            $this->expectExceptionMessage($error);
            $this->shouldFail();
        }

        $this->module->seeResponseIsValidOnJsonSchema(codecept_data_dir($schema));
    }

    public function testSeeResponseIsValidOnJsonSchemachesJsonSchemaString()
    {
        $this->setStubResponse('{"name": "john", "age": 20}');
        $this->module->seeResponseIsValidOnJsonSchemaString('{"type": "object"}');

        $schema = [
            "properties" => [
                "age" => [
                    "type" => "integer",
                    "minimum" => 18
                ]
            ]
        ];
        $this->module->seeResponseIsValidOnJsonSchemaString(json_encode($schema, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider configAndRequestUrls
     */
    public function testRestExecute(string $configUrl, string $requestUrl, string $expectedFullUrl)
    {
        $connectionModule = $this->createMock(
            UniversalFramework::class
        );
        $connectionModule
            ->expects($this->once())
            ->method('_request')
            ->will(
                $this->returnCallback(function($method,
                    $uri,
                    $parameters,
                    $files,
                    $server,
                    $content
                ) use ($expectedFullUrl) {
                    Assert::assertSame($expectedFullUrl, $uri);
                    return '';
                })
            );

        $config = ['url' => $configUrl];

        /** @var REST */
        $module = Stub::make(REST::class);
        $module->_setConfig($config);
        $module->_inject($connectionModule);
        $module->_initialize();
        $module->_before(Stub::makeEmpty(\Codeception\Test\Test::class));

        $module->sendGET($requestUrl);
    }

    public static function schemaAndResponse(): array
    {
        return [
            //schema, responsefile, valid
            ['schemas/basic-schema.json', 'responses/valid-basic-schema.json', true, ""],
            ['schemas/basic-schema.json', 'responses/invalid-basic-schema.json', false, "Must have a minimum value of 0"],
            ['schemas/complex-schema.json', 'responses/valid-complex-schema.json', true, ""],
            ['schemas/complex-schema.json', 'responses/invalid-complex-schema.json', false, "String value found, but a boolean is required"]
        ];
    }

    public static function configAndRequestUrls(): array
    {
        return [
            //$configUrl, $requestUrl, $expectedFullUrl
            ['v1/', 'healthCheck', 'v1/healthCheck'],
            ['/v1', '/healthCheck', '/v1/healthCheck'],
            ['v1', 'healthCheck', 'v1/healthCheck'],
            ['http://v1/', '/healthCheck', 'http://v1/healthCheck'],
            ['http://v1', 'healthCheck', 'http://v1/healthCheck'],
            ['http://v1', 'http://v2/healthCheck', 'http://v2/healthCheck'],
            ['http://v1', '', 'http://v1'],
            ['http://v1', '/', 'http://v1/'],
            ['', 'http://v1', 'http://v1'],
            ['', 'healthCheck', 'healthCheck'],
            ['/', 'healthCheck', '/healthCheck'],
        ];
    }

    protected function shouldFail()
    {
        $this->expectException(AssertionFailedError::class);
    }
}

class JsonSerializedItem implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return array("hello" => "world");
    }
}
