<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\AdminBackupType;
use App\Form\AdminUnzipType;
use App\Form\AdminUploadType;
use App\Form\AdminUsersType;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    public function __construct(
        protected AdminService $service,
        protected string $projectDir,
    ) {}

    #[Route('/admin', name: 'admin', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(
        Request $request,
    ): Response {

        $backupDir = "$this->projectDir/backup";
        $oldDate = date_create('2016-01-01');

        $formBackup = $this->createForm(AdminBackupType::class, ['fromDate'=>$oldDate]);
        $formBackup->handleRequest($request);
        if ($formBackup->isSubmitted() && $formBackup->isValid()) {
            $fromDate = $formBackup->has('fromDate') ? $formBackup->get('fromDate')->getData() ?? $oldDate : $oldDate;
            $toDate = date_create();
            $zipFile = "librairie-posts-" . $fromDate->format('Y-m-d') . "-to-" . $toDate->format('Y-m-d-H-i') . ".zip";

            $this->addFlash('info', "Zipping into $zipFile");

            $gen = $this->service->zip(
                "$backupDir/$zipFile",
                $this->projectDir,
                ["posts/*/*.md", "public/uploads/images/*/*.{png,jpg,jpeg,gif}"],
                $fromDate
            );

            foreach ($gen as $message) {
                $this->addFlash('info', $message);
            }
            if ($gen->getReturn()) {
                $this->addFlash('success', "Zipping $zipFile done");
            } else {
                $this->addFlash('error', "🔺Error zipping $zipFile");
            };
        }

        $numFiles = 0;
        $formUpload = $this->createForm(AdminUploadType::class);
        $formUpload->handleRequest($request);
        if ($formUpload->isSubmitted() && $formUpload->isValid()) {
            /** @var ?UploadedFile */ $uploadFile = $formUpload->get('uploadedZip')->getData();
            $zipFile = $uploadFile->getClientOriginalName();

            $newfile = $uploadFile->move($backupDir, $zipFile);

            $this->addFlash('success', "File loaded: $zipFile");
        }

        $zipFiles = glob("$backupDir/librairie*.zip", GLOB_NOSORT);
        usort($zipFiles, fn($a, $b) => filemtime($b) - filemtime($a));
        $zipDates = array_map(fn($f) => date('Y-m-d', filemtime($f)) .' : '. basename($f), $zipFiles);
        $zipFiles = array_map(basename(...), $zipFiles);
        $formUnzip = $this->createForm(AdminUnzipType::class, array_combine($zipDates, $zipFiles));
        $formUnzip->handleRequest($request);
        if ($formUnzip->isSubmitted() && $formUnzip->isValid()) {
            $zipFile = $formUnzip->get('zipFile')->getData();
            /** @var SubmitButton */ $downloadButton = $formUnzip->get('actions')->get('download');
            if ($downloadButton->isClicked()) {
                // download the zip
                return new BinaryFileResponse(
                    "$backupDir/$zipFile",
                    200,
                    ['Content-Type' => 'application/zip'],
                    true,
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    true,
                );
            } else {
                if (!preg_match('/^[a-zA-Z0-9_\.\-]+\.zip$/', $zipFile)) {
                    $this->addFlash('error', "Bad zip file $zipFile");
                } else {
                    $gen = $this->service->unzip("$backupDir/$zipFile", $this->projectDir, $numFiles);
                    foreach ($gen as $message) {
                        $this->addFlash('info', $message);
                    }
                    if ($gen->getReturn()) {
                        $this->addFlash('success', "Unzipping $zipFile done");
                    } else {
                        $this->addFlash('error', "🔺Error unzipping $zipFile");
                    };
                }
            }
        }

        return $this->render('admin.html.twig', [
            'formBackup' => $formBackup,
            'formUpload' => $formUpload,
            'formUnzip' => $formUnzip,
            'numFiles' => $numFiles,
        ]);
    }

    #[Route('/admin/user', name: 'adminUser', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function adminUser(
        Request $request,
        #[CurrentUser] UserInterface $loggedUser,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!($userProvider instanceof InMemoryUserProvider)) {
            throw $this->createAccessDeniedException('Unsupported user provider');
            // also for $user type inference
        }

        $formUsers = $this->createForm(AdminUsersType::class);
        if (!$isAdmin) {
            $formUsers->setData(['login' => $loggedUser->getUserIdentifier()]);
        }

        $formUsers->handleRequest($request);
        while ($formUsers->isSubmitted() && $formUsers->isValid()) {

            $userInfo = $formUsers->getData();
            try {
                $user = $userProvider->loadUserByIdentifier($isAdmin ? $userInfo['login'] : $loggedUser->getUserIdentifier());
            } catch (UserNotFoundException $ex) {
                $this->addFlash('error', 'Nom ou  mot de passe invalide');
                break;
            }

            if (!$passwordHasher->isPasswordValid($user, $userInfo['passwordOld'])) {
                $this->addFlash('error', 'Nom ou mot de passe invalide');
                break;
            }

            $userId = $user->getUserIdentifier();
            do {
                $hash = $passwordHasher->hashPassword($user, $userInfo['password']);
            } while (str_contains($hash, "'")); // no quote in hash

            // $this->addFlash('success', "Nouveau mot de passe crypté: $hash");

            if ($error = $this->service->savePassword($userId, $hash)) {
                $this->addFlash('error', $error);
                break;
            }

            $this->addFlash('success', "Nouveau mot de passe enregistré pour $userId");
            break; // exit of the one loop
        }

        return $this->render('admin_user.html.twig', [
            'formUsers' => $formUsers,
        ]);
    }
}
