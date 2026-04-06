<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Langues, auteurs, catégories et livres du catalogue.
 */
final class CatalogFixtures extends Fixture
{
    public const REF_BOOK_PREFIX = 'book.';

    public function load(ObjectManager $manager): void
    {
        $languages = $this->createLanguages($manager);
        $authors = $this->createAuthors($manager);
        $categories = $this->createCategories($manager);
        $this->createBooks($manager, $languages, $authors, $categories);

        $manager->flush();
    }

    /**
     * @return array<string, Language>
     */
    private function createLanguages(ObjectManager $manager): array
    {
        $defs = [
            'fr' => ['country' => 'Français', 'shortcode' => 'fr'],
            'en' => ['country' => 'Anglais', 'shortcode' => 'en'],
            'es' => ['country' => 'Espagnol', 'shortcode' => 'es'],
            'de' => ['country' => 'Allemand', 'shortcode' => 'de'],
        ];

        $map = [];
        foreach ($defs as $key => $data) {
            $language = (new Language())
                ->setCountry($data['country'])
                ->setShortcode($data['shortcode']);
            $manager->persist($language);
            $map[$key] = $language;
        }

        return $map;
    }

    /**
     * @return list<Author>
     */
    private function createAuthors(ObjectManager $manager): array
    {
        $defs = [
            ['Victor', 'Hugo'],
            ['Guy', 'de Maupassant'],
            ['Jean-Baptiste', 'Poquelin'],
            ['Jean', 'Racine'],
            ['George', 'Orwell'],
            ['Jane', 'Austen'],
        ];

        $authors = [];
        foreach ($defs as [$firstname, $lastname]) {
            $author = (new Author())
                ->setFirstname($firstname)
                ->setLastname($lastname);
            $manager->persist($author);
            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(ObjectManager $manager): array
    {
        $names = [
            'roman' => 'Roman',
            'poesie' => 'Poésie',
            'theatre' => 'Théâtre',
            'essai' => 'Essai',
            'classique' => 'Classique',
            'science-fiction' => 'Science-fiction',
        ];

        $map = [];
        foreach ($names as $key => $name) {
            $category = (new Category())->setName($name);
            $manager->persist($category);
            $map[$key] = $category;
        }

        return $map;
    }

    /**
     * @param array<string, Language> $languages
     * @param list<Author>             $authors
     * @param array<string, Category>  $categories
     */
    private function createBooks(
        ObjectManager $manager,
        array $languages,
        array $authors,
        array $categories,
    ): void {
        $booksData = [
            [
                'id' => 1,
                'title' => 'Les Misérables',
                'description' => 'Fresque monumentale de la littérature française, ce chef-d’œuvre suit le destin de Jean Valjean, ancien forçat en quête de rédemption, confronté à l’implacable inspecteur Javert dans une France déchirée par les révoltes sociales du XIXe siècle.',
                'stock' => 12,
                'author' => 0,
                'language' => 'fr',
                'cats' => ['roman', 'classique'],
            ],
            [
                'id' => 2,
                'title' => 'Notre-Dame de Paris',
                'description' => 'Au cœur du Paris médiéval, ce récit tragique lie les destins de la mystérieuse Esmeralda, du tourmenté archidiacre Frollo et du sonneur Quasimodo, faisant de la cathédrale elle-même un personnage vivant et témoin des passions humaines.',
                'stock' => 8,
                'author' => 0,
                'language' => 'fr',
                'cats' => ['roman', 'classique'],
            ],
            [
                'id' => 3,
                'title' => 'Bel-Ami',
                'description' => 'L’ascension fulgurante de Georges Duroy, un jeune homme ambitieux et sans scrupules qui utilise son charme pour gravir les échelons du pouvoir et de la presse dans le Paris mondain et corrompu de la IIIe République.',
                'stock' => 15,
                'author' => 1,
                'language' => 'fr',
                'cats' => ['roman', 'classique'],
            ],
            [
                'id' => 4,
                'title' => 'Le Tartuffe',
                'description' => 'Dans cette comédie grinçante devenue légendaire, Molière dénonce l’imposture et la fausse dévotion à travers le personnage de Tartuffe, un hypocrite qui s’immisce dans une famille bourgeoise pour mieux la dépouiller.',
                'stock' => 20,
                'author' => 2,
                'language' => 'fr',
                'cats' => ['theatre', 'classique'],
            ],
            [
                'id' => 5,
                'title' => 'Britannicus',
                'description' => 'Une tragédie racinienne d’une intensité rare qui dépeint la naissance d’un monstre : le jeune Néron. Entre soif de pouvoir et jalousie amoureuse, la cour impériale devient le théâtre d’un huis clos étouffant et meurtrier.',
                'stock' => 6,
                'author' => 3,
                'language' => 'fr',
                'cats' => ['theatre', 'classique'],
            ],
            [
                'id' => 6,
                'title' => '1984',
                'description' => 'Le chef-d’œuvre absolu de la dystopie. Dans un monde dominé par Big Brother et la surveillance constante, Winston Smith tente de préserver son humanité et sa liberté de pensée face à un système totalitaire qui réécrit l’histoire.',
                'stock' => 25,
                'author' => 4,
                'language' => 'en',
                'cats' => ['roman', 'science-fiction'],
            ],
            [
                'id' => 7,
                'title' => 'Orgueil et Préjugés',
                'description' => 'Un classique intemporel explorant les mœurs de la petite gentry anglaise. À travers les joutes verbales entre l’indépendante Elizabeth Bennet et le ténébreux Mr Darcy, Jane Austen livre une critique subtile de la condition féminine et des classes sociales.',
                'stock' => 18,
                'author' => 5,
                'language' => 'en',
                'cats' => ['roman', 'classique'],
            ],
            [
                'id' => 8,
                'title' => 'Pauca meae',
                'description' => 'Composant le livre IV des Contemplations, ce recueil est l’un des plus poignants de la langue française. Victor Hugo y crie sa douleur et son deuil après la perte tragique de sa fille Léopoldine, entre désespoir et quête de paix spirituelle.',
                'stock' => 4,
                'author' => 0,
                'language' => 'fr',
                'cats' => ['poesie', 'classique'],
            ],
        ];

        foreach ($booksData as $data) {
            $book = (new Book())
                ->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setStock($data['stock'])
                ->setAuthor($authors[$data['author']])
                ->setLanguage($languages[$data['language']]);

            foreach ($data['cats'] as $catKey) {
                $book->addCategory($categories[$catKey]);
            }

            $manager->persist($book);
            $this->addReference(self::REF_BOOK_PREFIX.$data['id'], $book);
        }
    }
}
