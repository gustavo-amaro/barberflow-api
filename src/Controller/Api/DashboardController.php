<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\BarberRepository;
use App\Repository\ClientRepository;
use App\Repository\ProductMovementRepository;
use App\Repository\ProductRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private BarberRepository $barberRepository,
        private ServiceRepository $serviceRepository,
        private ProductRepository $productRepository,
        private ProductMovementRepository $productMovementRepository,
        private ClientRepository $clientRepository
    ) {}

    #[Route('/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $today = new \DateTime('today');
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');
        $startOfLastMonth = new \DateTimeImmutable('first day of last month 00:00:00');
        $endOfLastMonth = new \DateTimeImmutable('last day of last month 23:59:59');

        // Today's appointments
        $todayAppointments = $this->appointmentRepository->findByShopAndDate($shop, $today);
        $yesterday = new \DateTime('yesterday');
        $todayRevenue = $this->appointmentRepository->getRevenueByShopAndDate($shop, $today);
        $yesterdayRevenue = $this->appointmentRepository->getRevenueByShopAndDate($shop, $yesterday);
        $yesterdayNum = (float) $yesterdayRevenue;
        $revenueTrend = $yesterdayNum > 0
            ? (int) round(((float) $todayRevenue - $yesterdayNum) / $yesterdayNum * 100)
            : ((float) $todayRevenue > 0 ? 100 : 0);

        // Pending appointments
        $pendingCount = $this->appointmentRepository->countByShopAndStatus($shop, Appointment::STATUS_PENDING);

        // Monthly revenue
        $monthlyRevenue = $this->appointmentRepository->getTotalRevenueByShop($shop, $startOfMonth, $endOfMonth);

        // New clients this month and growth vs last month
        $newClientsThisMonth = $this->clientRepository->countCreatedInPeriod($shop, $startOfMonth, $endOfMonth);
        $newClientsLastMonth = $this->clientRepository->countCreatedInPeriod($shop, $startOfLastMonth, $endOfLastMonth);
        $growth = $newClientsLastMonth > 0
            ? (int) round(($newClientsThisMonth - $newClientsLastMonth) / $newClientsLastMonth * 100)
            : ($newClientsThisMonth > 0 ? 100 : 0);

        // Total clients
        $totalClients = count($this->clientRepository->findByShop($shop));

        // Active barbers
        $activeBarbers = count($this->barberRepository->findActiveByShop($shop));

        // Active services
        $activeServices = count($this->serviceRepository->findActiveByShop($shop));

        // Low stock products
        $lowStockProducts = $this->productRepository->findLowStockByShop($shop);

        // Top clients
        $topClients = $this->clientRepository->findTopClientsByShop($shop, 5);

        return $this->json([
            'today' => [
                'appointments' => count($todayAppointments),
                'pending' => $pendingCount,
                'revenueTrend' => $revenueTrend,
            ],
            'monthly' => [
                'revenue' => $monthlyRevenue,
                'newClients' => $newClientsThisMonth,
                'growth' => $growth,
            ],
            'totals' => [
                'clients' => $totalClients,
                'barbers' => $activeBarbers,
                'services' => $activeServices,
            ],
            'alerts' => [
                'lowStockProducts' => count($lowStockProducts),
            ],
            'topClients' => array_map(fn($client) => [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'visits' => $client->getVisits(),
                'totalSpent' => $client->getTotalSpent(),
            ], $topClients),
        ]);
    }

    #[Route('/appointments/today', name: 'api_dashboard_today', methods: ['GET'])]
    public function todayAppointments(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $appointments = $this->appointmentRepository->findTodayByShop($shop);

        return $this->json(array_map(fn($apt) => [
            'id' => $apt->getId(),
            'clientName' => $apt->getClientName(),
            'phone' => $apt->getPhone(),
            'time' => $apt->getTime()->format('H:i'),
            'status' => $apt->getStatus(),
            'price' => $apt->getPrice(),
            'barber' => [
                'id' => $apt->getBarber()->getId(),
                'name' => $apt->getBarber()->getName(),
            ],
            'service' => [
                'id' => $apt->getService()->getId(),
                'name' => $apt->getService()->getName(),
                'duration' => $apt->getService()->getDuration(),
            ],
        ], $appointments));
    }

    #[Route('/revenue', name: 'api_dashboard_revenue', methods: ['GET'])]
    public function revenue(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $period = $request->query->get('period', 'month');

        switch ($period) {
            case 'week':
                $startDate = new \DateTime('monday this week');
                $endDate = new \DateTime('sunday this week');
                break;
            case 'year':
                $startDate = new \DateTime('first day of january this year');
                $endDate = new \DateTime('last day of december this year');
                break;
            case 'month':
            default:
                $startDate = new \DateTime('first day of this month');
                $endDate = new \DateTime('last day of this month');
        }

        $revenue = $this->appointmentRepository->getTotalRevenueByShop($shop, $startDate, $endDate);

        return $this->json([
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'revenue' => $revenue,
        ]);
    }

    #[Route('/reports', name: 'api_dashboard_reports', methods: ['GET'])]
    public function reports(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');
        $startOfLastMonth = new \DateTimeImmutable('first day of last month 00:00:00');
        $endOfLastMonth = new \DateTimeImmutable('last day of last month 23:59:59');

        $monthlyRevenue = (float) $this->appointmentRepository->getTotalRevenueByShop($shop, $startOfMonth, $endOfMonth);
        $lastMonthRevenue = (float) $this->appointmentRepository->getTotalRevenueByShop($shop, $startOfLastMonth, $endOfLastMonth);
        $revenueTrend = $lastMonthRevenue > 0
            ? (int) round(($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100)
            : ($monthlyRevenue > 0 ? 100 : 0);

        $appointmentsCount = $this->appointmentRepository->countByShopAndDateRange($shop, $startOfMonth, $endOfMonth);
        $cancelledCount = $this->appointmentRepository->countCancelledByShopAndDateRange($shop, $startOfMonth, $endOfMonth);
        $cancellationRate = $appointmentsCount > 0 ? (int) round($cancelledCount / $appointmentsCount * 100) : 0;

        $newClientsThisMonth = $this->clientRepository->countCreatedInPeriod($shop, $startOfMonth, $endOfMonth);
        $newClientsLastMonth = $this->clientRepository->countCreatedInPeriod($shop, $startOfLastMonth, $endOfLastMonth);
        $growth = $newClientsLastMonth > 0
            ? (int) round(($newClientsThisMonth - $newClientsLastMonth) / $newClientsLastMonth * 100)
            : ($newClientsThisMonth > 0 ? 100 : 0);

        $totalCommissions = (float) $this->appointmentRepository->getTotalCommissionsByShopAndDateRange($shop, $startOfMonth, $endOfMonth);
        $productRevenue = (float) $this->productMovementRepository->getSalesRevenueByShopAndPeriod($shop, $startOfMonth, $endOfMonth);
        $netProfit = $monthlyRevenue - $totalCommissions + $productRevenue;

        $revenueByBarber = $this->appointmentRepository->getRevenueByBarberByShopAndDateRange($shop, $startOfMonth, $endOfMonth);
        $barbers = $this->barberRepository->findByShop($shop);
        $topBarbers = [];
        foreach ($barbers as $barber) {
            $revenue = $revenueByBarber[$barber->getId()] ?? '0';
            $topBarbers[] = [
                'id' => $barber->getId(),
                'name' => $barber->getName(),
                'avatar' => $barber->getAvatar(),
                'specialty' => $barber->getSpecialty(),
                'color' => $barber->getColor(),
                'revenue' => $revenue,
            ];
        }
        usort($topBarbers, fn($a, $b) => (float) $b['revenue'] <=> (float) $a['revenue']);

        return $this->json([
            'monthlyRevenue' => (string) $monthlyRevenue,
            'revenueTrend' => $revenueTrend,
            'appointmentsCount' => $appointmentsCount,
            'newClients' => $newClientsThisMonth,
            'growth' => $growth,
            'cancellationRate' => $cancellationRate,
            'summary' => [
                'grossRevenue' => (string) $monthlyRevenue,
                'commissions' => (string) $totalCommissions,
                'productRevenue' => (string) $productRevenue,
                'netProfit' => (string) round($netProfit, 2),
            ],
            'topBarbers' => $topBarbers,
        ]);
    }
}
