<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\Http\HttpClientException;
use App\Http\Client\HttpClientInterface;
use App\Messenger\Message\UserRegisteredMessage;
use App\Messenger\RoutingKey;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

class RegisterActionService
{
    private MessageBusInterface $bus;
    private HttpClientInterface $httpClient;
    private string $applicationServiceUserEndpoint;

    public function __construct(MessageBusInterface $bus, HttpClientInterface $httpClient, string $applicationServiceUserEndpoint)
    {
        $this->bus = $bus;
        $this->httpClient = $httpClient;
        $this->applicationServiceUserEndpoint = $applicationServiceUserEndpoint;
    }

    public function __invoke(string $name, string $email): void
    {
        try {
            $this->httpClient->get(\sprintf('%s?email=%s', $this->applicationServiceUserEndpoint, $email));
        } catch (HttpClientException $e) {
            if (JsonResponse::HTTP_NOT_FOUND === $e->getStatusCode()) {
                $this->bus->dispatch(
                    new UserRegisteredMessage($name, $email),
                    [new AmqpStamp(RoutingKey::REGISTER_APPLICATION_QUEUE)]
                );
            }

            return;
        }

        throw new ConflictHttpException(\sprintf('User with email %s already exists', $email));
    }
}
