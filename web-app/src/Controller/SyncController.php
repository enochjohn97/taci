<?php
// src/Controller/SyncController.php

namespace App\Controller;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sync')]
class SyncController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'api_sync', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sync(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['transactions']) || !is_array($data['transactions'])) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $count = 0;
        $results = [];
        foreach ($data['transactions'] as $tx) {
            $txResult = ['offlineId' => $tx['offlineId'] ?? null, 'status' => 'logged', 'message' => null];

            // Basic dedup: if tx has offlineId, skip if already logged
            $offlineId = $tx['offlineId'] ?? null;
            $already = false;
            if ($offlineId) {
                $qb = $this->em->getRepository(AuditLog::class)->createQueryBuilder('a')
                    ->where('a.description LIKE :p')
                    ->setParameter('p', '%"offlineId":"' . addslashes($offlineId) . '"%')
                    ->setMaxResults(1);
                $already = (bool) $qb->getQuery()->getOneOrNullResult();
            }

            if ($already) {
                $txResult['status'] = 'duplicate';
                $results[] = $txResult;
                continue;
            }

            // If transaction looks like a sale, attempt to apply it server-side
            if (($tx['type'] ?? '') === 'sale' && isset($tx['items']) && is_array($tx['items'])) {
                $conn = $this->em->getConnection();
                try {
                    $conn->beginTransaction();

                    $sale = new \App\Entity\Sale();
                    $sale->setCashier($this->getUser());
                    $sale->setPaymentMethod($tx['paymentMethod'] ?? 'cash');
                    $sale->setDiscountAmount((float) ($tx['discountAmount'] ?? 0));
                    $sale->setLoyaltyPointsUsed((float) ($tx['loyaltyPointsUsed'] ?? 0));

                    $total = 0;
                    foreach ($tx['items'] as $it) {
                        $product = $this->em->getRepository(\App\Entity\Product::class)->find($it['productId']);
                        if (!$product) throw new \Exception('Product not found: ' . ($it['productId'] ?? ''));
                        if ($product->getStockQuantity() < ($it['quantity'] ?? 0)) {
                            throw new \RuntimeException('INSUFFICIENT_STOCK');
                        }

                        $item = new \App\Entity\SaleItem();
                        $item->setProduct($product);
                        $item->setQuantity((int) $it['quantity']);
                        $item->setUnitPrice($product->getUnitPrice());
                        $item->calculateSubtotal();
                        $sale->addItem($item);

                        $total += $item->getSubtotal();

                        // Deduct stock and log
                        $old = $product->getStockQuantity();
                        $product->setStockQuantity($old - $item->getQuantity());
                        $this->em->persist($product);

                        $log = new \App\Entity\InventoryLog();
                        $log->setProduct($product);
                        $log->setActionType('out');
                        $log->setQuantityChanged($item->getQuantity());
                        $log->setStockBefore($old);
                        $log->setStockAfter($product->getStockQuantity());
                        $log->setPerformedBy($this->getUser());
                        $log->setReference('OFFLINE#' . ($offlineId ?? ''));
                        $this->em->persist($log);
                    }

                    $sale->setTotalAmount($total - ($tx['discountAmount'] ?? 0));
                    $this->em->persist($sale);

                    // Create a Payment record if needed
                    $payment = new \App\Entity\Payment();
                    $payment->setSale($sale);
                    $payment->setMethod($tx['paymentMethod'] ?? 'cash');
                    $payment->setAmount($sale->getTotalAmount());
                    $payment->setStatus('completed');
                    $payment->markAsCompleted();
                    $this->em->persist($payment);

                    // Audit
                    $audit = new AuditLog();
                    $audit->setUser($this->getUser());
                    $audit->setAction('offline_sync_sale');
                    $audit->setModule('sync');
                    $audit->setDescription(json_encode(['offlineId' => $offlineId, 'saleId' => null, 'payload' => $tx]));
                    $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
                    $audit->setUserAgent($request->headers->get('User-Agent'));
                    $this->em->persist($audit);

                    $this->em->flush();
                    // update audit with sale id
                    $audit->setDescription(json_encode(['offlineId' => $offlineId, 'saleId' => $sale->getId(), 'payload' => $tx]));
                    $this->em->flush();

                    $conn->commit();

                    $txResult['status'] = 'applied';
                    $txResult['saleId'] = $sale->getId();
                    $results[] = $txResult;
                    $count++;
                    continue;
                } catch (\Throwable $e) {
                    if ($conn->isTransactionActive()) $conn->rollBack();

                    if ($e instanceof \RuntimeException && $e->getMessage() === 'INSUFFICIENT_STOCK') {
                        // mark as conflict
                        $txResult['status'] = 'conflict';
                        $txResult['message'] = 'Insufficient stock for one or more items';

                        // persist a conflict audit with full payload for reconciliation
                        $audit = new AuditLog();
                        $audit->setUser($this->getUser());
                        $audit->setAction('offline_sync_conflict');
                        $audit->setModule('sync');
                        $audit->setDescription(json_encode(['offlineId' => $offlineId, 'reason' => 'insufficient_stock', 'payload' => $tx]));
                        $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
                        $audit->setUserAgent($request->headers->get('User-Agent'));
                        $this->em->persist($audit);

                        $results[] = $txResult;
                        continue;
                    }

                    // otherwise log failure as audit
                    $audit = new AuditLog();
                    $audit->setUser($this->getUser());
                    $audit->setAction('offline_sync_failed');
                    $audit->setModule('sync');
                    $audit->setDescription(json_encode(['offlineId' => $offlineId, 'error' => $e->getMessage(), 'payload' => $tx]));
                    $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
                    $audit->setUserAgent($request->headers->get('User-Agent'));
                    $this->em->persist($audit);

                    $txResult['status'] = 'failed';
                    $txResult['message'] = $e->getMessage();
                    $results[] = $txResult;
                    continue;
                }
            }

            // Default: just record audit log
            $audit = new AuditLog();
            $audit->setUser($this->getUser());
            $audit->setAction('offline_sync_tx');
            $audit->setModule('sync');
            $audit->setDescription(json_encode($tx));
            $audit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
            $audit->setUserAgent($request->headers->get('User-Agent'));
            $this->em->persist($audit);
            $count++;
            $results[] = $txResult;
        }

        $this->em->flush();

        return $this->json(['accepted' => $count, 'results' => $results], 202);
    }
}
