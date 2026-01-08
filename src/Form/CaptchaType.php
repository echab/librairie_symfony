<?php

namespace App\Form;

use App\Captcha\CaptchaInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CaptchaType extends AbstractType
{
    /** @var string */ protected const SESSION_KEY = 'captcha_answer';

    protected CaptchaInterface $captcha;
    
    protected SessionInterface $session;

    protected string $question;

    protected ?string $previousAnswer;

    protected string $nextAnswer;

    public function __construct(
        RequestStack $requestStack,
        #[AutowireIterator('captcha_challenge')]\Traversable $captchas
    ) {
        $captchas = iterator_to_array($captchas);
        $this->captcha = $captchas[array_rand($captchas)];
        $this->session = $requestStack->getSession();
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        list($this->question, $this->nextAnswer) = $this->captcha->getChallenge();
        $this->handleSession();
    }

    protected function handleSession()
    {
        $this->previousAnswer = $this->session->get(self::SESSION_KEY, null);
        $this->session->set(self::SESSION_KEY, $this->nextAnswer);
    }

    public function validateCaptcha($data, ExecutionContextInterface $context)
    {
        if (false === $this->captcha->checkAnswer($data, $this->previousAnswer)) {
            $context
                ->buildViolation('captcha_invalid')
                ->setTranslationDomain('messages')
                ->addViolation();
        }
    }
    
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['captcha_question'] = $this->question;
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'autocapitalize' => 'none',
                'autocorrect' => 'off',
                'spellcheck' => 'false',
            ],
            'constraints' => [
                new Callback([
                    'callback' => [$this, 'validateCaptcha'],
                ]),
            ],
            'mapped' => false,
            'required' => false,
        ]);
    }

    public function getParent(): ?string
    {
        return TextType::class;
    }
}
