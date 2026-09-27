<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pb_page_revision')]
class BuilderPageRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'revisions', targetEntity: BuilderPage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BuilderPage $page;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $structure = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $widgetPropsByLocale = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function __construct()
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): BuilderPage
    {
        return $this->page;
    }

    public function setPage(BuilderPage $page): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->page = $page;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getStructure(): array
    {
        return $this->structure;
    }

    /** @param array<string, mixed> $structure */
    public function setStructure(array $structure): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->structure = $structure;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getWidgetPropsByLocale(): array
    {
        return $this->widgetPropsByLocale;
    }

    /** @param array<string, mixed> $widgetPropsByLocale */
    public function setWidgetPropsByLocale(array $widgetPropsByLocale): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->widgetPropsByLocale = $widgetPropsByLocale;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->label = $label;

        return $this;
    }
}
