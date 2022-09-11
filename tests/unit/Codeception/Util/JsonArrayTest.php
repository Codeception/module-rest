<?php

declare(strict_types=1);

namespace Codeception\Util;

use Codeception\Test\Unit;
use InvalidArgumentException;

final class JsonArrayTest extends Unit
{
    protected ?JsonArray $jsonArray = null;

    protected function _before()
    {
        $this->jsonArray = new JsonArray(
            '{"ticket": {"title": "Bug should be fixed", "user": {"name": "Davert"}, "labels": null}}'
        );
    }

    public function testXmlConversion()
    {
        $this->assertStringContainsString(
            '<ticket><title type="string">Bug should be fixed</title><user><name type="string">Davert</name></user><labels type="null"></labels></ticket>',
            $this->jsonArray->toXml()->saveXML()
        );
    }

    public function testXmlArrayConversion2()
    {
        $jsonArray = new JsonArray(
            '[{"user":"Blacknoir","age":27,"tags":["wed-dev","php"]},'
            . '{"user":"John Doe","age":27,"tags":["web-dev","java"]}]'
        );
        $this->assertStringContainsString('<tags type="string">wed-dev</tags>', $jsonArray->toXml()->saveXML());
        $this->assertSame(2, $jsonArray->filterByXPath('//user')->length);
    }

    public function testXPathEvaluation()
    {
        $this->assertTrue($this->jsonArray->evaluateXPath('count(//ticket/title)>0'));
        $this->assertEquals(1, $this->jsonArray->evaluateXPath('count(//ticket/user/name)'));
        $this->assertTrue($this->jsonArray->evaluateXPath("count(//user/name[text() = 'Davert']) > 0"));
    }

    public function testXPathTypes()
    {
        $jsonArray = new JsonArray(
            '{"boolean":true, "number": -1.2780E+2, "null": null, "string": "i\'am a sentence"}'
        );
        $this->assertEquals(0, $jsonArray->evaluateXPath("count(//*[text() = 'false'])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//boolean[text() = 'true'])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//boolean[@type = 'boolean'])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//number[text() = -127.80])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//number[text() = -1.2780E+2])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//number[@type = 'number'])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//null[@type = 'null'])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//null[text() = ''])"));
        $this->assertEquals(1, $jsonArray->evaluateXPath("count(//string[@type = 'string'])"));
    }

    public function testXPathLocation()
    {
        $this->assertGreaterThan(0, $this->jsonArray->filterByXPath('//ticket/title')->length);
        $this->assertGreaterThan(0, $this->jsonArray->filterByXPath('//ticket/user/name')->length);
        $this->assertGreaterThan(0, $this->jsonArray->filterByXPath('//user/name')->length);
    }

    public function testJsonPathLocation()
    {
        $this->assertNotEmpty($this->jsonArray->filterByJsonPath('$..user'));
        $this->assertNotEmpty($this->jsonArray->filterByJsonPath('$.ticket.user.name'));
        $this->assertNotEmpty($this->jsonArray->filterByJsonPath('$..user.name'));
        $this->assertSame(['Davert'], $this->jsonArray->filterByJsonPath('$.ticket.user.name'));
        $this->assertEmpty($this->jsonArray->filterByJsonPath('$..invalid'));
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/2535
     */
    public function testThrowsInvalidArgumentExceptionIfJsonIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        new JsonArray('{"test":');
    }

    /**
     * @issue https://github.com/Codeception/Codeception/issues/4944
     */
    public function testConvertsBareJson()
    {
        $jsonArray = new JsonArray('"I am a {string}."');
        $this->assertSame(['I am a {string}.'], $jsonArray->toArray());
    }

    /**
     * @Issue https://github.com/Codeception/Codeception/issues/2899
     */
    public function testInvalidXmlTag()
    {
        $jsonArray = new JsonArray('{"a":{"foo/bar":1,"":2},"b":{"foo/bar":1,"":2},"baz":2}');
        $expectedXml = '<a><invalidTag1 type="number">1</invalidTag1><invalidTag2 type="number">2</invalidTag2></a>'
            . '<b><invalidTag1 type="number">1</invalidTag1><invalidTag2 type="number">2</invalidTag2></b><baz type="number">2</baz>';
        $this->assertStringContainsString($expectedXml, $jsonArray->toXml()->saveXML());
    }

    public function testConvertsArrayHavingSingleElement()
    {
        $jsonArray = new JsonArray('{"success": 1}');
        $expectedXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n<root><success type=\"number\">1</success></root>\n";
        $this->assertSame($expectedXml, $jsonArray->toXml()->saveXML());
    }

    public function testConvertsArrayHavingTwoElements()
    {
        $jsonArray = new JsonArray('{"success": 1, "info": "test"}');
        $expectedXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n<root><success type=\"number\">1</success><info type=\"string\">test</info></root>\n";
        $this->assertSame($expectedXml, $jsonArray->toXml()->saveXML());
    }

    public function testConvertsArrayHavingSingleSubArray()
    {
        $jsonArray = new JsonArray('{"array": {"success": 1}}');
        $expectedXml = '<?xml version="1.0" encoding="UTF-8"?>'
             . "\n<array><success type=\"number\">1</success></array>\n";
        $this->assertSame($expectedXml, $jsonArray->toXml()->saveXML());
    }
}
