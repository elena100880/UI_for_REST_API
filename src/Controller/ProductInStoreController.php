<?php

namespace App\Controller;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ProductInStoreController extends AbstractController
{
    // access to API container; or just change into IP = "api",
    // if using my own Dockerfile from https://github.com/elena100880/dockerfile;
    // or ="172.*.*.*" if using ip (cmd: docker inspect yy | grep IPAddress).
    private const IP = 'api';
    private const PROTOCOL = 'http://';
    private const PATH = '/products/';

    // number of elements on the page:
    private const ELEMENTS = 5;

    // amount of page numbers to click:
    private const PAGE_RANGE = 3;

    // flash messages:
    private const API_NOT_200_RESPONSE_GENERAL = 'Service is not available at the moment. Please, try again later.';
    private const API_NOT_200_RESPONSE_FOR_EDIT = 'Editing is not available. Please, try again later!!';
    private const API_NOT_200_RESPONSE_FOR_DELETE = 'Deleting is not available at the moment. Please, try again later!!';

    private const API_200_PRODUCT_ADD = 'Product was successfully added!!';
    private const API_200_PRODUCT_UPDATE = 'Product was successfully updated!!';
    private const API_200_PRODUCT_DELETE = 'Product was successfully deleted!!';
    private const API_404_PRODUCT_NOT_FOUND = 'Product was already deleted or not exist!!';

    // user messages:
    private const USER_MESSAGE_INVALID_NAME = 'Please, check your Product\'s name: it must be a string with >2 and <50 characters.';
    private const USER_MESSAGE_INVALID_AMOUNT = 'Please, check your Product\'s amount: it must be positive integer or zero.';

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function getProducts(Request $request): Response
    {
        try {
            $form = $this->createFormBuilder()
                ->setMethod('GET')
                ->add(
                    'select',
                    ChoiceType::class,
                    [
                        'label' => ' ',
                        'expanded' => true,
                        'choices' => [
                            'all items' => 1,
                            'zero amount items' => 0,
                            'more than five items' => 5,
                        ],
                        'data' => 1,
                    ]
                )
                ->add('send', SubmitType::class, ['label' => 'Show chosen'])
                ->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $dataFromForm = $form->getData();
                $select = $dataFromForm['select'];
            } else {
                $select = $request->query->getInt('select', 1);
            }

            $query = [
                'select' => $select,
                'elements' => ProductInStoreController::ELEMENTS,
                'page' => $request->query->getInt('page', 1),
            ];
            $response = $this->client->request(
                'GET',
                ProductInStoreController::PROTOCOL.ProductInStoreController::IP.'/products',
                ['query' => $query]
            );
            $arrayDataFromAPI = $this->arrayDataFromResponse($response);

            if (200 == $response->getStatusCode()) {
                $arrayOfProducts = $arrayDataFromAPI['data'];
                $total = $arrayDataFromAPI['total'];
                $viewDataForSlider = $this->getDataForSlider(
                    $request,
                    $total
                );
            } else {
                $arrayOfProducts = [];
                $viewDataForSlider = [];
                $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
            }
            $developerMessage = $this->getDevInfo($arrayDataFromAPI, $response);
        } catch (TransportExceptionInterface | RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
        }

        $contents = $this->renderView(
            'product_in_store/products_all.html.twig',
            array_merge(
                [
                    'products' => $arrayOfProducts,
                    'form' => $form->createView(),
                    'developerMessage' => $developerMessage, // only for dev!
                    'select' => $select,
                    'elements' => ProductInStoreController::ELEMENTS,
                ],
                $viewDataForSlider
            )
        );

        return new Response($contents);
    }

    public function addProduct(Request $request): Response
    {
        try {
            $form = $this->createFormBuilder()
                ->add('name', TextType::class, ['label' => 'Name of Product:', 'required' => false])
                ->add('amount', TextType::class, ['label' => 'Amount in Store:', 'data' => 0, 'required' => false])
                ->add('save', SubmitType::class, ['label' => 'Add Product'])
                ->add('reset', ResetType::class, ['label' => 'RESET'])
                ->getForm();
            $form->handleRequest($request);

            $userMessage = null;
            $developerMessage = 'Not connected to API yet';

            if ($form->isSubmitted()) {
                $dataFromForm = $form->getData();
                $name = $dataFromForm['name'];
                $amount = ($dataFromForm['amount']) ?? 0;

                // validation of user's data:
                if (!$this->isNameValid($name)) {
                    $userMessage = ProductInStoreController::USER_MESSAGE_INVALID_NAME;
                } elseif (!$this->isAmountValid($amount)) {
                    $userMessage = ProductInStoreController::USER_MESSAGE_INVALID_AMOUNT;
                } else {
                    $jsonToAddProduct = json_encode(['name' => $name, 'amount' => intval($amount)]);
                    $response = $this->client->request(
                        'POST',
                        ProductInStoreController::PROTOCOL.ProductInStoreController::IP.'/products',
                        ['body' => $jsonToAddProduct]
                    );
                    $arrayDataFromAPI = $this->arrayDataFromResponse($response);

                    if (200 == $response->getStatusCode()) {
                        $this->addFlash('message', ProductInStoreController::API_200_PRODUCT_ADD);

                        return $this->redirectToRoute('products_all');
                    } else {
                        $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
                    }
                    $developerMessage = $this->getDevInfo($arrayDataFromAPI, $response);
                }
            }
        } catch (TransportExceptionInterface | RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
        }
        $contents = $this->renderView(
            'product_in_store/product_add.html.twig',
            [
                'form' => $form->createView(),
                'developerMessage' => $developerMessage, // only for dev
                'userMessage' => $userMessage,
            ]
        );

        return new Response($contents);
    }

    public function editProduct(Request $request, int $id): Response
    {
        // querying for product to edit:
        try {
            $response = $this->client->request(
                'GET',
                ProductInStoreController::PROTOCOL.ProductInStoreController::IP.ProductInStoreController::PATH.$id
            );
            $arrayDataFromAPI = $this->arrayDataFromResponse($response);
            $developerMessage = $this->getDevInfo($arrayDataFromAPI, $response);

            if (200 == $response->getStatusCode()) {
                $product = $arrayDataFromAPI['data'];
            } elseif (404 == $response->getStatusCode()) { // preventing open edit-page if Product was deleted/noe exist
                $this->addFlash('message', ProductInStoreController::API_404_PRODUCT_NOT_FOUND);

                return $this->redirectToRoute('products_all');
            } else {
                $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_FOR_EDIT);

                return $this->redirectToRoute('products_all');
            }
            $form = $this->createFormBuilder()
                ->add(
                    'name',
                    TextType::class,
                    ['label' => 'Name of Product:', 'data' => $product['name'], 'required' => false]
                )
                ->add(
                    'amount',
                    TextType::class,
                    ['label' => 'Amount in Store:', 'data' => $product['amount'], 'required' => false]
                )
                ->add('save', SubmitType::class, ['label' => 'Save changes'])
                ->add('delete', SubmitType::class, ['label' => 'Delete Product!!'])
                ->getForm();
            $form->handleRequest($request);

            // querying to update the Product:
            $userMessage = null;
            if ($form->isSubmitted()) {
                if ('delete' == $form->getClickedButton()->getName()) {
                    return $this->redirectToRoute('product_delete', ['id' => $id]);
                } else {
                    $dataFromForm = $form->getData();
                    $name = $dataFromForm['name'];
                    $amount = intval($dataFromForm['amount']);

                    // validation of user's data:
                    if (!$this->isNameValid($name)) {
                        $userMessage = ProductInStoreController::USER_MESSAGE_INVALID_NAME;
                    } elseif (!$this->isAmountValid($amount)) {
                        $userMessage = ProductInStoreController::USER_MESSAGE_INVALID_AMOUNT;
                    } else {
                        $jsonToUpdateProduct = json_encode(['name' => $name, 'amount' => $amount]);
                        $response = $this->client->request(
                            'PATCH',
                            ProductInStoreController::PROTOCOL.ProductInStoreController::IP.ProductInStoreController::PATH.$id,
                            ['body' => $jsonToUpdateProduct]
                        );
                        $arrayDataFromAPI = $this->arrayDataFromResponse($response);
                        $developerMessage = $this->getDevInfo($arrayDataFromAPI, $response);

                        if (200 == $response->getStatusCode()) {
                            $this->addFlash('message', ProductInStoreController::API_200_PRODUCT_UPDATE);
                        } else {
                            $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
                        }
                    }
                }
            }
        } catch (TransportExceptionInterface | RuntimeException $e) {
            $developerMessage = $e->getMessage();
            $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_GENERAL);
        }

        $contents = $this->renderView(
            'product_in_store/product_edit.html.twig',
            [
                'form' => $form->createView(),
                'developerMessage' => $developerMessage,
                'userMessage' => $userMessage,
                'id' => $id,
            ]
        );

        return new Response($contents);
    }

    public function deleteProduct(int $id): RedirectResponse
    {
        try {
            $response = $this->client->request(
                'DELETE',
                ProductInStoreController::PROTOCOL.ProductInStoreController::IP.ProductInStoreController::PATH.$id
            );

            if (200 == $response->getStatusCode()) {
                $this->addFlash('message', ProductInStoreController::API_200_PRODUCT_DELETE);

                return $this->redirectToRoute('products_all');
            } else {
                $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_FOR_DELETE);

                return $this->redirectToRoute('product_edit', ['id' => $id]);
            }
        } catch (TransportExceptionInterface | RuntimeException) {
            $this->addFlash('message', ProductInStoreController::API_NOT_200_RESPONSE_FOR_DELETE);

            return $this->redirectToRoute('product_edit', ['id' => $id]);
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function getDevInfo(?array $arrayDataFromAPI, ResponseInterface $response): ?string
    {
        $message = $arrayDataFromAPI['message'] ?? '--';
        $code = $response->getStatusCode();
        $devInfo = $arrayDataFromAPI['devInfo'] ?? '--';

        return "message: $message, code: $code, devInfo: $devInfo";
    }

    private function arrayDataFromResponse(ResponseInterface $response): ?array
    {
        $jsonDataFromAPI = $response->getContent(false);

        return json_decode($jsonDataFromAPI, true);
    }

    private function isNameValid($name): bool
    {
        return (null === $name || is_numeric($name) || strlen($name) > 50 || strlen($name) < 2 || ('' == trim($name)));
    }

    private function isAmountValid($amount): bool
    {
        return (!is_numeric($amount) || (0 != $amount - floor($amount)) || $amount < 0);
    }

    // Get Data for Slider template, based on
    // https://github.com/KnpLabs/KnpPaginatorBundle/blob/master/src/Pagination/SlidingPagination.php
    private function getDataForSlider(Request $request, int $total): array
    {
        $pageRange = ProductInStoreController::PAGE_RANGE;

        // quantity of pages:
        $pageCount = ceil($total / ProductInStoreController::ELEMENTS);
        $current = $request->query->getInt('page', 1);

        // quantity of page links shown:
        if (ProductInStoreController::PAGE_RANGE > $pageCount) {
            $pageRange = $pageCount;
        }

        //make range of pages ($pageRange + 2):
        $delta = ceil($pageRange / 2);
        if ($current - $delta > $pageCount - $pageRange) {
            $pagesInRange = range($pageCount - $pageRange + 1, $pageCount);
        } else {
            if ($current - $delta < 0) {
                $delta = $current;
            }
            $offset = $current - $delta;
            $pagesInRange = range($offset + 1, $offset + $pageRange);
        }

        $first = 1;
        $last = $pageCount;
        $previous = ($current > 1) ? $current - 1 : null;
        $next = ($current < $pageCount) ? $current + 1 : null;

        $queryToLink = $request->query->all();

        return [
            'pageCount' => $pageCount,
            'first' => $first,
            'last' => $last,
            'current' => $current,
            'previous' => $previous,
            'next' => $next,
            'pagesInRange' => $pagesInRange,
            'elements' => ProductInStoreController::ELEMENTS,
            'queryToLink' => $queryToLink,
        ];
    }
}
