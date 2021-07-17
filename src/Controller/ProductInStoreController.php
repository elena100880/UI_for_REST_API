<?php
//declare(strict_types = 1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Response\TraceableResponse as ClientResponse;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Event\Subscriber\Paginate\Callback\CallbackPagination;

class ProductInStoreController extends AbstractController
{
    private const IP =  "api"; //access to API container; or just change into IP = "api", if using my own Dockerfile from https://github.com/elena100880/dockerfile; or ="172.*.*.*" if using ip (cmd: docker inspect yy | grep IPAddress).

    private const ELEMENTS = 5;  //number of elements on the page
    private const PAGE_RANGE = 3;   //amount of page numbers to click

    //flash messages:
    

    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    public function products_all (Request $request, PaginatorInterface $paginator) : Response
    {   
        try {
            $form = $this->createFormBuilder()
                                        ->setMethod('GET')
                                        ->add ('select', ChoiceType::class, [
                                                                            'label' => ' ',
                                                                            'expanded' =>true,
                                                                            'choices' => [  'all items' => 1,
                                                                                            'zero amount items' => 0,
                                                                                            'more than five items' => 5],
                                                                            'data' => 1])
                                        ->add('send', SubmitType::class, ['label'=>'Show chosen'])
                                        ->getForm();
            $form->handleRequest($request);
        
            if ($form->isSubmitted() ) {
                $dataFromForm = $form->getData();
                $select = $dataFromForm['select'];
            }
            else {
                $select = $request->query->getInt('select', 1);
            }
            
            $query = ['select' => $select, 'elements' => ProductInStoreController::ELEMENTS, 'page' => $request->query->getInt('page', 1)];
            $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products', ['query' => $query ]);
            $arrayDataFromAPI = $this->array_data_from_response($response);                      
                        
            if ($response->getStatusCode() == 200) {
                $аrrayOfProducts = $arrayDataFromAPI['data']; 
                $total = $arrayDataFromAPI['total'];

                $viewDataForSlider = $this->get_data_for_slider($request, $total, ProductInStoreController::ELEMENTS, ProductInStoreController::PAGE_RANGE);
            }

            else {
                $аrrayOfProducts = [];
                $viewDataForSlider = [];
                $this->addFlash('message','Service is not available at the moment. Please, try again later.');
            }
            $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response); 
        }
        catch (TransportExceptionInterface|\RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message','Service is not available at the moment. Please, try again later.');
        }      

        $contents = $this->renderView('product_in_store/products_all.html.twig', 
        array_merge([
            'products' => $аrrayOfProducts,
            'form' => $form->createView(),
            'developerMessage' => $developerMessage, //only for dev
            'select' => $select,
            'elements' => ProductInStoreController::ELEMENTS],
            $viewDataForSlider)

        );
        return new Response($contents);
    }

    public function product_add(Request $request): Response
    {

        try{
            $form = $this->createFormBuilder()           
                                        ->add('name', TextType::class, ['label' => 'Name of Product:', 'required' => false ])
                                        ->add('amount', TextType::class, ['label' => 'Amount in Store:', 'data' => 0, 'required' => false ])
                                        ->add('save', SubmitType::class, ['label'=>'Add Product'])
                                        ->add('reset', ResetType::class,['label'=>'RESET'] )
                                        ->getForm();
            $form->handleRequest($request);
        
            $userMessage = null;
            $developerMessage = "Not connected to API yet";

            if ($form->isSubmitted()) {

                $dataFromForm = $form->getData();
                $name = $dataFromForm['name'];      
                $amount = ($dataFromForm['amount']) ?? 0;  
                            
            //validation of user's data: 
                if (!$this->is_name_valid($name)) $userMessage = "Please, check your Product's name: it must be a string with <2 and >50 characters.";
                elseif (!$this->is_amount_valid($amount)) $userMessage = "Please, check your Product's amount: it must be positive integer or zero.";
                else {    
                
                    $jsonToAddProduct = json_encode(['name' => $name, 'amount' => intval($amount)]);  
                    $response = $this->client->request( 'POST',  'http://'.ProductInStoreController::IP.'/products', ['body' => $jsonToAddProduct]);
                    $arrayDataFromAPI = $this->array_data_from_response($response);
                                
                    if ($response->getStatusCode() == 200) {
                        $this->addFlash('message','Product was seccesfully added!!');
                        return $this->redirectToRoute('products_all');
                    }
                    else $this->addFlash('message','Service is not available at the moment. Please, try again later.');
                    $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);
                }
            }
        }
        catch (TransportExceptionInterface|\RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message','Service is not available at the moment. Please, try again later.');
        }                     
        $contents = $this->renderView('product_in_store/product_add.html.twig', [
            'form' => $form->createView(),
            'developerMessage' => $developerMessage, //only for dev
            'userMessage' => $userMessage,
        ]);
        return new Response($contents);
    }

    public function product_edit(Request $request, int $id): Response
    {
    
    //quering for product to edit:
        try{    
            $response = $this->client->request( 'GET', 'http://'.ProductInStoreController::IP.'/products/'.$id);
            $arrayDataFromAPI = $this->array_data_from_response($response);
            $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);

            if ($response->getStatusCode() == 200) { 
                $product = $arrayDataFromAPI['data']; 
            }
            elseif ($response->getStatusCode() == 404) {  //preventing open edit-page if Product was deleted/noe exist
                $this->addFlash('message','Product was already deleted or not exist!!');
                return $this->redirectToRoute('products_all');
            }
            else {
                $this->addFlash('message','Editing is not available. Please, try again later!!');
                return $this->redirectToRoute('products_all');
            }
            $form = $this->createFormBuilder()           
                                        ->add('name', TextType::class, ['label' => 'Name of Product:', 'data' => $product['name'], 'required' => false ])
                                        ->add('amount', TextType::class, ['label' => 'Amount in Store:', 'data' => $product['amount'], 'required' => false ])
                                        ->add('save', SubmitType::class, ['label'=>'Save changes'])
                                        ->add('delete', SubmitType::class, ['label'=>'Delete Product!!'])
                                        ->getForm();
            $form->handleRequest($request);
            
        //quering to update the Product:
            $userMessage = null;
            if ($form->isSubmitted()) {
                if ($form->getClickedButton()->getName() == 'delete') {
                    return $this->redirectToRoute('product_delete', ['id' => $id]);
                }
                else {
                    $dataFromForm = $form->getData();
                    $name = $dataFromForm['name'];        
                    $amount = intval($dataFromForm['amount']);  

                //validation of user's data: 
                    if (!$this->is_name_valid($name)) {
                        $userMessage = "Please, check your Product's name: it must be a string with <2 and >50 characters.";
                    }
                    elseif (!$this->is_amount_valid($amount)) {
                        $userMessage = "Please, check your Product's amount: it must be positive integer or zero.";
                    }
                    else {    
                        $jsonToUpdateProduct = json_encode(['name' => $name, 'amount' => intval($amount)]);  
                        $response = $this->client->request( 'PATCH',  'http://'.ProductInStoreController::IP.'/products/'.$id, ['body' => $jsonToUpdateProduct]);
                        $arrayDataFromAPI = $this->array_data_from_response($response);
                        $developerMessage = $this->get_dev_info($arrayDataFromAPI, $response);

                        if ($response->getStatusCode() == 200) {
                            $this->addFlash('message','Product was seccesfully updated!!');
                        }
                        else {
                            $this->addFlash('message','Service is not available at the moment. Please, try again later.');
                        }
                        
                    }
                }
            }
            
        }
        catch (TransportExceptionInterface|\RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message','Service is not available at the moment. Please, try again later.');
        } 

        $contents = $this->renderView('product_in_store/product_edit.html.twig', [
            'form' => $form->createView(),
            'developerMessage' => $developerMessage,
            'userMessage' => $userMessage,
            'id' => $id
        ]);
        return new Response($contents);
    }

    public function product_delete(Request $request, int $id): RedirectResponse
    {
        try {
            $response = $this->client->request( 'DELETE', 'http://'.ProductInStoreController::IP.'/products/'.$id);
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
        catch (TransportExceptionInterface|\RuntimeException $e) {
            $this->addFlash('message','Deleting is not available at the moment. Please, try again later!!');
            return $this->redirectToRoute('product_edit', ['id' => $id]);
        } 
    }

    private function get_dev_info(array $arrayDataFromAPI, ClientResponse $response): string
    {
        $message = $arrayDataFromAPI['message'] ?? "--";
        $code = $response->getStatusCode();
        $devInfo = $arrayDataFromAPI['devInfo'] ?? "--";
        return "message: $message, code: $code, devInfo: $devInfo";
    }

    private function array_data_from_response(ClientResponse $response): array
    {
        $jsonDataFromAPI = $response->getContent(false);
        return json_decode($jsonDataFromAPI, true);
    }

    private function is_name_valid ($name) : bool
    {
        if ($name === null || is_numeric($name) || strlen($name) > 50 or strlen($name) < 2 || (trim($name) == "") ) {
            return false;
        }
        else {
            return true;
        }
    }
    private function is_amount_valid($amount) : bool
    {
        if (!is_numeric($amount) || ($amount - floor($amount) != 0) || $amount < 0 ) {
            return false;
        }
        else {
            return true;
        }
    }

    private function get_data_for_slider(Request $request, int $total, int $elements, int $pageRange) : array
    {
        //Get Data for Slider template, based on https://github.com/KnpLabs/KnpPaginatorBundle/blob/master/src/Pagination/SlidingPagination.php:
                // quantity of pages:
                $pageCount = ceil ($total/$elements); 
                $current = $request->query->getInt('page', 1);

                //quantity of page links shown:
                if ($pageRange > $pageCount) $pageRange = $pageCount;

                //make range of pages ($pageRange + 2):
                $delta = ceil( $pageRange / 2);
                if ($current - $delta > $pageCount - $pageRange) {
                    $pagesInRange = range($pageCount - $pageRange + 1, $pageCount);
                } else {
                    if ($current - $delta < 0) {
                        $delta = $current;
                    }
                    $offset = $current - $delta;
                    $pagesInRange = range($offset + 1, $offset + $pageRange);
                }
              
                $first =  1;
                $last = $pageCount;
                $previous = ($current > 1) ? $current - 1 : null;
                $next =  ($current < $pageCount) ? $current + 1 : null;

                $queryToLink = $request->query->all();
                
                return $viewData = ['pageCount' => $pageCount,
                            'first' => $first,
                            'last' => $last,
                            'current' => $current,
                            'previous' => $previous,
                            'next' => $next,
                            'pagesInRange' => $pagesInRange,
                            'elements' => $elements,
                            'queryToLink' => $queryToLink];
    }
}
