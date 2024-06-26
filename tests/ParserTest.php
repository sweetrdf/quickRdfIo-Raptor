<?php

declare(strict_types=1);

/*
 * (c) Konrad abicht <hi@inspirito.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use quickRdf\DataFactory;
use quickRdfIo\Raptor\Parser;
use quickRdfIo\Raptor\RapperCommand;
use rdfInterface\QuadIteratorInterface;

class ParserTest extends TestCase
{
    private string $testRdfString = '<http://bar> <http://baz> "1" .'.PHP_EOL.'<http://bar> <http://baz> "2" .'.PHP_EOL;

    public function setUp(): void
    {
        parent::setUp();

        if (false === RapperCommand::rapperCommandIsAvailable()) {
            $this->markTestSkipped('rapper command line tool not available (install raptor2-utils');
        }
    }

    private function getSubjectUnderTest(string|null $baseUri): Parser
    {
        $subjectUnderTest = new Parser(new DataFactory(), $baseUri);
        $subjectUnderTest->setDirPathForTemporaryFiles(__DIR__.'/../.cache');
        return $subjectUnderTest;
    }

    /**
     * @return array<non-empty-string>
     */
    private function generateTripleStringArray(QuadIteratorInterface $iterator): array
    {
        $generated = [];

        foreach ($iterator as $quad) {
            $generated[] = (string) $quad;
        }

        return $generated;
    }

    public function testParseString(): void
    {
        $iterator = $this->getSubjectUnderTest(null)->parse($this->testRdfString);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseStringWithBaseUri(): void
    {
        $str = '@prefix : <http://example.org/prefix/> .
        @prefix rdf: <http://example.org/rdf/> .

        <name> rdf:type rdf:Property .
        :phone rdf:type rdf:Property .';

        $iterator = $this->getSubjectUnderTest('http://test/base/')->parse($str);

        $this->assertEquals(
            [
                'http://test/base/name http://example.org/rdf/type http://example.org/rdf/Property',
                'http://example.org/prefix/phone http://example.org/rdf/type http://example.org/rdf/Property',
            ],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseResource(): void
    {
        // put test RDF into a temp. file
        $filepath = tempnam(sys_get_temp_dir(), 'phpunit_parsertest_');
        file_put_contents($filepath, $this->testRdfString);
        $resource = fopen($filepath, 'r');

        $iterator = $this->getSubjectUnderTest(null)->parse($resource);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseResponseInterface(): void
    {
        // create a mock class for StreamInterface and ResponseInterface
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($this->testRdfString);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);

        $iterator = $this->getSubjectUnderTest(null)->parse($response);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseStreamInterface(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($this->testRdfString);

        $iterator = $this->getSubjectUnderTest(null)->parse($stream);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseWithCustomFormat(): void
    {
        $subjectUnderTest = $this->getSubjectUnderTest(null);
        $subjectUnderTest->setFormat('ntriples');
        $iterator = $subjectUnderTest->parse($this->testRdfString);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseWithInvalidFormat(): void
    {
        $msg = 'Given format is invalid, it must be one of: ';
        $msg .= 'application/rdf+xml, atom, dot, grddl, html, json-triples, json, jsonld, nquads, n-quads, ';
        $msg .= 'ntriples, n-triples, rdfa, rdfxml, rdfxml-abbrev, rdfxml-xmp, rss-1.0, trig, turtle, text/turtle, ttl, xml';
        $this->expectExceptionMessage($msg);

        $subjectUnderTest = $this->getSubjectUnderTest(null);
        $subjectUnderTest->setFormat('invalid');
        $subjectUnderTest->parse($this->testRdfString);
    }

    public function testParseStreamResource(): void
    {
        // put test RDF into a temp. file
        $filepath = tempnam(sys_get_temp_dir(), 'phpunit_parsertest_');
        file_put_contents($filepath, $this->testRdfString);
        $resource = fopen($filepath, 'r');

        $iterator = $this->getSubjectUnderTest(null)->parseStream($resource);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }

    public function testParseStreamStreamInterface(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($this->testRdfString);

        $iterator = $this->getSubjectUnderTest(null)->parseStream($stream);

        $this->assertEquals(
            ['http://bar http://baz 1', 'http://bar http://baz 2'],
            $this->generateTripleStringArray($iterator)
        );
    }
}
