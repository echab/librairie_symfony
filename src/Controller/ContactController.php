<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Message;
use App\Form\MessageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;

class ContactController extends AbstractController
{
    public function __construct(
        protected string $mailContact,
    ) {}

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        // TransportInterface $mailer, // send synchronously
        \Psr\Log\LoggerInterface $logger,
    ): Response {
        $commande = $request->getPayload()->getString('commande');

        $message = new Message();
        if (\strlen($commande)) {
            $message->sujet = 'Commande client';
            $message->message = $commande;
        }
        $form = $this->createForm(MessageType::class, $message);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = (new Email())
                ->to($this->mailContact)
                ->from("$message->nom <$message->email>")
                ->subject("[Contact] $message->sujet")
                ->text("$message->message\n\n$message->telephone");

            try {
                $mailer->send($email);
                return $this->redirectToRoute('message_success');
            } catch (TransportExceptionInterface $e) {
                $logger->error("mailer $e");
                $this->addFlash('error','🔺Erreur lors de l’envoi du message.');
            }      
        }

        return $this->render('contact.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/contact/envoye', name: 'message_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $this->addFlash('success','Votre message a bien été envoyé. Merci.');
        return $this->render('contact.html.twig', [
            'form' => null,
        ]);
    }
}
