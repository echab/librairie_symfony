<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Rayon;
use App\Form\PostType;
use App\Service\ImageRepository;
use App\Service\PostRepository;
use App\Service\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #[Route(Constant::BASE_URL)]
class PostController extends AbstractController
{
    public function __construct(
        private PostRepository $posts,
        private ImageRepository $images,
        private LoggerInterface $logger,
    ) {}

    #[Route(['/error'], name: 'errorTest', methods: ['GET'])]
    public function errorTest(Request $request): Response
    {
        // return new Response("For test in post", 503);
        // throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, "For test in post");
        // throw $this->createNotFoundException("For test in post");
        throw new \Exception("Error for test in post");
    }

    #[Route(['/'], name: 'home', methods: ['GET'])]
    public function home(int $page = 1): Response
    {
        $unes = $this->posts->findLast([
            'categories' => ['a-la-une'],
            'limit' => 2,
        ]);

        $infos = $this->posts->findLast([
            'categories' => ['info'],
            'limit' => 10,
        ]);

        $agendas = $this->posts->findLast([
            'categories' => ['agenda'],
            'limit' => 3,
        ]);

        return $this->render('home.html.twig', [
            'unes' => $unes,
            'infos' => $infos,
            'agendas' => $agendas,
            'rayons' => Rayon::$rayons,
        ]);
    }

    #[Route(['/agenda'], name: 'agenda', methods: ['GET'])]
    public function agenda(int $page = 1): Response
    {
        $agendas = $this->posts->findLast([
            'categories' => ['agenda'],
            'limit' => 10,
        ]);

        return $this->render('agenda.html.twig', [
            'agendas' => $agendas,
        ]);
    }

    #[Route(['/post/{category}/{slug}'], name: 'post_view', methods: ['GET'], requirements: ['category' => Util::IS_SLUG, 'slug' => Util::IS_SLUG])]
    public function view(string $slug, string $category): Response
    {
        $post = $this->posts->findOne($slug, $category);

        if (!$post) {
            throw $this->createNotFoundException('Post introuvable');
        }

        return $this->render('post_view.html.twig', [
            'post' => $post,
            'category' => $category,
        ]);
    }

    #[Route('/edit/post/{category}', name: 'post_edit', methods: ['GET', 'POST'], requirements: ['category' => Util::IS_SLUG])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        #[CurrentUser] UserInterface $user,
        string $category,
        #[MapQueryParameter] ?string $slug = null,
    ): Response {

        if (empty($slug)) {
            $post = new Post(category: $category);
            $post->date = date_create();
            $post->libraire = $user->getUserIdentifier();
        } else {
            $post = $this->posts->findOne($slug, $category);
        }

        $prevPost = clone $post; // clone to detect category change

        $form = $this->createForm(PostType::class, $post);
        $form->remove('livre'); // le post ne concerne pas un livre (voir CoeurController)

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->posts->save($post, $prevPost);

            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }

            return $this->redirectToRoute('post_view', [
                'category' => $post->category,
                'slug' => $post->slug,
            ]);
        }

        return $this->render('post_edit.html.twig', [
            'form' => $form,
            'category' => $category,
            'slug' => $slug,
            'is_edit' => !empty($slug) || $post === null,
        ]);
    }

    #[Route(['/edit/preview/post'], name: 'post_preview', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function preview(
        Request $request,
    ): Response {
        $post = new Post(Rayon::$defaut->slug);
        $form = $this->createForm(PostType::class, $post, ['allow_extra_fields' => true]);
        $form->remove('category'); // pour un livre, pas une des categories

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->render('post_preview.html.twig', [
                'post' => $post,
            ]);
        } else {
            $this->logger->error('preview données invalides: '. $form->getErrors(true, false)->__toString());
            return new Response('Erreur, données invalides ! '. $form->getErrors(true, false)->__toString());
        }
    }

    #[Route(['/clear', '/cache'], name: 'post_clear', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function clearCache(): Response
    {
        $this->posts->clearCache();
        $this->images->clearCache();
        return $this->redirectToRoute('home');
    }
}
