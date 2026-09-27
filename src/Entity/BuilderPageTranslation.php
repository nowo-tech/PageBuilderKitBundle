<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pb_page_translation')]
#[ORM\UniqueConstraint(name: 'pb_page_translation_page_locale', columns: ['page_id', 'locale'])]
class BuilderPageTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $locale = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ogTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ogDescription = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $ogImage = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $robots = null;

    #[ORM\ManyToOne(inversedBy: 'translations', targetEntity: BuilderPage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BuilderPage $page;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->slug = $slug;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $ogTitle): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->ogTitle = $ogTitle;

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->ogDescription = $ogDescription;

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function setOgImage(?string $ogImage): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->ogImage = $ogImage;

        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->canonicalUrl = $canonicalUrl;

        return $this;
    }

    public function getRobots(): ?string
    {
        return $this->robots;
    }

    public function setRobots(?string $robots): self
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->robots = $robots;

        return $this;
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
}
