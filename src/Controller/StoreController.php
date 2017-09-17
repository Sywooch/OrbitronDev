<?php

namespace Controller;

use App\Account\Account;
use App\Account\AccountTools;
use App\Account\UserInfo;
use App\Store\Store;
use App\Store\StoreCheckout;
use App\Store\StoreComments;
use App\Store\StoreProduct;
use Container\DatabaseContainer;
use Controller;
use Form\RecaptchaType;
use Kernel;
use PDO;
use ReCaptcha\ReCaptcha;
use Swift_Image;
use Swift_Message;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;

class StoreController extends Controller
{

    public function indexAction()
    {
        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $storeList = Store::getStoreList();
        foreach ($storeList as $key => $store) {
            $user                        = new UserInfo($store['owner_id']);
            $storeList[$key]['username'] = $user->getFromUser('username');
        }

        return $this->render('store/list-stores.html.twig', array(
            'current_user' => $currentUser->aUser,
            'store_list'  => $storeList,
        ));
    }

    public function newStoreAction()
    {
        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $createStoreForm = $this->createFormBuilder()
            ->add('name', TextType::class, array(
                'label'       => 'Store name',
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter a name')),
                ),
            ))
            ->add('url', TextType::class, array(
                'label'       => 'Store url',
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter a url')),
                ),
            ))
            ->add('recaptcha', RecaptchaType::class, array(
                'private_key'    => '6Ldec_4SAAAAAMqZOBRgHo0KRYptXwsfCw-3Pxll',
                'public_key'     => '6Ldec_4SAAAAAJ_TnvICnltNqgNaBPCbXp-wN48B',
                'recaptcha_ajax' => false,
                'attr'           => array(
                    'options' => array(
                        'theme' => 'light',
                        'type'  => 'image',
                        'size'  => 'normal',
                        'defer' => true,
                        'async' => true,
                    ),
                ),
            ))
            ->add('send', SubmitType::class, array(
                'label' => 'Create',
            ))
            ->getForm();


        /** @var Request $request */
        $request = $this->get('kernel')->getRequest();
        $createStoreForm->handleRequest($request);
        if ($createStoreForm->isValid()) {
            $errorMessages   = array();
            $captcha         = new ReCaptcha('6Ldec_4SAAAAAMqZOBRgHo0KRYptXwsfCw-3Pxll');
            $captchaResponse = $captcha->verify($_POST['g-recaptcha-response'], $request->getClientIp());
            if (!$captchaResponse->isSuccess()) {
                $createStoreForm->get('recaptcha')->addError(new FormError('The Captcha is not correct'));
            } else {
                if (strlen($storeName = trim($createStoreForm->get('name')->getData())) == 0) {
                    $errorMessages[] = '';
                    $createStoreForm->get('name')->addError(new FormError('Please give your store a name'));
                } elseif (strlen($storeName) < 4) {
                    $errorMessages[] = '';
                    $createStoreForm->get('name')->addError(new FormError('Your store must have minimally 4 characters'));
                }
                if (strlen($storeUrl = trim($createStoreForm->get('url')->getData())) == 0) {
                    $errorMessages[] = '';
                    $createStoreForm->get('url')->addError(new FormError('Please give your store an unique url to access it'));
                } elseif (strlen($storeUrl) < 3) {
                    $errorMessages[] = '';
                    $createStoreForm->get('url')->addError(new FormError('Your store must url have minimally 3 characters'));
                } elseif (preg_match('/[^a-z_\-0-9]/i', $storeUrl)) {
                    $errorMessages[] = '';
                    $createStoreForm->get('url')->addError(new FormError('Only use a-z, A-Z, 0-9, _, -'));
                } elseif (in_array($storeUrl, array('new-forum', 'admin'))) {
                    $errorMessages[] = '';
                    $createStoreForm->get('url')->addError(new FormError('It\'s prohibited to use this url'));
                } elseif (Store::urlExists($storeUrl)) {
                    $errorMessages[] = '';
                    $createStoreForm->get('url')->addError(new FormError('This url is already in use'));
                }

                if (!count($errorMessages)) {
                    /** @var \PDOStatement $addStore */
                    $addStore = $this->get('database')->prepare('INSERT INTO `stores`(`name`,`url`,`owner_id`) VALUES (:name,:url,:user_id)');
                    $storeAdded = $addStore->execute(array(
                        ':name'    => $storeName,
                        ':url'     => $storeUrl,
                        ':user_id' => USER_ID,
                    ));

                    if ($storeAdded) {
                        /** @var \PDOStatement $getStore */
                        $getStore = $this->get('database')->prepare('SELECT `url` FROM `stores` WHERE `url`=:url LIMIT 1');
                        $getStore->execute(array(
                            ':url' => $storeUrl,
                        ));
                        $storeData = $getStore->fetchAll(PDO::FETCH_ASSOC);

                        return $this->redirectToRoute('app_store_store_index', array('store' => $storeData[0]['url']));
                    } else {
                        $errorMessage = $addStore->errorInfo();
                        $createStoreForm->addError(new FormError('We could not create your forum. (ERROR: '.$errorMessage[2].')'));
                    }
                }
            }
        }

        return $this->render('store/create-new-store.html.twig', array(
            'current_user'      => $currentUser->aUser,
            'create_store_form' => $createStoreForm->createView(),
        ));
    }

    public function storeIndexAction()
    {
        // Does the store even exist?
        if (!Store::urlExists($this->parameters['store'])) {
            return $this->render('error/error404.html.twig');
        }

        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $storeId = Store::url2Id($this->parameters['store']);
        $store   = new Store($storeId);

        $productList = StoreProduct::getProductList($store->getVar('id'));
        $userLanguage = 'en'; // TODO: Make this editable by the user
        $userCurrency = 'dollar';  // TODO: Make this editable by the user

        foreach ($productList as $index => $product) {
            $productList[$index]['short_description'] = $product['short_description_' . $userLanguage];
            $productList[$index]['price'] = $product['price_' . $userCurrency];
            $productList[$index]['in_sale'] = is_null($product['price_sale_' . $userCurrency]) ? false : true;
            $productList[$index]['price_sale'] = $productList[$index]['in_sale'] ? $product['price_sale_' . $userCurrency] : null; // TODO: Show it when there is a sale
        }

        return $this->render('store/theme1/index.html.twig', array(
            'current_user'  => $currentUser->aUser,
            'current_store' => $store->storeData,
            'product_list'  => $productList,
        ));
    }

    public function storeProductAction()
    {
        // Does the store even exist?
        if (!Store::urlExists($this->parameters['store'])) {
            return $this->render('error/error404.html.twig');
        }

        // Does the product even exist?
        if (!StoreProduct::productExists($this->parameters['product'])) {
            return $this->render('error/error404.html.twig');
        }

        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $storeId      = Store::url2Id($this->parameters['store']);
        $store        = new Store($storeId);
        $userLanguage = 'en'; // TODO: Make this editable by the user
        $userCurrency = 'dollar';  // TODO: Make this editable by the user
        $product      = new StoreProduct($this->parameters['product']);

        $product->productData['description'] = $product->getVar('long_description_' . $userLanguage);
        $product->productData['price'] = $product->getVar('price_' . $userCurrency);
        $product->productData['in_sale'] = is_null($product->getVar('price_sale_' . $userCurrency)) ? false : true;
        $product->productData['price_sale'] = $product->productData['in_sale'] ? $product->getVar('price_sale_' . $userCurrency) : null;

        $addToCartForm = $this->createFormBuilder()
            ->add('store_id', HiddenType::class, array(
                'data' => $store->getVar('id'),
            ))
            ->add('product_id', HiddenType::class, array(
                'data' => $product->getVar('id'),
            ))
            ->add('product_count', TextType::class, array( // TODO: This should be IntegerType
                'label' => 'Amount',
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter a number')),
                    //new Type(array('type' => 'int', 'message' => 'The value {{ value }} is not a valid {{ type }}.'))
                ),
                'disabled' => $product->getVar('stock_available') == 0 ? true : false,
            ))
            ->add('send', SubmitType::class, array(
                'label' => 'Add to shopping cart',
                'disabled' => $product->getVar('stock_available') == 0 ? true : false,
            ))
            ->getForm();

        $addCommentForm = $this->createFormBuilder()
            ->add('product_id', HiddenType::class, array(
                'data' => $product->getVar('id'),
            ))
            ->add('rating', HiddenType::class, array(
                'data' => 0,
            ))
            ->add('comment', TextareaType::class, array(
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter a message')),
                ),
            ))
            ->add('send', SubmitType::class, array(
                'label' => 'Write a review',
            ))
            ->getForm();

        // Add product to cart
        /** @var Request $request */
        $request = $this->get('kernel')->getRequest();
        $addToCartForm->handleRequest($request);
        if ($addToCartForm->isSubmitted() && $addToCartForm->isValid()) {
            $formData = $addToCartForm->getData();
            $formData['product_count'] = intval($formData['product_count']); // TODO: As product_count is TextType, we have to convert it to a Int

            if (LOGGED_IN) {
                // Is registered user
                $user = new UserInfo(USER_ID);
                if (StoreCheckout::cartExistsForUser($user)) {
                    // Cart exists
                    $cartId = StoreCheckout::getCartIdFromUser($user);
                    $cart = new StoreCheckout($cartId, true, $user);
                } else {
                    // Create new cart
                    $cart = new StoreCheckout(null, true, $user);
                }
            } else {
                // Is a guest
                $cart = new StoreCheckout(null);
            }
            $store = new Store($formData['store_id']);
            $product2 = new StoreProduct($formData['product_id']);
            $cart->addToCart($store, $product2, $formData['product_count']);

            $this->addFlash('product_added', 'Your product was added to the cart');
        }

        // Add a review
        /** @var Request $request */
        $request = $this->get('kernel')->getRequest();
        $addCommentForm->handleRequest($request);
        if ($addCommentForm->isSubmitted() && $addCommentForm->isValid()) {
            $formData = $addCommentForm->getData();
            StoreComments::addReview($formData['product_id'], USER_ID, $formData['comment'], $formData['rating']);
        }

        $comments = StoreComments::getCommentList($product->getVar('id'));
        foreach($comments as $index => $comment) {
            $comments[$index]['formatted_username'] = AccountTools::formatUsername($comment['user_id']);
        }

        return $this->render('store/theme1/product.html.twig', array(
            'current_user'  => $currentUser->aUser,
            'current_store' => $store->storeData,
            'current_product'  => $product->productData,
            'comments' => $comments,
            'add_to_cart_form' => $addToCartForm->createView(),
            'add_comment_form' => $addCommentForm->createView(),
        ));
    }

    public function storeCheckoutAction()
    {
        // Does the store even exist?
        if (!Store::urlExists($this->parameters['store'])) {
            return $this->render('error/error404.html.twig');
        }

        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $storeId      = Store::url2Id($this->parameters['store']);
        $store        = new Store($storeId);
        $userLanguage = 'en'; // TODO: Make this editable by the user
        $userCurrency = 'dollar';  // TODO: Make this editable by the user

        if (LOGGED_IN) {
            $rawCart = new StoreCheckout(StoreCheckout::getCartIdFromUser($currentUser), true, $currentUser);
        } else {
            $rawCart = new StoreCheckout(StoreCheckout::getCartIdFromUser($currentUser), false);
        }
        $cart = $rawCart->getProductsForStore($store->getVar('id'));
        foreach ($cart as $key => $productInfo) {
            $product = new StoreProduct($productInfo['id']);

            $product->productData['description'] = $product->getVar('long_description_' . $userLanguage);
            $product->productData['price'] = $product->getVar('price_' . $userCurrency);
            $product->productData['in_sale'] = is_null($product->getVar('price_sale_' . $userCurrency)) ? false : true;
            $product->productData['price_sale'] = $product->productData['in_sale'] ? $product->getVar('price_sale_' . $userCurrency) : null;
            $product->productData['in_cart'] = $productInfo['count'];

            $productInCart = array_merge($cart[$key], $product->productData);
            $cart[$key] = $productInCart;
        }

        $checkoutForm = $this->createFormBuilder()
            ->add('name', TextType::class, array(
                'label' => 'Full name',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('firstname') : '') . ' ' . (!empty($currentUser->aProfile) ? $currentUser->getFromProfile('lastname') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your full name')),
                ),
            ))
            ->add('email', TextType::class, array(
                'label' => 'Email',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromUser('email') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your email')),
                ),
            ))
            ->add('phone', TextType::class, array(
                'label' => 'Phone Nr.',
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your phone number')),
                ),
            ))
            ->add('location_street', TextType::class, array(
                'label' => 'Street',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('location_street') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your street')),
                ),
            ))
            ->add('location_street_number', TextType::class, array(
                'label' => 'House Nr.',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('location_street_number') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your house nr.')),
                ),
            ))
            ->add('location_postal_code', TextType::class, array(
                'label' => 'Postal code',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('location_zip') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your zip code')),
                ),
            ))
            ->add('location_city', TextType::class, array(
                'label' => 'City',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('location_city') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your city')),
                ),
            ))
            ->add('location_country', TextType::class, array(
                'label' => 'Country',
                'attr'  => array(
                    'value'       => (USER_ID != -1 ? $currentUser->getFromProfile('location_country') : ''),
                ),
                'constraints' => array(
                    new NotBlank(array('message' => 'Please enter your country')),
                ),
            ))
            ->add('send', SubmitType::class, array(
                'label' => 'Order',
            ))
            ->getForm();

        /** @var Request $request */
        $request = $this->get('kernel')->getRequest();
        $checkoutForm->handleRequest($request);
        if ($checkoutForm->isSubmitted() && $checkoutForm->isValid()) {
            $formData = $checkoutForm->getData();
            $ownerUser = new UserInfo($store->getVar('owner_id'));

            $productUnavailable = array();
            $newProductsStock = array();
            foreach ($cart as $key => $productInfo) {
                $product = new StoreProduct($productInfo['id']);

                if($product->getVar('stock_available') >= $productInfo['count']) {
                    $newProductsStock[$product->getVar('id')] = $product->getVar('stock_available') - $productInfo['count'];
                } else {
                    $product->productData['count'] = $productInfo['count'];
                    $productUnavailable[$key] = $product->productData;
                }
            }

            if (count($productUnavailable) > 0) {
                foreach ($productUnavailable as $key => $item) {
                    $this->addFlash('products_unavailable', $item['name'].' has only '.$item['stock_available'].' left! You wanted '.$item['count']);
                }
            } else {
                // TODO: Order confirmations form is seen as Spam
                $message = Swift_Message::newInstance();
                $imgDir1 = $message->embed(Swift_Image::fromPath(Kernel::getIntent()->getRootDir().'/web/assets/logo-long.png'));
                $message->setSubject('Order confirmation')
                    ->setFrom(array($store->getVar('email') => $store->getVar('name')))
                    ->setTo(array(trim($formData['email']) => trim($formData['name'])))
                    ->setBcc(array($store->getVar('email')))
                    ->setReplyTo(array($store->getVar('email') => $store->getVar('name')))
                    ->setBody($this->renderView('store/mail/order-confirmation.html.twig', array(
                        'current_store' => $store->storeData,
                        'order_form' => $formData,
                        'header_image' => $imgDir1,
                        'ordered_time' => time(),
                        'cart' => $cart,
                    )), 'text/html');

                /** @var \Swift_Mailer $mailer */
                $mailer = $this->get('mailer');
                $mailSent = $mailer->send($message);

                if($mailSent) {
                    $formData['delivery_type'] = '';
                    $rawCart->makeOrder($store->getVar('id'), $formData);

                    $this->addFlash('order_sent', 'Your order has been sent! Thanks!');
                } else {
                    $this->addFlash('order_not_sent', 'Your order was not sent. Try again!');
                }
            }
        }

        return $this->render('store/theme1/checkout.html.twig', array(
            'current_user'  => $currentUser->aUser,
            'current_store' => $store->storeData,
            'checkout_form' => $checkoutForm->createView(),
            'cart' => $cart,
        ));
    }

    public function storeDoCheckVoucherAction()
    {
        // Does the store even exist?
        if (!Store::urlExists($this->parameters['store'])) {
            return $this->render('error/error404.html.twig');
        }
        $storeId = Store::url2Id($this->parameters['store']);
        $store   = new Store($storeId);

        $database = DatabaseContainer::getDatabase();
        $sql = $database->prepare('SELECT * FROM `store_voucher` WHERE `code`=:voucher AND `store_id`=:store_id LIMIT 1');
        $sql->execute(array(
            ':voucher' => $this->getRequest()->query->get('code'),
            ':store_id' => $store->getVar('id'),
        ));

        if ($sql->rowCount() == 0) {
            $result = new \SimpleXMLElement('<root></root>');
            $result->addChild('result', 'invalid');
        } else {
            $voucherInfo = $sql->fetchAll(\PDO::FETCH_ASSOC);

            $result = new \SimpleXMLElement('<root></root>');
            $result->addChild('result', 'valid');
            $result->addChild('type', $voucherInfo[0]['type']);
            $result->addChild('amount', $voucherInfo[0]['amount']);
        }
        header('Content-Type: text/xml');
        return $result->asXML();
    }

    public function storeDoClearCartAction()
    {
        // Does the store even exist?
        if (!Store::urlExists($this->parameters['store'])) {
            return $this->render('error/error404.html.twig');
        }

        Account::updateSession();
        $currentUser = new UserInfo(USER_ID);

        $cart = new StoreCheckout(StoreCheckout::getCartIdFromUser($currentUser), true, $currentUser);
        $cart->clearCart();
        return '';
    }
}
