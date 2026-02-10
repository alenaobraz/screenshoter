<?php

namespace App\Message;

class ProcessJob
{
    public function __construct(private string $jobUuid) {}

    public function getJobUuid(): string
    {
        return $this->jobUuid;
    }
}