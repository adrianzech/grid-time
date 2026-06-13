<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'race_session')]
#[ORM\UniqueConstraint(name: 'uniq_session_event_name', columns: ['event_id', 'name'])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Event $event,
        #[ORM\Column(length: 64)]
        private string $name,
        #[ORM\Column]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(nullable: true)]
        private ?DateTimeImmutable $endsAt,
        #[ORM\Column(length: 512)]
        private string $sourceUrl,
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

    public function updateFromSchedule(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt, string $sourceUrl): void
    {
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->sourceUrl = $sourceUrl;
    }
}
