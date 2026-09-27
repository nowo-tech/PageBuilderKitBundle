<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pb_document_locale')]
#[ORM\UniqueConstraint(name: 'pb_document_locale_document_locale', columns: ['document_id', 'locale'])]
class BuilderDocumentLocale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $locale = '';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $widgetProps = [];

    #[ORM\ManyToOne(targetEntity: BuilderDocument::class, inversedBy: 'locales')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BuilderDocument $document;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->locale = $locale;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getWidgetProps(): array
    {
        return $this->widgetProps;
    }

    /** @param array<string, mixed> $widgetProps */
    public function setWidgetProps(array $widgetProps): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->widgetProps = $widgetProps;

        return $this;
    }

    public function getDocument(): BuilderDocument
    {
        return $this->document;
    }

    public function setDocument(BuilderDocument $document): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->document = $document;

        return $this;
    }
}
