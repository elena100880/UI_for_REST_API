<?php

namespace App\Controller;

use App\Entity\ProductInStore;
use App\Form\ProductInStoreType;
use App\Repository\ProductInStoreRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProductInStoreController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    public function products_all (Request $request) : Response
    {
        $response = $this->client->request(
            'GET',
            'https://localhost:81/products'
        );
        $content = $response->getContent();
        
        
        
        
        
        
        $contents = $this->renderView('product_in_store/products_all.html.twig', [

            //'product_in_stores' => $productInStoreRepository->findAll(),

        ]);
        return new Response($contents);
    }

    #[Route('/new', name: 'product_in_store_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $productInStore = new ProductInStore();
        $form = $this->createForm(ProductInStoreType::class, $productInStore);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($productInStore);
            $entityManager->flush();

            return $this->redirectToRoute('product_in_store_index');
        }

        return $this->render('product_in_store/new.html.twig', [
            'product_in_store' => $productInStore,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'product_in_store_show', methods: ['GET'])]
    public function show(ProductInStore $productInStore): Response
    {
        return $this->render('product_in_store/show.html.twig', [
            'product_in_store' => $productInStore,
        ]);
    }

    #[Route('/{id}/edit', name: 'product_in_store_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProductInStore $productInStore): Response
    {
        $form = $this->createForm(ProductInStoreType::class, $productInStore);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('product_in_store_index');
        }

        return $this->render('product_in_store/edit.html.twig', [
            'product_in_store' => $productInStore,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'product_in_store_delete', methods: ['POST'])]
    public function delete(Request $request, ProductInStore $productInStore): Response
    {
        if ($this->isCsrfTokenValid('delete'.$productInStore->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($productInStore);
            $entityManager->flush();
        }

        return $this->redirectToRoute('product_in_store_index');
    }
}
