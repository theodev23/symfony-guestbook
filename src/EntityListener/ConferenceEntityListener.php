<?php
namespace App\EntityListener;

use App\Entity\Conference;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsEntityListener(event: Events::prePersist, entity: Conference::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Conference::class)]
class ConferenceEntityListener
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function prePersist(Conference $conference, PrePersistEventArgs $event): void
    {
        $conference->computeSlug($this->slugger);
    }

    public function preUpdate(Conference $conference, PreUpdateEventArgs $event): void
    {
        $conference->computeSlug($this->slugger);
    }
}