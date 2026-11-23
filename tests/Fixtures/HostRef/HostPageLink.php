<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Fixtures\HostRef;

use Doctrine\ORM\Mapping as ORM;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

/**
 * Host entity on the host's default entity manager with a many-to-one to a bundle page (W1-2): the host takes the page
 * with `$yourManager->getReference(BuilderPage::class, $id)` and its own flush writes the foreign key.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_page_link')]
class HostPageLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: BuilderPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false)]
        public BuilderPage $page,
    ) {
    }
}
