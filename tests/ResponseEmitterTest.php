<?php

declare(strict_types=1);

/**
 * Intercept calls to `header()` in the namespace which the tested class exists
 */
namespace Bref\DevServer
{
    /**
     * Used to override call to native header function in Bref\DevServer namespace
     */
    function header(string $string, bool $replace = true, int $http_response_code = 0): void
    {
        \Bref\DevServer\Test\HeaderCatcher::$headers[] = $string;
    }
}

namespace Bref\DevServer\Test
{
    use Bref\DevServer\ResponseEmitter;
    use Nyholm\Psr7\Response;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\ResponseInterface;

    /**
     * Stores intercepted headers
     */
    class HeaderCatcher
    {
        public static array $headers = [];
    }

    /**
     * Test class
     */
    class ResponseEmitterTest extends TestCase
    {
        /**
         * @inheritDoc
         */
        protected function setUp(): void
        {
            HeaderCatcher::$headers = [];
        }

        /**
         * Test emitting a response
         *
         * @dataProvider emitProvider
         *
         * @param ResponseInterface $response
         * @param string $expected_body
         * @param string[] $expected_headers
         * @return void
         */
        public function testEmit(
            ResponseInterface $response,
            string $expected_body,
            array $expected_headers
        ): void {
            $this->expectOutputString($expected_body);

            $emitter = new ResponseEmitter();
            $emitter->emit($response);
            $this->assertSame($expected_headers, HeaderCatcher::$headers);
        }

        /**
         * Data provider for testEmit
         *
         * @return array<string, array{
         *  response: ResponseInterface,
         *  expected_body: string,
         *  expected_headers: string[]
         * }>
         */
        public function emitProvider(): array
        {
            $response_200 = [
                'response' => new Response(200),
                'expected_body' => '',
                'expected_headers' => [
                    'HTTP/1.1 200 OK',
                ],
            ];

            $response_200_extra_headers = [
                'response' => new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'),
                'expected_body' => '{"status":"ok"}',
                'expected_headers' => [
                    'Content-Type: application/json',
                    'HTTP/1.1 200 OK'
                ],
            ];

            return compact(
                'response_200',
                'response_200_extra_headers',
            );
        }
    }
}
