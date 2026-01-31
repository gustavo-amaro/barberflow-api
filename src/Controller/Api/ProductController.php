<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_products_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $category = $request->query->get('category');
        $lowStock = $request->query->get('low_stock');

        if ($lowStock === 'true') {
            $products = $this->productRepository->findLowStockByShop($shop);
        } elseif ($category) {
            $products = $this->productRepository->findByShopAndCategory($shop, $category);
        } else {
            $products = $this->productRepository->findByShop($shop);
        }

        return $this->json(
            $this->serializer->normalize($products, null, ['groups' => 'product:read'])
        );
    }

    #[Route('', name: 'api_products_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        $product = new Product();
        $product->setShop($shop);
        $product->setName($data['name'] ?? '');
        $product->setPrice($data['price'] ?? '0.00');
        $product->setCost($data['cost'] ?? null);
        $product->setStock($data['stock'] ?? 0);
        $product->setMinStock($data['minStock'] ?? null);
        $product->setImage($data['image'] ?? null);
        $product->setCategory($data['category'] ?? null);
        $product->setActive($data['active'] ?? true);

        // Validate
        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($product, null, ['groups' => 'product:read']),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_products_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->find($id);

        if (!$product || $product->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            $this->serializer->normalize($product, null, ['groups' => 'product:read'])
        );
    }

    #[Route('/{id}', name: 'api_products_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->find($id);

        if (!$product || $product->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Dados inválidos'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }
        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }
        if (array_key_exists('cost', $data)) {
            $product->setCost($data['cost']);
        }
        if (isset($data['stock'])) {
            $product->setStock($data['stock']);
        }
        if (array_key_exists('minStock', $data)) {
            $product->setMinStock($data['minStock']);
        }
        if (array_key_exists('image', $data)) {
            $product->setImage($data['image']);
        }
        if (array_key_exists('category', $data)) {
            $product->setCategory($data['category']);
        }
        if (isset($data['active'])) {
            $product->setActive($data['active']);
        }

        // Validate
        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($product, null, ['groups' => 'product:read'])
        );
    }

    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->find($id);

        if (!$product || $product->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/stock', name: 'api_products_update_stock', methods: ['PATCH'])]
    public function updateStock(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $user->getShop();

        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->find($id);

        if (!$product || $product->getShop()->getId() !== $shop->getId()) {
            return $this->json(['error' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['quantity'])) {
            return $this->json(['error' => 'Quantidade é obrigatória'], Response::HTTP_BAD_REQUEST);
        }

        $quantity = (int) $data['quantity'];
        $operation = $data['operation'] ?? 'add';

        if ($operation === 'add') {
            $product->setStock($product->getStock() + $quantity);
        } elseif ($operation === 'subtract') {
            $newStock = $product->getStock() - $quantity;
            if ($newStock < 0) {
                return $this->json(['error' => 'Estoque insuficiente'], Response::HTTP_BAD_REQUEST);
            }
            $product->setStock($newStock);
        } else {
            $product->setStock($quantity);
        }

        $this->entityManager->flush();

        return $this->json(
            $this->serializer->normalize($product, null, ['groups' => 'product:read'])
        );
    }
}
