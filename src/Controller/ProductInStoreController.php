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
                                                                        'data' => 1])
                                    ->add('send', SubmitType::class, ['label'=>'Show chosen'])
                                    ->getForm();
        $form->handleRequest($request);
      
        if ($form->isSubmitted() ) 
        {
            $dataFromForm = $form->getData();
            $itemsToSearch = $dataFromForm['items'];

            $query = ['amount' => $itemsToSearch];
         
            if ($itemsToSearch == 0) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products', ['query' => $query ]);
            if ($itemsToSearch == 5) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products', ['query' => $query ]);
            if ($itemsToSearch == 1) $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products', ['query' => $query ]);
        }
        else $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products' );
        
        $arrayDataFromAPI = $this->array_data_from_response($response);
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
                                    ->add('name', TextType::class, ['label' => 'Name of Product:'])
                                    ->add('amount', NumberType::class, ['label' => 'Amount in Store:', 'data' => 0, 'required' => false ])
                                    ->add('save', SubmitType::class, ['label'=>'Add Product'])
                                    ->getForm();
        $form->handleRequest($request);
    
        $developerMessage = "Not connected to API yet";
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

            $arrayDataFromAPI = $this->array_data_from_response($response);
            $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);
            
            if ($response->getStatusCode() == 200) {
                $this->addFlash('message','Product was seccesfully added!!');
                return $this->redirectToRoute('products_all');
            }
            else $userMessage = "DB is not available. Please, try again later.";
        }
                        
        $contents = $this->renderView('product_in_store/product_add.html.twig', [
            'form' => $form->createView(),
            'developerMessage' => $developerMessage,
            'userMessage' => $userMessage,
        ]);
        return new Response($contents);
    }

    public function product_edit(Request $request, $id): Response
    {
        $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products/'.$id);

        $arrayDataFromAPI = $this->array_data_from_response($response);
        $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);

        if ($response->getStatusCode() == 200) {
            $product = $arrayDataFromAPI['data'];
        }
        //preventing open edit-page if Product was deleted
        elseif ($response->getStatusCode() == 404) {
            $this->addFlash('message','Product was already deleted!!');
            return $this->redirectToRoute('products_all');
        }
        else {
            $this->addFlash('message','Editing is not available. Please, try again later!!');
            return $this->redirectToRoute('products_all');
        }

        $form = $this->createFormBuilder()           /** @todo make ProductInStore object TypeForm */
                                    ->add('name', TextType::class, ['label' => 'Name of Product:', 'data' => $product['name'], 'required' => false ])
                                    ->add('amount', NumberType::class, ['label' => 'Amount in Store:', 'data' => $product['amount'], 'required' => false ])
                                    ->add('save', SubmitType::class, ['label'=>'Save changes'])
                                    ->add('delete', SubmitType::class, ['label'=>'Delete Product!!'])
                                    ->getForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->getClickedButton()->getName() == 'delete') return $this->redirectToRoute('product_delete', ['id' => $id]);
            else {

                $dataFromForm = $form->getData();
                $name = $dataFromForm['name'];               
                $amount = intval($dataFromForm['amount']);
                
                $userMessage = null;
                /** 
                 * @todo some validation of user's input data: name and amount 
                 * if not Valid return some $userMessage
                 */

                $jsonToAddProduct = json_encode(['name' => $name, 'amount' => $amount]);  /** @todo encode from object ProductInStore */
                $response = $this->client->request( 'PATCH',  'http://'.ProductInStoreController::IP.'/products', ['body' => $jsonToAddProduct]);

                $arrayDataFromAPI = $this->array_data_from_response($response);
                $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);
                
                if ($response->getStatusCode() == 200) {
                    $this->addFlash('message','Product was seccesfully updated!!');
                    return $this->redirectToRoute('product_edit');
                }
                else $userMessage = "DB is not available. Please, try again later.";
            }
        }
        
        $contents = $this->renderView('product_in_store/product_edit.html.twig', [
            'form' => $form->createView(),
            'developerMessage' => $developerMessage,
            'userMessage' => $userMessage,
        ]);
        return new Response($contents);
        
    }

    public function product_delete(Request $request, $id)
    {
        $response = $this->client->request( 'DELETE', 'http://'.ProductInStoreController::IP.'/products/'.$id);

     //   $jsonDataFromAPI = $response->getContent(false); //а что я получу, если вернет не мой обработанный ответ, а симфони ошибку выдаст свою? или //ПарамКонвертер свое 500 вернет, когда БД недоступна??? тогда httpClient должен их обработать и выдать мне сюда 500
      //  $arrayDataFromAPI = json_decode($jsonDataFromAPI, true);

        $arrayDataFromAPI = $this->array_data_from_response($response);
                
        if ($response->getStatusCode() == 200) {
            $this->addFlash('message','Product was seccesfully deleted!!');
            return $this->redirectToRoute('products_all');
        }
        else {
            $this->addFlash('message','Deleting is not available at the moment. Please, try again later!!');
            return $this->redirectToRoute('product_edit', ['id' => $id]);
        }

        $this->addFlash('message','Product was seccesfully added!!');
        return $this->redirectToRoute('products_all');
    }

    private function get_dev_info($arrayDataFromAPI, $response)
    {
        $message = $arrayDataFromAPI['message'] ?? "--";
        $code = $response->getStatusCode();
        $devInfo = $arrayDataFromAPI['devInfo'] ?? "--";
        return "message: $message, code: $code, devInfo: $devInfo";
    }

    private function array_data_from_response($response) {
        $jsonDataFromAPI = $response->getContent(false);
        return json_decode($jsonDataFromAPI, true);
    }
}
