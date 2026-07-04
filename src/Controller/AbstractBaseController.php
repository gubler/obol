<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Lib\Bus\CommandBus;
use App\Lib\Bus\QueryBus;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractBaseController extends AbstractController
{
    public const string FLASH_SUCCESS = 'success';

    public const string FLASH_WARNING = 'warning';

    public const string FLASH_ERROR = 'error';

    public const string FLASH_NOTICE = 'notice';

    protected CommandBus $commandBus;

    protected QueryBus $queryBus;

    protected LoggerInterface $appLogger;

    protected TranslatorInterface $translator;

    #[Required]
    public function autowireBaseController(
        CommandBus $commandBus,
        QueryBus $queryBus,
        LoggerInterface $appLogger,
        TranslatorInterface $translator,
    ): void {
        $this->appLogger = $appLogger;
        $this->commandBus = $commandBus;
        $this->queryBus = $queryBus;
        $this->translator = $translator;
    }

    /**
     * The authenticated user. The whole app is authenticated-by-default (see ADR-0014), so any
     * controller action that runs is behind the firewall and always has one; the assertion narrows
     * the framework's ?UserInterface to our User for the owner-threading call sites.
     */
    protected function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    protected function logFormErrors(FormInterface $form): void
    {
        $errors = [];

        foreach ($form->all() as $fieldName => $formField) {
            foreach ($formField->getErrors() as $error) {
                $errors[] = [
                    'field' => $fieldName,
                    'message' => $error->getMessage(),
                ];
            }
        }

        // Log error count and errors as JSON
        $this->appLogger->error('Form submission contains errors.', [
            'error_count' => \count($errors),
            'errors' => $errors,
        ]);
    }
}
