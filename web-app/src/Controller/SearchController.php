<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Memo;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $em): Response
    {
        $query = trim($request->query->get('q', ''));
        
        if (empty($query)) {
            return $this->render('search/index.html.twig', [
                'query' => $query,
                'staff' => [],
                'memos' => [],
                'suppliers' => [],
            ]);
        }

        // Search Staff (Users)
        $staff = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Search Memos
        $memos = $em->getRepository(Memo::class)->createQueryBuilder('m')
            ->where('m.subject LIKE :query OR m.body LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Search Suppliers
        $suppliers = $em->getRepository(Supplier::class)->createQueryBuilder('s')
            ->where('s.name LIKE :query OR s.contact LIKE :query OR s.address LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'staff' => $staff,
            'memos' => $memos,
            'suppliers' => $suppliers,
        ]);
    }
}
