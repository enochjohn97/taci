<?php
// src/Service/ReceiptService.php

namespace App\Service;

use App\Entity\Sale;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Twig\Environment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReceiptService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Environment $twig,
        private ParameterBagInterface $params
    ) {}

    public function generateSaleReceipt(Sale $sale): string
    {
        $html = $this->twig->render('pos/receipt.html.twig', ['sale' => $sale]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'receipt_sale_' . $sale->getId() . '_' . time() . '.pdf';
        $dir = $this->params->get('kernel.project_dir') . '/public/receipts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $filename;
        file_put_contents($path, $dompdf->output());

        // Save path on sale
        $sale->setReceiptPath('/receipts/' . $filename);
        $this->em->persist($sale);
        $this->em->flush();

        return '/receipts/' . $filename;
    }
}
