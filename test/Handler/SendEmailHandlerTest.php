<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\SendEmailHandler;
use Laminas\Diactoros\Response\EmptyResponse;
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
        $this->mail = $this->createMock(Mail::class);
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

        $this->sendgrid = $this->createMock(SendGrid::class);

        $this->requestBody = [
            'from_address' => 'send.from@example.com',
            'from_name' => 'Sender',
            'to_address' => 'send.to@example.com',
            'to_name' => 'Recipient',
            'subject' => 'SendGrid Test Email',
            'content_html' => '<p>Test</p>',
        ];

        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($this->requestBody);
    }

    public function testSendEmail(): void
    {
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

        $handler = new SendEmailHandler($this->mail, $this->sendgrid);
        $response = $handler->handle($this->request);

        $this->assertInstanceOf(EmptyResponse::class, $response);
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

        $handler = new SendEmailHandler($this->mail, $this->sendgrid);
        $response = $handler->handle($this->request);
        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($responseMessage, (string)$response->getBody());
    }

    public function testCanHandleSendGridExceptions(): void
    {
        $errorMessage = '"$to_address" must be a valid email address. Got: not.an.email.address';
        $this->expectException(TypeException::class);
        $this->expectExceptionMessageMatches($errorMessage);

        $this->mail = $this->createMock(Mail::class);
        $this->mail
            ->expects($this->once())
            ->method('setFrom')
            ->willThrowException(new TypeException());

        $handler = new SendEmailHandler($this->mail, $this->createMock(SendGrid::class));
        $response = $handler->handle($this->request);

        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame(StatusCode::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame($errorMessage, (string)$response->getBody());
    }
}
