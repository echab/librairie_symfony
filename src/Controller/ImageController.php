<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ImageType;
use App\Service\ImageRepository;
use App\Service\PostRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #[Route(Constant::BASE_URL)]
class ImageController extends AbstractController
{
    public function __construct(
        private PostRepository $posts,
        private ImageRepository $images,
        private LoggerInterface $logger,
    ) {}

    #[Route(['/photos'], name: 'photos', methods: ['GET'])]
    public function photos(int $page = 1): Response
    {
        $photos = $this->posts->findLast([
            'categories' => ['photos'],
            'limit' => 10,
        ]);

        return $this->render('photos.html.twig', [
            'photos' => $photos,
        ]);
    }

    #[Route(['/photos_old'], name: 'photos_old', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function photosOld(
        #[MapQueryParameter] string $folder = '2022/11',
        #[MapQueryParameter] string $size = '150',
    ): Response {
        $projectDir = $this->getParameter('kernel.project_dir');
        $uploads = "$projectDir/wp-content/uploads";
        // $uploads = 'C:\Users\chabaud\Documents\perso\Librairie\wordpress_lp\wp-content\uploads';
        $base = 'https://librairie-lespassantes.fr';

        $files = iterator_to_array(\App\Service\Util::recursiveFileIterator(
            "$uploads/$folder",
            "/(.+)(?:-\d+x$size|-$size"."x\d+)\.(png|jpg|jpeg)\$/i"
        ));
        $n = \count($files);

        $fileNames = array_map(
            fn($f) => [
                str_replace('\\', '/', substr("$f[1].$f[2]", \strlen($uploads) + 1)),
                str_replace('\\', '/', substr("$f[0]", \strlen($uploads) + 1)),
            ],
            $files
        );

        $gallery = implode('', array_map(fn($f) =>
            "<a href='$base/wp-content/uploads/$f[0]'><img src='$base/wp-content/uploads/$f[1]'></a>", $fileNames
        ));

        return new Response("<!DOCTYPE html><html><head><meta name='color-scheme' content='light dark'/></head><body><h1>Les $n photos de $folder</h1>$gallery</body></html>");
    }

    #[Route('/edit/images', name: 'post_images', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function editImages(
        Request $request,
    ): Response {

        $formImage = $this->createForm(ImageType::class);

        $formImage->handleRequest($request);
        if ($formImage->isSubmitted() && $formImage->isValid()) {
            /** @var UploadedFile|null */
            $imageFile = $formImage->get('image')->getData();
            $errors = $this->images->save($imageFile);
            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }
            if (!\count($errors)) {
                $name = $imageFile->getClientOriginalName();
                $this->addFlash('success', "✅ Image $name enregistrée.");
            }
        }

        $lastImages = $this->images->findLast(10);

        return $this->render('image_gallery.html.twig', [
            'formImage' => $formImage,
            'images' => $lastImages,
        ]);
    }

    #[Route(['/clearImageCache'], name: 'post_clear_cache_image', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function clearImageCache(): Response
    {
        $this->images->clearCache();
        return $this->redirectToRoute('post_images');
    }
}
