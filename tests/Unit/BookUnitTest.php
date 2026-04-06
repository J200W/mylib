<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Language;
use PHPUnit\Framework\TestCase;

final class BookUnitTest extends TestCase
{
    public function testBookWithAuthorAndLanguage(): void
    {
        $author = (new Author())
            ->setFirstname('Victor')
            ->setLastname('Hugo');

        $language = (new Language())
            ->setCountry('Français')
            ->setShortcode('fr');

        $book = (new Book())
            ->setTitle('Les Misérables — édition test')
            ->setDescription('Résumé de démonstration pour PHPUnit.')
            ->setStock(12)
            ->setAuthor($author)
            ->setLanguage($language);

        $this->assertSame('Les Misérables — édition test', $book->getTitle());
        $this->assertSame(12, $book->getStock());
        $this->assertSame($author, $book->getAuthor());
        $this->assertSame($language, $book->getLanguage());
        $this->assertCount(0, $book->getCategory());
    }
}
