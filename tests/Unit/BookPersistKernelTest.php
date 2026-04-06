<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BookPersistKernelTest extends KernelTestCase
{
    public function testPersistNewBookInDatabase(): void
    {
        $this->bootKernel();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $author = $em->getRepository(Author::class)->findOneBy([]);
        $language = $em->getRepository(Language::class)->findOneBy([]);
        $this->assertNotNull($author);
        $this->assertNotNull($language);

        $repo = $em->getRepository(Book::class);
        $countBefore = $repo->count([]);

        $title = 'Book PHPUnit '.uniqid();
        $book = (new Book())
            ->setTitle($title)
            ->setStock(3)
            ->setAuthor($author)
            ->setLanguage($language);

        $em->persist($book);
        $em->flush();

        $this->assertSame($countBefore + 1, $repo->count([]));
        $this->assertNotNull($repo->findOneBy(['title' => $title]));
    }
}
