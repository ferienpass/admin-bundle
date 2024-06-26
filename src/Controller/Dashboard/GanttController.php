<?php

declare(strict_types=1);

/*
 * This file is part of the Ferienpass package.
 *
 * (c) Richard Henkenjohann <richard@ferienpass.online>
 *
 * For more information visit the project website <https://ferienpass.online>
 * or the documentation under <https://docs.ferienpass.online>.
 */

namespace Ferienpass\AdminBundle\Controller\Dashboard;

use Ferienpass\CoreBundle\Entity\EditionTask;
use Ferienpass\CoreBundle\Repository\EditionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class GanttController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly EditionRepository $editions)
    {
    }

    public function __invoke(): Response
    {
        return new Response();

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $now = new \DateTimeImmutable();
        $editions = [];

        foreach ($this->editions->findAll() as $edition) {
            $tasks = [];
            foreach ($edition->getTasks() as $task) {
                if (null === $task->getPeriodEnd() || null === $task->getPeriodBegin()) {
                    continue;
                }

                // Do not show tasks that are past 30 days
                if ($task->getPeriodEnd() < $now && $task->getPeriodEnd()->diff($now)->days > 30) {
                    continue;
                }

                $tasks[] = [
                    'id' => (string) $task->getId(),
                    'name' => $this->getTitle($task),
                    'start' => $task->getPeriodBegin()->format('Y-m-d H:i'),
                    'end' => $task->getPeriodEnd()->format('Y-m-d H:i'),
                    'description' => $this->getDescription($task),
                    'progress' => $task->getProgress(),
                    'dependencies' => implode(', ', $task->getDependencies()->map(fn (EditionTask $t) => $t->getId())->toArray()),
                    'editLink' => $this->translator->trans('EditionTask.edit.0', [], 'contao_EditionTask'),
                    'editTitle' => $this->translator->trans('EditionTask.edit.1', [$task->getId()], 'contao_EditionTask'),
                    'editHref' => $this->generateUrl('contao_backend', ['do' => 'editions', 'table' => 'EditionTask', 'pid' => $edition->getId(), 'act' => 'edit', 'id' => $task->getId()]),
                ];
            }

            if (!empty($tasks)) {
                $editions[] = [
                    'edition' => $edition,
                    'tasks' => $tasks,
                ];
            }
        }

        if (empty($editions)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('@FerienpassAdmin/fragment/dashboard/gantt.html.twig', [
            'editions' => $editions,
        ]);
    }

    private function getTitle(EditionTask $task): string
    {
        $title = $this->translator->trans('EditionTask.type_options.'.$task->getType(), [], 'contao_EditionTask');
        if ('custom' === $task->getType()) {
            $title = (string) $task->getTitle();
        }
        if ($task->isAnApplicationSystem()) {
            $title = $this->translator->trans('MSC.application_system.'.$task->getApplicationSystem(), [], 'contao_default');
        }

        return $title;
    }

    private function getDescription(EditionTask $task): string
    {
        if ('custom' === $task->getType()) {
            return (string) $task->getDescription();
        }

        if ($task->isAnApplicationSystem()) {
            return $this->translator->trans('MSC.welcome_gantt.task_description.application_system.'.$task->getApplicationSystem(), [], 'contao_default');
        }

        return $this->translator->trans('MSC.welcome_gantt.task_description.'.$task->getType(), [], 'contao_default');
    }
}
