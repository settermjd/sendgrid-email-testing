<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\SendEmailHandler;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use PH7\JustHttp\StatusCode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;
use function json_encode;

class SendEmailHandlerTest extends TestCase
{
    private array $requestBody = [];
    private Mail&MockObject $mail;
    private SendGrid&MockObject $sendgrid;
    private ServerRequestInterface&MockObject $request;

    public function setUp(): void
    {
        $this->requestBody = [
            'from_address' => 'send.from@example.com',
            'from_name' => 'Sender',
            'to_address' => 'send.to@example.com',
            'to_name' => 'Recipient',
            'subject' => 'SendGrid Test Email',
            'content_html' => '<p>Test</p>',
        ];
        $this->mail = $this->createMock(Mail::class);
        $this->sendgrid = $this->createMock(SendGrid::class);
    }

    public function testSendEmail(): void
    {
        $request = $this->setServerRequest($this->requestBody);

        $this->sendgrid
            ->expects($this->once())
            ->method('send')
            ->with($this->mail)
            ->willReturn(new SendGrid\Response(
                StatusCode::ACCEPTED,
                '',
                [
                    'Content-Type' => 'application/json',
                ]
            ));

        $this->mail
            ->expects($this->once())
            ->method('setFrom')
            ->with($this->requestBody['from_address'], $this->requestBody['from_name']);
        $this->mail
            ->expects($this->once())
            ->method('addTo')
            ->with($this->requestBody['to_address'], $this->requestBody['to_name']);
        $this->mail
            ->expects($this->once())
            ->method('setSubject')
            ->with($this->requestBody['subject']);
        $this->mail
            ->expects($this->once())
            ->method('addContent')
            ->with('text/html', $this->requestBody['content_html']);

        $handler = new SendEmailHandler($this->mail, $this->sendgrid);
        $response = $handler->handle($request);

        $this->assertInstanceOf(EmptyResponse::class, $response);
    }

    public function setServerRequest(array $requestBody): ServerRequestInterface&MockObject
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($requestBody);

        return $request;
    }

    #[TestWith(
        [
            [
                'from_address' => 'send.from@example.com',
                'from_name' => 'Sender',
                'to_address' => 'send.to@example.com',
                'subject' => 'SendGrid Test Email',
                'content_html' => '<p>Test</p>',
            ],
            [
                'to_name',
            ],
        ]
    )]
    #[TestWith(
        [
            [
                'from_address' => 'send.from@example.com',
                'subject' => 'SendGrid Test Email',
                'content_html' => '<p>Test</p>',
            ],
            [
                'from_name',
                'to_address',
                'to_name',
            ],
        ]
    )]
    #[TestWith(
        [
            [
            ],
            [
                'content_html',
                'from_address',
                'from_name',
                'subject',
                'to_address',
                'to_name',
            ],
        ]
    )]
    #[TestWith(
        [
            [
                'from_address' => 'send.from@example.com',
                'from_name' => 'Sender',
                'to_address' => 'send.to@example.com',
                'subject' => 'SendGrid Test Email',
            ],
            [
                'content_html',
                'to_name',
            ],
        ]
    )]
    #[TestWith(
        [
            [
                'from_address' => 'send.from@example.com',
                'from_name' => 'Sender',
                'subject' => 'SendGrid Test Email',
                'to_address' => 'send.to@example.com',
                'to_name' => 'Recipient',
            ],
            [
                'content_html',
            ],
        ]
    )]
    public function testReturnsJsonErrorResponseIfPostParametersAreMissing(array $requestBody = [], array $missingItems = []): void
    {
        $request = $this->setServerRequest($requestBody);
        $handler = new SendEmailHandler($this->mail, $this->sendgrid);
        $response = $handler->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(StatusCode::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(
            [
                "Error" => "Missing configuration items.",
                "Missing configuration items." => $missingItems,
            ],
            json_decode(
                json: (string)$response->getBody(),
                associative: true,
                flags: JSON_OBJECT_AS_ARRAY
            )
        );
    }

    #[TestWith([StatusCode::BAD_REQUEST, "invalid request"])]
    #[TestWith([StatusCode::FORBIDDEN, "access forbidden"])]
    #[TestWith([StatusCode::INTERNAL_SERVER_ERROR, "internal server error"])]
    #[TestWith([StatusCode::METHOD_NOT_ALLOWED, "method not allowed"])]
    #[TestWith([StatusCode::NOT_FOUND, "not found"])]
    #[TestWith([StatusCode::PAYLOAD_TOO_LARGE, "content too large"])]
    #[TestWith([StatusCode::UNAUTHORIZED, "authorization required"])]
    public function testCanHandleErrors(int $statusCode, string $responseMessage): void
    {
        $this->sendgrid
            ->expects($this->once())
            ->method('send')
            ->with($this->mail)
            ->willReturn(new SendGrid\Response(
                $statusCode,
                json_encode([
                    'errors' => [
                        "message" => $responseMessage,
                        "field" => "null",
                    ],
                ]),
                [
                    'Content-Type' => 'application/json',
                ]
            ));

        $request = $this->setServerRequest($this->requestBody);

        $this->mail
            ->expects($this->once())
            ->method('setFrom')
            ->with($this->requestBody['from_address'], $this->requestBody['from_name']);
        $this->mail
            ->expects($this->once())
            ->method('addTo')
            ->with($this->requestBody['to_address'], $this->requestBody['to_name']);
        $this->mail
            ->expects($this->once())
            ->method('setSubject')
            ->with($this->requestBody['subject']);
        $this->mail
            ->expects($this->once())
            ->method('addContent')
            ->with('text/html', $this->requestBody['content_html']);

        $handler = new SendEmailHandler($this->mail, $this->sendgrid);
        $response = $handler->handle($request);
        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($responseMessage, (string)$response->getBody());
    }

    public function testCanHandleSendGridExceptions(): void
    {
        $message = 'Message';
        $this->mail = $this->createMock(Mail::class);
        $this->mail
            ->expects($this->once())
            ->method('setFrom')
            ->willThrowException(
                new TypeException($message)
            );
        $request = $this->setServerRequest($this->requestBody);
        $handler = new SendEmailHandler($this->mail, $this->createMock(SendGrid::class));
        $response = $handler->handle($request);

        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame(StatusCode::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame($message, (string)$response->getBody());
    }
}
