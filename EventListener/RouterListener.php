<?php

namespace Theodo\Evolution\Bundle\LegacyWrapperBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener as SymfonyRouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Theodo\Evolution\Bundle\LegacyWrapperBundle\Kernel\LegacyKernelInterface;

/**
 * RouterListener delegates the request handling to the Symfony router listener.
 * If the later does not match any controller, then this listener catches the NotFoundHttpException
 * and tells the wrapper to handle the request instead.
 *
 * @author Benjamin Grandfond <benjaming@theodo.fr>
 */
class RouterListener implements EventSubscriberInterface
{
    /**
     * @var LegacyKernelInterface
     */
    protected $legacyKernel;

    /**
     * @var RouterListener
     */
    protected $routerListener;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LegacyKernelInterface $legacyKernel, SymfonyRouterListener $routerListener, LoggerInterface $logger = null)
    {
        $this->legacyKernel = $legacyKernel;
        $this->routerListener = $routerListener;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        try {
            $this->routerListener->onKernelRequest($event);
        } catch (NotFoundHttpException $e) {
            if (null !== $this->logger) {
                $this->logger->info('Request handled by the '.$this->legacyKernel->getName().' kernel.');
            }

            $response = $this->legacyKernel->handle($event->getRequest(), $event->getRequestType(), true);
            if ($response->getStatusCode() !== 404) {
                $event->setResponse($response);
            }
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $this->routerListener->onKernelFinishRequest($event);
    }

    /**
     * @{inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 31]],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', 0]],
        ];
    }
}
