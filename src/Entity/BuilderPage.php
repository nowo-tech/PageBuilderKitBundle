<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;

#[ORM\Entity(repositoryClass: BuilderPageRepository::class)]
#[ORM\Table(name: 'pb_page')]
#[ORM\HasLifecycleCallbacks]
class BuilderPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid = '';

    #[ORM\Column(length: 64, unique: true)]
    private string $pageKey = '';

    #[ORM\Column(length: 16, enumType: PageStatus::class)]
    private PageStatus $status = PageStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, BuilderPageTranslation> */
    #[ORM\OneToMany(targetEntity: BuilderPageTranslation::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToOne(targetEntity: BuilderDocument::class, mappedBy: 'page', cascade: ['persist', 'remove'])]
    private ?BuilderDocument $document = null;

    /** @var Collection<int, BuilderPageRevision> */
    #[ORM\OneToMany(targetEntity: BuilderPageRevision::class, mappedBy: 'page', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $revisions;

    public function __construct()
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->translations = new ArrayCollection();
        $this->revisions    = new ArrayCollection();
        $this->createdAt    = new DateTimeImmutable();
        $this->updatedAt    = new DateTimeImmutable();
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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->uuid = $uuid;

        return $this;
    }

    public function getPageKey(): string
    {
        return $this->pageKey;
    }

    public function setPageKey(string $pageKey): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->pageKey = $pageKey;

        return $this;
    }

    public function getStatus(): PageStatus
    {
        return $this->status;
    }

    public function setStatus(PageStatus $status): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->publishedAt = $publishedAt;

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

    /** @return Collection<int, BuilderPageTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(BuilderPageTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPage($this);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?BuilderPageTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function getDocument(): ?BuilderDocument
    {
        return $this->document;
    }

    public function setDocument(?BuilderDocument $document): self
    {
        if ($document instanceof BuilderDocument && $document->getPage() !== $this) {
            $document->setPage($this);
        }

        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->document = $document;

        return $this;
    }

    /** @return Collection<int, BuilderPageRevision> */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(BuilderPageRevision $revision): self
    {
        if (!$this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setPage($this);
        }

        return $this;
    }

    public function removeRevision(BuilderPageRevision $revision): self
    {
        if ($this->revisions->removeElement($revision) && $revision->getPage() === $this) {
            // Owning side keeps the association until Doctrine removes the row.
        }

        return $this;
    }
}
