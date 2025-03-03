<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use PH7\JustHttp\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\TypeException;
use function array_intersect;
use function array_keys;
use function count;

class SendEmailHandler implements RequestHandlerInterface
{
    public const array REQUIRED_POST_PARAMS = [
        'content_html',
        'from_address',
        'from_name',
        'subject',
        'to_address',
        'to_name',
    ];

    public function __construct(private Mail $mail, private SendGrid $sendGrid)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = (array)$request->getParsedBody();
        $keys = array_intersect(self::REQUIRED_POST_PARAMS, array_keys($parsedBody));
        if (count($keys) !== count(self::REQUIRED_POST_PARAMS)) {
            $missingPostElements = array_values(
                array_diff(
                    array_values(self::REQUIRED_POST_PARAMS),
                    array_keys($parsedBody)
                )
            );
            sort($missingPostElements, SORT_STRING);
            return new JsonResponse(
                [
                    "Error" => "Missing configuration items.",
                    "Missing configuration items." => $missingPostElements,
                ],
                StatusCode::BAD_REQUEST
            );
        }

        try {
            $response = $this->sendEmail($parsedBody);
        } catch (TypeException $e) {
            return new TextResponse($e->getMessage(), StatusCode::BAD_REQUEST);
        }

        return match ($response->statusCode()) {
            StatusCode::ACCEPTED => new EmptyResponse(),
            StatusCode::BAD_REQUEST,
            StatusCode::FORBIDDEN,
            StatusCode::INTERNAL_SERVER_ERROR,
            StatusCode::METHOD_NOT_ALLOWED,
            StatusCode::NOT_FOUND,
            StatusCode::PAYLOAD_TOO_LARGE,
            StatusCode::UNAUTHORIZED => new TextResponse(
                $this->getErrorMessage($response),
                $response->statusCode()
            ),
            default => new TextResponse("Unknown response", StatusCode::INTERNAL_SERVER_ERROR),
        };
    }

    /**
     * sendEmail simplifies initialising the Mail object and sending it.
     *
     * @param array<string,string> $config The mail parameters for the sender, recipient, subject, and HTML body
     * @throws TypeException
     */
    private function sendEmail(array $config = []): SendGrid\Response
    {
        $this->mail->setFrom($config['from_address'], $config['from_name']);
        $this->mail->addTo($config['to_address'], $config['to_name']);
        $this->mail->setSubject($config['subject']);
        $this->mail->addContent('text/html', $config['content_html']);

        return $this->sendGrid->send($this->mail);
    }

    public function getErrorMessage(SendGrid\Response $response): string
    {
        return json_decode(
            json: $response->body(),
            associative: true,
            flags: JSON_OBJECT_AS_ARRAY
        )['errors']['message'];
    }
}
