<?php

namespace App\Infrastructure\Http\EventListener;

use App\Infrastructure\Persistence\Doctrine\DoctrineApiKeyRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class ApiKeyListener
{
    private const PUBLIC_PREFIXES = ['/health', '/webhooks/'];

    public function __construct(
        private readonly DoctrineApiKeyRepository $apiKeyRepo,
        private readonly bool $apiKeyRequired,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->apiKeyRequired) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $key = $event->getRequest()->headers->get('X-Api-Key');
        if ($key === null || $this->apiKeyRepo->findByPlainKey($key) === null) {
            $event->setResponse(new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED));
        }
    }
}
