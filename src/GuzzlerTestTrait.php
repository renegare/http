<?php

namespace Renegare\GuzzleClientHelper;

use GuzzleHttp\Message\MessageFactory;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Exception\ClientException;

trait GuzzlerTestTrait {
    public function assertRequest(GuzzlerInterface $client, array $expectedRequest = [], $assertRequest = null, $expectToBeCalled = true) {
        $mock = $this->getMockBuilder('GuzzleHttp\Client')
            ->setMethods(['send'])
            ->getMock();
        $client->setGuzzle($mock);

        $mockMethod = $mock->expects($expectToBeCalled? $this->once() : $this->never());

        if($expectToBeCalled) {
            $mockMethod->method('send')
                ->will($this->returnCallback(function(Request $request) use ($client, $expectedRequest, $assertRequest) {
                    $client->setGuzzle(null);

                    if(isset($expectedRequest['method'])) {
                        $this->assertEquals(strtolower($request->getMethod()), strtolower($expectedRequest['method']), 'Expected request method match');
                    }

                    if(isset($expectedRequest['path'])) {
                        $this->assertEquals($expectedRequest['path'], $request->getPath(), 'Expected request path match');
                    }

                    if(isset($expectedRequest['headers'])) {
                        $headers = $request->getHeaders();
                        foreach($expectedRequest['headers'] as $name => $value) {
                            $this->assertArrayHasKey($name, $headers);
                            $this->assertEquals(implode(',', $headers[$name]), $value);
                        }
                    }

                    if(isset($expectedRequest['query'])) {
                        $this->assertEquals($expectedRequest['query'], $request->getQuery()->toArray(), 'Expected request query match');
                    }

                    if(isset($expectedRequest['body'])) {
                        $bodyContent = (string) $request->getBody();
                        $expectedBody = $expectedRequest['body'];
                        if(is_array($expectedBody) && $request->hasHeader('Content-Type')) {
                            switch($request->getHeader('Content-Type')) {
                                case 'application/x-www-form-urlencoded':
                                    parse_str($bodyContent, $bodyContent);
                                    break;
                                case 'application/json':
                                    parse_str($bodyContent, $bodyContent);
                                    break;
                            }
                        }
                        $this->assertEquals($expectedBody, $bodyContent, 'Expected request body match');
                    }

                    if($assertRequest instanceOf \Closure) {
                        $response = $assertRequest($request);
                    } else {
                        $response = $assertRequest;
                    }

                    if(!($response instanceOf ResponseInterface)) {
                        $response = $this->createMockResponse($response, $request);
                    }

                    if($response->getStatusCode() > 399) {
                        throw new ClientException($response->getReasonPhrase(), $request, $response);
                    }

                    return $response;
                }));
        }

    }

    public function createMockResponse($response = null, RequestInterface $request = null) {
        if(is_array($response)) {
            $json = json_encode($response);
            $response = sprintf(<<<EOF
HTTP/1.1 200 OK
Date: %s
Connection: keep-alive
Content-length: %s
Content-type: application/json

%s
EOF
, date('D, d M Y H:i:s T'), strlen($json), $json);
        }

        if(!is_string($response)) {
            $response = sprintf(<<<EOF
HTTP/1.1 204 No Content
Date: %s
Connection: keep-alive
EOF
, date('D, d M Y H:i:s T'));
        }

        $factory = new MessageFactory();
        $response = $factory->fromMessage($response);

        return $response;
    }
}
