<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new Get(), new GetCollection()],
    normalizationContext: ['groups' => ['session:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['event.season.series.code' => 'exact', 'event.season.year' => 'exact', 'event.roundNumber' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['startsAt'])]
#[ApiFilter(OrderFilter::class, properties: ['startsAt'])]
#[ORM\Entity]
#[ORM\Table(name: 'race_session')]
#[ORM\UniqueConstraint(name: 'uniq_session_event_name', columns: ['event_id', 'name'])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['session:read'])]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        #[ApiProperty(readableLink: false)]
        #[Groups(['session:read'])]
        private readonly Event $event,
        #[ORM\Column(length: 64)]
        #[Groups(['session:read'])]
        private readonly string $name,
        #[ORM\Column]
        #[Groups(['session:read'])]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(nullable: true)]
        #[Groups(['session:read'])]
        private ?DateTimeImmutable $endsAt,
        #[ORM\Column(length: 512)]
        #[Groups(['session:read'])]
        private string $sourceUrl,
        #[ORM\Column(length: 6, nullable: true)]
        #[Groups(['session:read'])]
        private ?string $trackTimezoneOffset,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function getTrackTimezoneOffset(): ?string
    {
        return $this->trackTimezoneOffset;
    }

    public function updateFromSchedule(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt, string $sourceUrl, ?string $trackTimezoneOffset): void
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->sourceUrl = $sourceUrl;
        $this->trackTimezoneOffset = $trackTimezoneOffset;
    }
}
