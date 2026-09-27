<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepository;

#[ORM\Entity(repositoryClass: BuilderPageTemplateRepository::class)]
#[ORM\Table(name: 'pb_page_template')]
#[ORM\HasLifecycleCallbacks]
class BuilderPageTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $templateKey = '';

    #[ORM\Column(length: 255)]
    private string $label = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $structure = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $widgetPropsByLocale = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->templateKey = $templateKey;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->label = $label;

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

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
