<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\SendEmailHandler;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use PH7\JustHttp\StatusCode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;

use function json_encode;

class SendEmailHandlerTest extends TestCase
{
    public function testSendEmail(): void
    {
        $requestBody = [
            'from_address' => 'send.from@example.com',
            'from_name'    => 'Sender',
            'to_address'   => 'send.to@example.com',
            'to_name'      => 'Recipient',
            'subject'      => 'SendGrid Test Email',
            'content_html' => '<p>Test</p>',
        ];

        $_ENV['SENDGRID_API_KEY'] = 'key';

        $mail = $this->createMock(Mail::class);
        $mail
            ->expects($this->once())
            ->method('setFrom')
            ->with($requestBody['from_address'], $requestBody['from_name']);
        $mail
            ->expects($this->once())
            ->method('addTo')
            ->with($requestBody['to_address'], $requestBody['to_name']);
        $mail
            ->expects($this->once())
            ->method('setSubject')
            ->with($requestBody['subject']);
        $mail
            ->expects($this->once())
            ->method('addContent')
            ->with('text/html', $requestBody['content_html']);

        $sendgrid = $this->createMock(SendGrid::class);
        $sendgrid
            ->expects($this->once())
            ->method('send')
            ->with($mail)
            ->willReturn(new SendGrid\Response(
                StatusCode::ACCEPTED,
                '',
                [
                    'Content-Type' => 'application/json',
                ]
            ));

        $handler = new SendEmailHandler($mail, $sendgrid);

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($requestBody);

        $response = $handler->handle($request);

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
        $requestBody = [
            'from_address' => 'send.from@example.com',
            'from_name'    => 'Sender',
            'to_address'   => 'send.to@example.com',
            'to_name'      => 'Recipient',
            'subject'      => 'SendGrid Test Email',
            'content_html' => '<p>Test</p>',
        ];

        $mail = $this->createMock(Mail::class);
        $mail
            ->expects($this->once())
            ->method('setFrom')
            ->with($requestBody['from_address'], $requestBody['from_name']);
        $mail
            ->expects($this->once())
            ->method('addTo')
            ->with($requestBody['to_address'], $requestBody['to_name']);
        $mail
            ->expects($this->once())
            ->method('setSubject')
            ->with($requestBody['subject']);
        $mail
            ->expects($this->once())
            ->method('addContent')
            ->with('text/html', $requestBody['content_html']);

        $sendgrid = $this->createMock(SendGrid::class);
        $sendgrid
            ->expects($this->once())
            ->method('send')
            ->with($mail)
            ->willReturn(new SendGrid\Response(
                StatusCode::UNAUTHORIZED,
                json_encode([
                    'errors' => [
                        "message" => "Unauthorized",
                        "field"   => "null",
                    ],
                ]),
                [
                    'Content-Type' => 'application/json',
                ]
            ));

        $handler = new SendEmailHandler($mail, $sendgrid);

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($requestBody);

        $response = $handler->handle($request);
        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame(StatusCode::UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Unauthorized', (string) $response->getBody());
    }

    public function testCanHandleSendGridExceptions(): void
    {
        $requestBody = [
            'from_address' => 'send.from@example.com',
            'from_name'    => 'Sender',
            'to_address'   => 'not.an.email.address',
            'to_name'      => 'Recipient',
            'subject'      => 'SendGrid Test Email',
            'content_html' => '<p>Test</p>',
        ];

        $errorMessage = '"$to_address" must be a valid email address. Got: not.an.email.address';

        $mail = $this->createMock(Mail::class);
        $mail
            ->expects($this->once())
            ->method('setFrom')
            ->willThrowException(
                new TypeException($errorMessage)
            );

        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($requestBody);

        $handler  = new SendEmailHandler($mail, $this->createMock(SendGrid::class));
        $response = $handler->handle($request);

        $this->assertInstanceOf(TextResponse::class, $response);
        $this->assertSame(StatusCode::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame($errorMessage, (string) $response->getBody());
    }
}
