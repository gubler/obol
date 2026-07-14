<?php

// ABOUTME: GET/POST /app/admin/system-toggles - the admin hub's System Toggles section, behind ROLE_ADMIN.
// ABOUTME: Shows the runtime system settings and flips public signup; reads via the query bus, writes via commands.

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractBaseController;
use App\Dto\Admin\SystemTogglesData;
use App\Entity\SystemSettings;
use App\Form\Admin\SystemTogglesFormType;
use App\Message\Command\System\SetPublicSignupCommand;
use App\Message\Query\System\GetSystemSettingsQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SystemTogglesController extends AbstractBaseController
{
    #[IsGranted(attribute: 'ROLE_ADMIN')]
    #[Route(path: '/app/admin/system-toggles', name: 'admin_system_toggles', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $settings = $this->queryBus->query(new GetSystemSettingsQuery());
        \assert($settings instanceof SystemSettings);

        $data = new SystemTogglesData();
        $data->publicSignupEnabled = $settings->publicSignupEnabled;

        $form = $this->createForm(SystemTogglesFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new SetPublicSignupCommand(enabled: $data->publicSignupEnabled));

            $this->addFlash(self::FLASH_SUCCESS, $this->translator->trans('admin.system_toggles.updated'));

            return $this->redirectToRoute('admin_system_toggles');
        }

        return $this->render('admin/system_toggles.html.twig', [
            'form' => $form,
        ]);
    }
}
