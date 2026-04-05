<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use Google\Rpc\Code;
use Grpc\Reflection\V1\ExtensionRequest;
use Grpc\Reflection\V1\ServerReflectionClient;
use Grpc\Reflection\V1\ServerReflectionRequest;
use Grpc\Reflection\V1\ServerReflectionResponse;
use Grpc\Reflection\V1\ServiceResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Protobuf\Registry\Pool;

#[CoversClass(Server::class)]
final class ServerTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse();
                }
            }))
            ->build();

        registerV1($this->server);

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testReflectionFlow(): void
    {
        $client = new ServerReflectionClient(new Client\Builder()->build());

        $stream = $client->serverReflectionInfo();

        self::sendExpect(
            $stream,
            new ServerReflectionRequest('localhost:50051', new ServerReflectionRequest\MessageRequestListServices('*')),
            ServerReflectionResponse\MessageResponseListServicesResponse::class,
            static function (ServerReflectionResponse\MessageResponseListServicesResponse $message): void {
                self::assertEquals([new ServiceResponse('echos.api.v1.EchoService'), new ServiceResponse('grpc.reflection.v1.ServerReflection')], $message->listServicesResponse?->service);
            },
        );

        self::sendExpect(
            $stream,
            new ServerReflectionRequest('localhost:50051', new ServerReflectionRequest\MessageRequestFileContainingSymbol('echos.api.v1.EchoService')),
            ServerReflectionResponse\MessageResponseFileDescriptorResponse::class,
            static function (ServerReflectionResponse\MessageResponseFileDescriptorResponse $message): void {
                self::assertEquals([Pool::get()->descriptorByFilename('tests/protos/echo_v1.proto')?->bytes], $message->fileDescriptorResponse?->fileDescriptorProto);
            },
        );

        self::sendExpect(
            $stream,
            new ServerReflectionRequest('localhost:50051', new ServerReflectionRequest\MessageRequestFileContainingSymbol('grpc.reflection.v1.ServerReflection')),
            ServerReflectionResponse\MessageResponseFileDescriptorResponse::class,
            static function (ServerReflectionResponse\MessageResponseFileDescriptorResponse $message): void {
                self::assertEquals([Pool::get()->descriptorByFilename('grpc/reflection/v1/reflection.proto')?->bytes], $message->fileDescriptorResponse?->fileDescriptorProto);
            },
        );

        self::sendExpect(
            $stream,
            new ServerReflectionRequest('localhost:50051', new ServerReflectionRequest\MessageRequestFileContainingExtension(
                new ExtensionRequest(),
            )),
            ServerReflectionResponse\MessageResponseErrorResponse::class,
            static function (ServerReflectionResponse\MessageResponseErrorResponse $message): void {
                self::assertSame(Code::UNIMPLEMENTED->value, $message->errorResponse?->errorCode);
                self::assertSame('extensions are not implemented', $message->errorResponse->errorMessage);
            },
        );

        self::sendExpect(
            $stream,
            new ServerReflectionRequest('localhost:50051', new ServerReflectionRequest\MessageRequestFileByFilename('a.b.c.proto')),
            ServerReflectionResponse\MessageResponseErrorResponse::class,
            static function (ServerReflectionResponse\MessageResponseErrorResponse $message): void {
                self::assertSame(Code::NOT_FOUND->value, $message->errorResponse?->errorCode);
                self::assertSame('file not found', $message->errorResponse->errorMessage);
            },
        );

        $stream->close();
    }

    /**
     * @template T of ServerReflectionResponse\MessageResponse
     * @param Client\BidirectionalStreamChannel<ServerReflectionRequest, ServerReflectionResponse> $channel
     * @param class-string<T> $messageOf
     * @param \Closure(T): void $expect
     */
    private static function sendExpect(
        Client\BidirectionalStreamChannel $channel,
        ServerReflectionRequest $request,
        string $messageOf,
        \Closure $expect,
    ): void {
        $channel->send($request);

        /** @var ServerReflectionResponse $response */
        $response = $channel->receive();
        self::assertEquals($request, $response->originalRequest);

        $messageResponse = $response->messageResponse;
        self::assertInstanceOf($messageOf, $messageResponse);
        $expect($messageResponse);
    }
}
