<?php

namespace App\MessageHandler;

use App\Entity\Job;
use App\Message\ProcessJob;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class ProcessJobHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $client,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ProcessJob $message): void
    {

    }
}
