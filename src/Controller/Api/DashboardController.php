<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\BarberRepository;
use App\Repository\ClientRepository;
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
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');

        // Today's appointments
        $todayAppointments = $this->appointmentRepository->findByShopAndDate($shop, $today);

        // Pending appointments
        $pendingCount = $this->appointmentRepository->countByShopAndStatus($shop, Appointment::STATUS_PENDING);

        // Monthly revenue
        $monthlyRevenue = $this->appointmentRepository->getTotalRevenueByShop($shop, $startOfMonth, $endOfMonth);

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
            ],
            'monthly' => [
                'revenue' => $monthlyRevenue,
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
}
