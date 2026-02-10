<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Serializer\Filter\GroupFilter;
use Symfony\Component\Uid\Uuid;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/job/{uuid}',
            requirements: ['uuid' => '[0-9a-f\-]+'],
            normalizationContext: ['groups' => ['job:read']]
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['job:list']]
        ),
        new Post(
            denormalizationContext: ['groups' => ['job:create']],
            normalizationContext: ['groups' => ['job:read']],
            read: false,
            //processor: 'App\State\JobProcessor',
            validationContext: ['groups' => ['create']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['job:read']],
    denormalizationContext: ['groups' => ['job:create']]
)]
#[ApiFilter(PropertyFilter::class, arguments: ['parameterName' => 'properties'])]
#[ApiFilter(GroupFilter::class)]
#[ORM\Entity]
#[ORM\Table(name: 'job')]
#[ORM\Index(columns: ['created_at'])]
#[ORM\Index(columns: ['status'])]
class Job
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['job:read', 'job:list'])]
    private string $uuid;

    #[ORM\Column(type: 'json')]
    #[Groups(['job:create', 'job:read'])]
    private array $urls = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['job:create', 'job:read'])]
    private array $options = [];

    #[ORM\Column(type: 'integer')]
    private int $userId = 0;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['job:read', 'job:list'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['job:read'])]
    private ?string $result = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['job:read', 'job:list'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUrls(): array
    {
        return $this->urls;
    }

    public function setUrls(array $urls): self
    {
        $this->urls = $urls;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
