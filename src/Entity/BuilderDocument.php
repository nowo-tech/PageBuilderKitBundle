<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pb_document')]
class BuilderDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: BuilderPage::class, inversedBy: 'document')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BuilderPage $page;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $structure = [];

    /** @var Collection<int, BuilderDocumentLocale> */
    #[ORM\OneToMany(targetEntity: BuilderDocumentLocale::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $locales;

    public function __construct()
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->locales = new ArrayCollection();
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
        if ($page->getDocument() !== $this) {
            $page->setDocument($this);
        }

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

    /** @return Collection<int, BuilderDocumentLocale> */
    public function getLocales(): Collection
    {
        return $this->locales;
    }

    public function getLocaleDocument(string $locale): ?BuilderDocumentLocale
    {
        foreach ($this->locales as $localeDocument) {
            if ($localeDocument->getLocale() === $locale) {
                return $localeDocument;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $widgetProps
     */
    public function upsertLocale(string $locale, array $widgetProps): BuilderDocumentLocale
    {
        $existing = $this->getLocaleDocument($locale);
        if ($existing instanceof BuilderDocumentLocale) {
            // @igor-ignore - Doctrine entity instance state; not a FrankenPHP shared service.
            $existing->setWidgetProps($widgetProps);

            return $existing;
        }

        $localeDocument = (new BuilderDocumentLocale())
            ->setLocale($locale)
            ->setWidgetProps($widgetProps)
            ->setDocument($this);
        $this->locales->add($localeDocument);

        return $localeDocument;
    }
}
