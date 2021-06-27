<?php

namespace App\Controller;

#use App\Entity\ProductInStore;
#use App\Form\ProductInStoreType;
#use App\Repository\ProductInStoreRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Contracts\HttpClient\HttpClientInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ProductInStoreController extends AbstractController
{
    private $client;

    private const IP =  "172.17.0.3"; //access to API container

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    public function products_all (Request $request) : Response
    {   
        $form = $this->createFormBuilder()
                                    ->setMethod('GET')
                                    ->add ('items', ChoiceType::class, [
                                                                        'label' => ' ',
                                                                        'expanded' =>true,
                                                                        'choices' => [  'all items' => 1,
                                                                                        'zero amount items' => 0,
                                                                                        'more than five items' => 5],
                                                                        'data' => 1,
                                                                                    ])
                                    ->add('send', SubmitType::class, ['label'=>'Show chosen'])
                                    ->getForm();
        $form->handleRequest($request);
      
        if ($form->isSubmitted() ) 
        {
            $dataFromForm = $form->getData();
            $itemsToSearch = $dataFromForm['items'];
         
            if ($itemsToSearch == 0) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products?amount=0' );
            if ($itemsToSearch == 5) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products?amount=5' );
            if ($itemsToSearch == 1) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products?amount=1' );
        }
        else $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products' );
        
        $jsonDataFromAPI = $response->getContent(false);
        $arrayDataFromAPI = json_decode($jsonDataFromAPI, true); 

        $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);
       
        if ($response->getStatusCode() == 200) {
            $аrrayOfProducts = $arrayDataFromAPI['data'];   /** @todo convert decoded data into array of ProdutInStore objects */
            $userMessage = null;
        }
        else {
            $аrrayOfProducts = [];
            $userMessage = "DB is not available. Please, try again later.";
        }
                        
        $contents = $this->renderView('product_in_store/products_all.html.twig', [
            'ip' => ProductInStoreController::IP,
            'products' => $аrrayOfProducts,
            'form' => $form->createView(),
            'developerMessage' => $developerMessage,
            'userMessage' => $userMessage
        ]);
        return new Response($contents);
    }

    public function product_add(Request $request): Response
    {
        $form = $this->createFormBuilder()           /** @todo make ProductInStore object TypeForm */
                                    ->add('name', TextType::class, ['label' => 'Name of Product:',
                                                                    'required' => false])
                                    ->add('amount', TextType::class, ['label' => 'Amount in Store:',
                                                                        'data' => 0])
                                    ->add('save', SubmitType::class, ['label'=>'Add the item'])
                                    ->getForm();
        $form->handleRequest($request);
    
        $developerMessage = null;
        $userMessage = null;
        if ($form->isSubmitted()) {

            $dataFromForm = $form->getData();
            $name = $dataFromForm['name'];               
            $amount = intval($dataFromForm['amount']);
            
            /** 
             * @todo some validation of user's input data: name and amount 
             * if not Valid return some $userMessage
             */

            $jsonToAddProduct = json_encode(['name' => $name, 'amount' => $amount]);  /** @todo encode from object ProductInStore */
            $response = $this->client->request( 'POST',  'http://'.ProductInStoreController::IP.'/products', ['body' => $jsonToAddProduct]);
            $jsonDataFromAPI = $response->getContent(false);
            $arrayDataFromAPI = json_decode($jsonDataFromAPI, true);

            $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);
            
            if ($response->getStatusCode() == 200) {
                $this->addFlash('succes','Product was seccesfully added!!');
                return $this->redirectToRoute('products_all');
            }
            else $userMessage = "DB is not available. Please, try again later.";
        }
        
                
            $contents = $this->renderView('product_in_store/product_add.html.twig', [
                'ip' => ProductInStoreController::IP,
                'form' => $form->createView(),
                'developerMessage' => $developerMessage,
                'userMessage' => $userMessage,
            ]);
            return new Response($contents);
        
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

    public function get_dev_info($arrayDataFromAPI, $response)
    {
        $message = $arrayDataFromAPI['message'] ?? "-";
        $code = $response->getStatusCode();
        return $developerMessage = "message: $message, code: $code";
    }
}
