<?php
/**
 * @copyright: DotKernel
 * @library: dotkernel/dot-user
 * @author: n3vrax
 * Date: 7/26/2016
 * Time: 9:22 PM
 */

namespace Dot\User\Factory;

use Dot\Authentication\AuthenticationInterface;
use Dot\User\Event\Listener\UserListenerAwareInterface;
use Dot\User\Exception\InvalidArgumentException;
use Dot\User\Options\UserOptions;
use Dot\User\Service\UserService;
use Interop\Container\ContainerInterface;
use Zend\Crypt\Password\PasswordInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\Exception\InvalidServiceException;

/**
 * Class UserServiceFactory
 * @package Dot\User\Factory
 */
class UserServiceFactory
{
    /** @var  UserOptions */
    protected $options;

    /**
     * @param ContainerInterface $container
     * @param $requestedName
     * @return UserService
     */
    public function __invoke(ContainerInterface $container, $requestedName)
    {
        if (!class_exists($requestedName)) {
            throw new InvalidServiceException("Class of type $requestedName could not be found");
        }

        /** @var UserOptions $options */
        $options = $container->get(UserOptions::class);
        $this->options = $options;

        $isDebug = isset($container->get('config')['debug'])
            ? (bool)$container->get('config')['debug']
            : false;

        $eventManager = $container->has(EventManagerInterface::class)
            ? $container->get(EventManagerInterface::class)
            : new EventManager();

        /** @var UserService $service */
        $service = new $requestedName(
            $container->get('UserMapper'),
            $options,
            $container->get(PasswordInterface::class),
            $container->get(AuthenticationInterface::class)
        );

        $service->setEventManager($eventManager);
        $service->setDebug($isDebug);

        $this->attachUserListeners($service, $container);

        return $service;
    }

    /**
     * @param UserListenerAwareInterface $service
     * @param ContainerInterface $container
     */
    protected function attachUserListeners(UserListenerAwareInterface $service, ContainerInterface $container)
    {
        $listeners = $this->options->getUserEventListeners();
        foreach ($listeners as $listener) {
            if (is_string($listener) && $container->has($listener)) {
                $listener = $container->get($listener);
            } elseif (is_string($listener) && class_exists($listener)) {
                $listener = new $listener;
            }

            if (!$listener instanceof AbstractListenerAggregate) {
                throw new InvalidArgumentException(sprintf(
                    'Provided user service listener of type "%s" is not valid. Expected string or %s',
                    is_object($listener) ? get_class($listener) : gettype($listener),
                    AbstractListenerAggregate::class
                ));
            }

            $service->attachUserListener($listener);
        }
    }
}
