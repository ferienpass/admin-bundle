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

namespace Ferienpass\AdminBundle\Controller\Page;

use Doctrine\ORM\EntityManagerInterface;
use Ferienpass\AdminBundle\Breadcrumb\Breadcrumb;
use Ferienpass\AdminBundle\Export\ExportQueryBuilderInterface;
use Ferienpass\AdminBundle\Form\EditHostType;
use Ferienpass\AdminBundle\Form\Filter\HostsFilter;
use Ferienpass\AdminBundle\Service\FileUploader;
use Ferienpass\CoreBundle\Entity\Host;
use Ferienpass\CoreBundle\Pagination\Paginator;
use Ferienpass\CoreBundle\Repository\HostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
#[Route('/veranstaltende')]
final class HostsController extends AbstractController
{
    public function __construct(#[Autowire(service: 'ferienpass.file_uploader.logos')] private readonly FileUploader $fileUploader, #[TaggedLocator(ExportQueryBuilderInterface::class, defaultIndexMethod: 'getFormat')] private readonly ServiceLocator $exporters)
    {
    }

    #[Route('', name: 'admin_hosts_index')]
    public function index(HostRepository $repository, Request $request, Breadcrumb $breadcrumb): Response
    {
        $qb = $repository->createQueryBuilder('i');

        $paginator = (new Paginator($qb))->paginate($request->query->getInt('page', 1));

        return $this->render('@FerienpassAdmin/page/hosts/index.html.twig', [
            'qb' => $qb,
            'filterType' => HostsFilter::class,
            'exports' => array_keys($this->exporters->getProvidedServices()),
            'searchable' => ['name'],
            'createUrl' => $this->generateUrl('admin_hosts_create'),
            'pagination' => $paginator,
            'breadcrumb' => $breadcrumb->generate('hosts.title'),
        ]);
    }

    #[Route('/export.{format}', name: 'admin_hosts_export', requirements: ['format' => '\w+'])]
    public function export(HostRepository $repository, string $format)
    {
        $qb = $repository->createQueryBuilder('i');

        if (!$this->exporters->has($format)) {
            throw $this->createNotFoundException();
        }

        $exporter = $this->exporters->get($format);

        return $this->file($exporter->generate($qb), "veranstaltende.$format");
    }

    #[Route('/neu', name: 'admin_hosts_create')]
    #[Route('/{alias}/bearbeiten', name: 'admin_hosts_edit')]
    public function edit(?Host $host, Request $request, EntityManagerInterface $em, Breadcrumb $breadcrumb, \Ferienpass\CoreBundle\Session\Flash $flash): Response
    {
        $form = $this->createForm(EditHostType::class, $host ?? new Host());

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$em->contains($host = $form->getData())) {
                $em->persist($host);
            }

            $imageFile = $form->get('logo')->getData();
            if ($imageFile) {
                $imageFileName = $this->fileUploader->upload($imageFile);
                $host->setLogo($imageFileName);
            }

            $em->flush();

            $flash->addConfirmation(text: new TranslatableMessage('editConfirm', domain: 'admin'));

            return $this->redirectToRoute('admin_hosts_edit', ['alias' => $host->getAlias()]);
        }

        $breadcrumbTitle = $host ? $host->getName().' (bearbeiten)' : 'hosts.new';

        /** @noinspection FormViewTemplate `createView()` messes ups error handling/redirect */
        return $this->render('@FerienpassAdmin/page/hosts/edit.html.twig', [
            'item' => $host,
            'form' => $form,
            'breadcrumb' => $breadcrumb->generate(['hosts.title', ['route' => 'admin_hosts_index']], $breadcrumbTitle),
        ]);
    }

    #[Route('/{alias}/löschen', name: 'admin_hosts_delete', requirements: ['id' => '\d+'])]
    public function delete(Host $item, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $item);

        $form = $this->createForm(FormType::class, options: [
            'action' => $this->generateUrl('admin_hosts_delete', ['alias' => $item->getAlias()]),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($item);
            $entityManager->flush();
        }

        return $this->render('@FerienpassAdmin/page/hosts/delete.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }
}
