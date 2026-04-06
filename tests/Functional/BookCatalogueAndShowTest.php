<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookCatalogueAndShowTest extends WebTestCase
{
    public function testCataloguePageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/book/catalogue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testBookShowPageDisplaysWorkFromCatalogue(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $book = $em->getRepository(Book::class)->findOneBy([]);
        $this->assertNotNull($book, 'Le catalogue de test doit contenir au moins un livre.');
        $id = $book->getId();
        $this->assertNotNull($id);

        $crawler = $client->request('GET', '/book/'.$id);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', (string) $book->getTitle());
    }
}
