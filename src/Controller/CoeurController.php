<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Rayon;
use App\Form\PostType;
use App\Service\PostRepository;
use App\Service\StockRepository;
use App\Service\Util;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CoeurController extends AbstractController
{
    public function __construct(
        protected string $eanWebUrl,
        private PostRepository $posts,
        private StockRepository $stock,
    ) {}

    #[Route(['/coups-de-coeur'], name: 'coups-de-coeur', methods: ['GET'])]
    public function index(
        #[MapQueryParameter] int $page = 1,
    ): Response
    {
        $posts = $this->posts->findLast([
            'notCategories' => ['a-la-une', 'info', 'agenda', 'photos', 'inconnu'],
            'offset' => ($page - 1) * 10,
            'limit' => 10,
        ]);

        return $this->render('coups-de-coeur.html.twig', [
            'posts' => $posts,
            'page' => $page,
        ]);
    }

    #[Route(['/coups-de-coeur/{category}'], name: 'coups-de-coeur_rayon', methods: ['GET'], requirements: ['category' => Util::IS_SLUG])]
    public function indexCategory(
        string $category,
        #[MapQueryParameter] int $page = 1,
    ): Response
    {
        $rayon = Rayon::bySlug($category);
        if (!$rayon) {
            throw $this->createNotFoundException("Rayon $category inconnu.");
        }

        $posts = $this->posts->findLast([
            'categories' => isset($rayon->sousRayons)
                ?
                array_merge(
                    [$category],
                    array_map(fn($r) => $r->slug, $rayon->sousRayons),
                )
                : [$category],
            'offset' => ($page - 1) * 10,
            'limit' => 10,
        ]);

        return $this->render('coups-de-coeur_rayon.html.twig', [
            'posts' => $posts,
            'rayon' => $rayon,
            'category' => $category,
            'page' => $page,
        ]);
    }

    #[Route(['/coups-de-coeur/{category}/{slug}'], name: 'coups-de-coeur_view', methods: ['GET'], requirements: ['category' => Util::IS_SLUG, 'slug' => Util::IS_SLUG])]
    public function view(string $category, string $slug): Response
    {
        $post = $this->posts->find($slug, $category);

        if (!$post) {
            throw $this->createNotFoundException('Post introuvable');
        }

        return $this->render('coups-de-coeur_view.html.twig', [
            'post' => $post,
            'category' => $category,
        ]);
    }

    #[Route('/edit/coups-de-coeur/{category?}', name: 'coups-de-coeur_edit', methods: ['GET', 'POST'], requirements: ['category' => Util::IS_SLUG])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        #[CurrentUser] UserInterface $user,
        ?string $category = null,
        #[MapQueryParameter] ?string $slug = null,
        #[MapQueryParameter] ?string $ean = null
    ): Response {

        if (empty($slug)) {
            $post = null;
            if ($ean !== null) {
                $ean = Util::toEan($ean);
            }
            if ($ean !== null) {
                $post = $this->posts->findByEan($ean);
                if ($post) {
                    return $this->redirectToRoute(
                        'coups-de-coeur_edit',
                        ['category' => $post->category, 'slug' => $post->slug]
                    );
                }
                $postStock = self::postFromStock($ean);
                $post = self::postFromWeb($ean);

                if ($post) {
                    $post->markdown = "$post->markdown\n\n__Coup de cœur__\n\n";

                    if ($postStock) {
                        $post->category = $postStock->category;
                        $post->rayonCode = $postStock->rayonCode;
                        $post->prix = $postStock->prix;
                    }

                    $image_url = Util::formatUrl($request->server->get('IMAGE_COUV_MED_URL'), \strval($ean));
                    $post->markdown = "![couverture]($image_url#gauche)\n\n$post->markdown";
                }
            }
            $post ??= new Post(category: $category ?? Rayon::$defaut->slug);
            $post->ean ??= $ean;
            $post->date = date_create();
            $post->libraire = $user->getUserIdentifier();
        } else {
            $post = $this->posts->find($slug, $category);
        }

        $prevPost = clone $post; // clone to detect category change

        $form = $this->createForm(PostType::class, $post);
        $form->remove('category'); // la catégorie d'un livre est le sous-rayon

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post->category = ((isset($post->rayonCode) ? Rayon::byCode($post->rayonCode) : null) ?? Rayon::$defaut)->slug;

            $errors = $this->posts->save($post, $prevPost);

            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }

            return $this->redirectToRoute('coups-de-coeur_view', [
                'category' => $post->category,
                'slug' => $post->slug,
            ]);
        }

        return $this->render('coups-de-coeur_edit.html.twig', [
            'form' => $form,
            'category' => $category,
            'slug' => $slug,
            'ean' => $post->ean ?? $ean,
            'is_edit' => !empty($slug) || $post === null,
        ]);
    }

    #[Route(["/api/coeur{ext<\.json>}", "/wp-json/lp/v1/coeur{ext<\.json>}"], name: 'coeur_get_api', methods: ['GET'], format: 'json', defaults: ['ext' => '.json'])]
    public function coeur_get_api(
        #[MapQueryParameter] ?bool $content,
    ): Response {
        $posts = $this->posts->findLast([
            'categories' => Rayon::$slugs,
            'limit' => 100,
        ]);

        $result = array_map(function ($post) use ($content) {
            return [
                'slug' => $post->slug,
                'i' => $post->ean ?? 0,
                'd' => $post->date->format('Y-m-d'),
                't' => $content ? $post->markdown : null,
            ];
        }, iterator_to_array($posts));

        return $this->json($result);
    }

    protected function postFromStock(int $ean): ?Post
    {
        $livre = $this->stock->findByEAN($ean);
        if ($livre === null) {
            return null;
        }
        $rayon = Rayon::byCode($livre->rayon, true);
        $sousRayon = Rayon::byCode($livre->rayon, false);

        $post = new Post($rayon->slug);
        $post->date = date_create();
        $post->ean = $livre->ean;
        $post->titre = $livre->titre;
        $post->auteur = $livre->auteur;
        $post->editeur = $livre->editeur;
        $post->parution = $livre->parution;
        $post->prix = $livre->prix;

        $post->rayonCode = $sousRayon->code;
        $post->category = $rayon->slug;

        return $post;
    }
    protected function postFromWeb(int $ean): ?Post
    {
        $html = @file_get_contents(Util::formatUrl($this->eanWebUrl,\strval($ean)));
        if ($html === false) {
            return null;
        }

        //extract ld+json data
        $match = preg_match('/<script type="application\/ld\+json">\s*({[^{<]*"@type":"(Product|Book)"[\s\S]+?)<\/script>/ms', $html, $m);
        if ($match !== 1) {
            return null;
        }

        $ld = json_decode($m[1], false, 5);
        if (!$ld) {
            return null;
        }
        $auteur = $ld->author->name ?? '';
        //add author, missing in ld+json
        if (empty($auteur)) {
            $match = preg_match('/<div class="authors-container">(.+?)<\/div>/ms', $html, $m);
            if ($match === 1) {
                $auteur = htmlspecialchars_decode(trim(preg_replace('/<[^>]+>/', '', $m[1])));
            }
        }
        $post = new Post(Rayon::$defaut->slug);
        $post->ean = (isset($ld->sku) ? \intval($ld->sku) : null) ??
            (isset($ld->isbn) ? \intval(str_replace('-', '', $ld->isbn)) : null) ??
            $ean;
        $post->titre = preg_replace('/[-\s]*(Grand Format|Poche)/i', '', $ld->name);
        $post->auteur = $auteur;
        $post->editeur = $ld->brand->name ?? $ld->publisher->name;
        $post->prix = $ld->offers->price;
        $post->parution = new \DateTime($ld->releaseDate ?? $ld->datePublished);
        $post->markdown = str_replace("\n", "\n\n", decodeHtmlWithNewLines($ld->description));

        return $post;
    }
}

function decodeHtmlWithNewLines($h)
{
    return html_entity_decode(
        trim(
            strip_tags(
                preg_replace('/(<\/p>|<br\s*\/?>)/', "\n\\1", $h)
            )
        )
    );
}
