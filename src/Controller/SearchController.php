<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Livre;
use App\Service\StockRepository;
use App\Entity\Post;
use App\Service\PostRepository;
use App\Service\Util;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class SearchController extends AbstractController
{
    public function __construct(
        private StockRepository $stock,
        private PostRepository $posts,
    ) {}

    #[Route('/cherche', name: 'search', methods: ['GET'])]
    public function index(
        #[MapQueryParameter] string $mot = '',
        #[MapQueryParameter] ?int $page = 1,
    ): Response
    {
        $limit = 10;
        // $offset = (abs($page) - 1) * $limit; // TODO page
        $mot = trim($mot ?? '');

        $posts = $this->posts->find([
            'mot' => $mot,
            'notCategories' => ['a-la-une', 'info', 'agenda', 'photos', 'inconnu'],
            // 'offset' => $offset,
            'limit' => $limit,
        ]);

        $posts = iterator_to_array($posts);

        if (\count($posts) < $limit) {
            $filter = Util::searchPredicate($mot);
            $livres = $this->stock->findAll(0, $filter, 0, $limit - \count($posts));
            // TODO remove $livres with ean already in $posts
            if (\count($livres)) {
                array_push( $posts, ...$livres);
            }
        }

        return $this->render('search.html.twig', [
            'posts' => $posts,
            'mot' => $mot,
            'page' => $page,
        ]);
    }
}