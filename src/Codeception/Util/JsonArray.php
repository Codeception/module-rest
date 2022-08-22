<?php

declare(strict_types=1);

namespace Codeception\Util;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Exception;
use Flow\JSONPath\JSONPath;
use InvalidArgumentException;

class JsonArray
{
    protected array $jsonArray = [];

    protected ?DOMDocument $jsonXml = null;

    public function __construct(string $jsonString)
    {
        $jsonDecode = json_decode($jsonString, true);

        if (!is_array($jsonDecode)) {
            $jsonDecode = [$jsonDecode];
        }

        $this->jsonArray = $jsonDecode;

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException(
                sprintf(
                    "Invalid json: %s. System message: %s.",
                    $jsonString,
                    json_last_error_msg()
                ),
                json_last_error()
            );
        }
    }

    public function toXml(): DOMDocument
    {
        if ($this->jsonXml) {
            return $this->jsonXml;
        }

        $root = 'root';
        $jsonArray = $this->jsonArray;
        if (count($jsonArray) == 1) {
            $value = reset($jsonArray);
            if (is_array($value)) {
                $root = key($jsonArray);
                $jsonArray = $value;
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $root = $dom->createElement($root);
        $dom->appendChild($root);
        $this->arrayToXml($dom, $root, $jsonArray);
        $this->jsonXml = $dom;
        return $dom;
    }

    public function toArray(): array
    {
        return $this->jsonArray;
    }

    public function filterByXPath(string $xPath): DOMNodeList|false
    {
        $path = new DOMXPath($this->toXml());
        return $path->query($xPath);
    }
  
    public function evaluateXPath(string $xPath): mixed
    {
        $path = new DOMXPath($this->toXml());
        return $path->evaluate($xPath);
    }

    public function filterByJsonPath(string $jsonPath): array
    {
        if (!class_exists(JSONPath::class)) {
            throw new Exception('JSONPath library not installed. Please add `softcreatr/jsonpath` to composer.json');
        }

        return (new JSONPath($this->jsonArray))->find($jsonPath)->getData();
    }

    public function getXmlString(): string|bool
    {
        return $this->toXml()->saveXML();
    }

    public function containsArray(array $needle): bool
    {
        return (new ArrayContainsComparator($this->jsonArray))->containsArray($needle);
    }

    private function arrayToXml(DOMDocument $doc, DOMNode $node, array $array): void
    {
        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $subNode = $doc->createElement($node->nodeName);
                $node->parentNode->appendChild($subNode);
            } else {
                try {
                    $subNode = $doc->createElement($key);
                } catch (Exception) {
                    $key = $this->getValidTagNameForInvalidKey($key);
                    $subNode = $doc->createElement($key);
                }

                $node->appendChild($subNode);
            }

            if (is_array($value)) {
                $this->arrayToXml($doc, $subNode, $value);
            } else {
                $this->setValue($subNode, $value);
            }
        }
    }

    private function setValue($subNode, $value) {
        switch(gettype($value)) {
            case 'boolean':
                $subNode->nodeValue = $value?'true':'false';
                $subNode->setAttribute('type', 'boolean');
                break;
            case 'integer':
            case 'double':
                $subNode->nodeValue = (string) $value;
                $subNode->setAttribute('type', 'number');
                break;
            case 'NULL':
                $subNode->nodeValue = '';
                $subNode->setAttribute('type', 'null');
                break;
            default:
                $subNode->nodeValue = htmlspecialchars((string) $value);
                $subNode->setAttribute('type', 'string');
                break;
        }
    }

    private function getValidTagNameForInvalidKey($key)
    {
        static $map = [];
        if (!isset($map[$key])) {
            $tagName = 'invalidTag' . (count($map) + 1);
            $map[$key] = $tagName;
            codecept_debug($tagName . ' is "' . $key . '"');
        }

        return $map[$key];
    }
}
