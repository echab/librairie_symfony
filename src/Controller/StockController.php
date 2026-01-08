<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Livre;
use App\Service\StockRepository;
use App\Entity\Rayon;
use App\Service\PostRepository;
use App\Service\Util;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class StockController extends AbstractController
{
    public function __construct(
        private StockRepository $stock,
        private PostRepository $posts,
    ) {}

    #[Route('/livresSolid', name: 'livresSolid', methods: ['GET'])]
    public function indexSolid(): Response
    {
        return $this->render('livresSolid.html.twig');
    }

    #[Route('/livres', name: 'livres', methods: ['GET'])]
    public function index(
        Request $request,
        #[MapQueryParameter] ?string $mot,
        #[MapQueryParameter] ?int $rayon = 0,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => '/^[tadp]$/'])] ?string $tri = 'd',
        #[MapQueryParameter] ?int $page = 1,
    ): Response {
        $limit = 20;
        $offset = (abs($page) - 1) * $limit;
        $mot = trim($mot ?? '');

        /** @var array<Livre> */ $livres = [];
        /** @var array<Livre> */ $livresAutre = [];
        /** @var ?int */ $posAutres = null;

        $ean = Util::toEan($mot);
        if ($ean) {
            $mot = (string)$ean;
            $livre = $this->stock->findByEAN($ean);
            if ($livre) {
                $livres[] = $livre;
            }
        } else {
            $filter = Util::searchPredicate($mot);
            $livres = $this->stock->findAll($rayon ?? 0, $filter, 0, 10000);

            if ($rayon > 0 && count($livres) < $limit) {
                $posAutres = count($livres) - $offset;
                $livresAutre = $this->stock->findAll(-$rayon, $filter, 0, 10000);
            }
        }

        $nLivresRayon = count($livres);
        $nLivres = $nLivresRayon + count($livresAutre);
        $nPage = ceil($nLivres / $limit);

        $countByRayon = [
            0 => $nLivres,
        ];
        foreach ($livres as $livre) {
            $countByRayon[$livre->rayon] = ($countByRayon[$livre->rayon] ?? 0) + 1;
        }
        foreach ($livresAutre as $livre) {
            $countByRayon[$livre->rayon] = ($countByRayon[$livre->rayon] ?? 0) + 1;
        }

        if ($tri !== 'd') {
            $by = [
                't' => fn(Livre $a, Livre $b) => strcasecmp($a->titre, $b->titre),
                'a' => fn(Livre $a, Livre $b) => strcasecmp($a->auteur, $b->auteur),
                'p' => fn(Livre $a, Livre $b) => (int)($a->prix * 100 - $b->prix * 100),
            ];
            usort($livres, $by[$tri]);
            usort($livresAutre, $by[$tri]);
        }

        $livresPage = array_slice(array_merge($livres, $livresAutre), $offset, $limit);

        $coeurByEan = $this->posts->findByEans(array_map(fn($livre) => $livre->ean, $livresPage));

        return $this->render('livres.html.twig', [
            'livres' => $livresPage,
            'posAutres' => $posAutres,
            'rayon' => $rayon,
            'rayonObj' => Rayon::byCode($rayon),
            'rayons' => Rayon::$rayons,
            'rayonByCode' => Rayon::$byCode,
            'countByRayon' => $countByRayon,
            'mot' => $mot,
            'mots' => mb_split("[^a-zA-Z0-9]+", Util::replace_accents($mot)),
            'tri' => $tri,
            'recentDate' => date_create()->sub(\DateInterval::createFromDateString('30 days')),
            'stockDate' => $this->stock->stockDate ?? null,
            'ageStock' => (date_create()->getTimestamp() - ($this->stock->stockDate ?? date_create())->getTimestamp()) / 3600,
            'coeurByEan' => $coeurByEan,
            'queryParams' => $request->query,
            'page' => $page,
            'nPage' => $nPage,
        ]);
    }

    #[Route(["/api/livres{ext<\.json>}", "/wp-json/lp/v1/livres{ext<\.json>}"], name: 'stock_get_api', methods: ['GET'], format: 'json', defaults: ['ext' => '.json'])]
    public function stock_get_api(string $_format, Request $request): Response
    {
        //find more recent stock data file
        $stockFile = $this->stock->findStockFile();
        if (!$stockFile) {
            throw $this->createNotFoundException('Pas de fichier de stock');
        }

        $fileContent = file_get_contents($stockFile);
        $stockDate = ($this->stock->stockDate ?? date_create())->format('Y-m-d H:i:s');

        $response = new Response(
            $fileContent,
            Response::HTTP_OK,
            [
                'content-type' => 'application/json'
            ]
        );
        $response->setEtag(md5($stockDate));
        $response->setPublic();
        $response->isNotModified($request);

        $response->headers->set('X-Debug-Date', $stockDate);

        if (str_ends_with($stockFile, '.gz')) {
            $response->headers->set('Content-Encoding', 'gzip');
        }

        return $response;
    }

    #[Route(['/wp-json/lp/v1/livres{ext<\.json>}'], name: 'stock_post_api', methods: ['POST'], format: 'json', defaults: ['ext' => '.json'])]
    // TODO #[IsGranted('ROLE_STOCK')]
    public function stock_post_api(Request $request): Response
    {
        // check nonce
        $nonce = $request->getSession()->get('nonce');
        $request->getSession()->set('nonce', null); // consume the number once
        $nonce2 = $request->headers->get('X-WP-Nonce');
        if ($nonce2 === null || $nonce !== $nonce2) {
            throw $this->createAccessDeniedException('Bad nonce');
        }

        //upload a stock file
        /** @var UploadedFile $stockFiles */
        $stockFiles = $request->files->get('stock');
        $basename = $stockFiles->getClientOriginalName();

        $errors = $this->stock->saveStockFile($stockFiles);

        if (count($errors)) {
            return $this->json([
                'message' => "Erreur de chargement du fichier $basename",
                'errors' => $errors
            ]);
        }
        return $this->json(['message' => "$basename chargé avec succés"]);
    }

    #[Route(["/api/rayons{ext<\.json>}", "/wp-json/lp/v1/rayons{ext<\.json>}"], name: 'rayon_get_api', methods: ['GET'], format: 'json', defaults: ['ext' => '.json'])]
    public function rayon_get_api(string $_format, Request $request): Response
    {
        $response = $this->json(Rayon::$rayons);
        $response->setEtag('v1');
        return $response;
    }
}
