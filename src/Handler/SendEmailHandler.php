<?php

declare(strict_types=1);

namespace App\Handler;

use Exception;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SendGrid;
use SendGrid\Mail\Mail;

use function array_filter;
use function printf;

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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $keys = array_intersect(self::REQUIRED_POST_PARAMS, array_keys($parsedBody));
        if (count($keys) !== count(self::REQUIRED_POST_PARAMS)) {
            return new JsonResponse(
                [
                    "Error" => "Missing configuration items.",
                    "Missing configuration items." => array_diff(
                        self::REQUIRED_POST_PARAMS,
                        array_keys($parsedBody)
                    )
                ]
            );
        }
        $this->sendEmail($parsedBody);
        return new EmptyResponse();
    }

    private function sendEmail(array $config): void
    {
        $email = new Mail();

        $email->setFrom($config['from_address'], $config['from_name']);
        $email->addTo($config['to_address'], $config['to_name']);
        $email->setSubject($config['subject']);
        $email->addContent('text/html', $config['content_html']);

        $sendgrid = new SendGrid($_ENV['SENDGRID_API_KEY']);
        try {
            $response = $sendgrid->send($email);
            printf("Response status: %d\n\n", $response->statusCode());
            $headers = array_filter($response->headers());
            echo "Response Headers\n\n";
            foreach ($headers as $header) {
                echo '- ' . $header . "\n";
            }
        } catch (Exception $e) {
            echo 'Caught exception: ' . $e->getMessage() . "\n";
        }
    }
}
