<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Job;
use App\Message\ProcessJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class JobProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Job
    {
        if (!$data instanceof Job) {
            throw new \InvalidArgumentException('Data must be a Job');
        }


        $this->em->persist($data);
        $this->em->flush();

        // Отправляем в очередь
        $this->bus->dispatch(new ProcessJob($data->getUuid()));

        return $data;
    }
}
