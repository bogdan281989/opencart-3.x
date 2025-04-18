<?php

include_once __DIR__ .'/rozetkapay/php_sdk_simple.php';

class ControllerExtensionPaymentRozetkaPay extends Controller {
    
    protected $version = '2.1.5';
    
    private $type = 'payment';
    private $code = 'rozetkapay';
    private $path = 'extension/payment/rozetkapay'; 
    private $prefix = 'payment_rozetkapay_';

    private $error = array();
    private $debug = false;
    private $_extlog = false;
    private $rpay;

    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->model('checkout/order');
        $this->language->load($this->path);

        $this->debug = $this->config->get($this->prefix.'test_status') === "1";

        if ($this->config->get($this->prefix.'log_status') === "1") {
            $this->_extlog = new Log('rozetkapay.log');
        }

        $this->rpay = new \RozetkaPay();

        if ($this->config->get($this->prefix.'test_status') === "1") {
            $this->rpay->setBasicAuthTest();
        } else {
            $this->rpay->setBasicAuth($this->config->get($this->prefix.'login'), $this->config->get($this->prefix.'password'));
        }
    }
    
    public function extLog($var){
		if($this->_extlog !== false){
            $this->_extlog->write(json_encode($var, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }
    }

    public function index() {

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_pay'] = $this->language->get('button_pay');
        $data['button_pay_holding'] = $this->language->get('button_pay_holding');
        
        $data['path'] = $this->path;

        $data['qrcode'] = false;
        $data['pay'] = false;

        $data['error'] = "";

        if ($this->session->data['payment_method']['code'] == 'rozetkapay') {

            $status_qrcode = $this->config->get($this->prefix.'qrcode_status') === "1";
            $data['qrcode'] = $status_qrcode;
			
			$data['referrer'] = $this->config->get($this->prefix.'order_status_init');
            
            $order_id = $this->session->data['order_id'];

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            
            $external_id = $order_id;
			
            if($this->config->get($this->prefix.'test_status') === "1"){
                $external_id .=  "_" . md5($this->request->server['HTTP_HOST']);
            }
            
            $this->rpay->setResultURL($this->url->link($this->path.'/result', 'external_id=' . $external_id, true));
            $this->rpay->setCallbackURL($this->url->link($this->path.'/callback', 'external_id=' . $external_id, true));

            $order_info = $this->model_checkout_order->getOrder($order_id);

            $dataCheckout = new \RPayCheckoutCreatRequest();            
            
            if($this->config->get($this->prefix.'send_info_customer_status') == "1"){
                
                $customer = new \RPayCustomer();
                
                $customer->email = $order_info['email'];
                
                $customer->first_name = (empty ($order_info['payment_firstname']))?$order_info['firstname']:$order_info['payment_firstname'];
                $customer->last_name = (empty ($order_info['payment_lastname']))?$order_info['lastname']:$order_info['payment_lastname'];
                
                $customer->country = $order_info['payment_iso_code_2'];
                $customer->city = $order_info['payment_city'];
                $customer->postal_code = $order_info['payment_postcode'];
				$customer->address = str_replace(array("(", ")", "[", "]"), '', $order_info['payment_address_1']);
                $customer->phone = $order_info['telephone'];
                
                if($this->config->get($this->prefix.'language_detect') == "avto"){
                    
                    $langs = explode("-", $order_info['language_code']);

                    if(isset($langs[0])){
                        $customer->locale = strtoupper($langs[0]);
                    }
                    
                }else{
                    $customer->locale = $this->config->get($this->prefix.'language_detect');
                }
                
                $dataCheckout->customer = $customer;
            }
			
			if($this->config->get($this->prefix.'currency_detect') == "avto"){
				$currency = $order_info['currency_code'];
            }else{				
				$currency = $this->config->get($this->prefix.'currency_detect');
            }
            
            if($this->config->get($this->prefix.'send_info_products_status') == "1"){
                
                $this->load->model('tool/image');
                $this->load->model('catalog/product');
                
                $products = $this->model_checkout_order->getOrderProducts($order_id);
                
                foreach ($products as $product_) {
                    
                    $product_info = $this->model_catalog_product->getProduct($product_['product_id']);
                    
                    $productNew = new \RPayProduct();
                    
                    $productNew->id = $product_['product_id'];
                    $productNew->name = $product_['name'];
                    
                    if(!empty($product_info['image'])){
                        $productNew->image = $image = $this->model_tool_image->resize(
                                $product_info['image'], 
                                $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), 
                                $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
                    }
                    $productNew->quantity = $product_['quantity'];
                    $productNew->net_amount = $this->currency->format($product_['total'], $currency, $this->currency->getValue($currency), false);
                    $productNew->vat_amount = $product_['tax'];
                    
                    $productNew->url = $this->url->link('product/product', 'product_id=' . $product_['product_id'] , true);
                    
                    $dataCheckout->products[] = $productNew; 
                }
            }           

            $dataCheckout->amount = $this->currency->format($order_info['total'], $currency, $this->currency->getValue($currency), false);
            $dataCheckout->external_id = $external_id;
            $dataCheckout->currency = $currency;
			
			if($this->config->get($this->prefix.'mode') == 'hold') {
				$dataCheckout->confirm = false;
			}

            $this->extLog($dataCheckout);

            list($result, $error) = $this->rpay->checkoutCreat($dataCheckout);

            $this->extLog($result);
            $this->extLog($error);
			
            $data['pay'] = false;
            
            if ($error === false) {
                if (isset($result->action) && $result->action->type == "url") {
                    $data['pay_href'] = $result->action->value;
                    $data['pay'] = true;
                }
            } else {
                //$json['alert'][] = $this->language->get('error_code_' . $error->code);
                $data['error'] .= "<br>".$error->message;
            }
            
            $data['pay_qrcode'] = $status_qrcode;
            

            if (isset($result->data)) {
                $data['message'] = $result->data['message']. ' ('.$result->data['param'].')';
            } elseif (isset($result->message)) {
                $data['message'] = $result->message. ' ('.$result->param.')';
            }
            if(!empty($data['error'])){
                $this->extLog('error');
                $this->extLog($data['error']);  
                $this->extLog($this->rpay->getdebug()); 

                $this->extLog($dataCheckout); 
                $this->extLog($result); 
            }

            return $this->load->view($this->path, $data);
        }

        return "";
    }
    

    public function callback() {
        
        $this->extLog('fun: callback');
        $this->extLog(file_get_contents('php://input'));
        
        $result = $this->rpay->сallbacks();
    
        if(!isset($result->external_id)){
            $this->extLog('Failure error return data:');
            return;
        }
        
        list($order_id) = explode("_", $result->external_id);
        
        $this->extLog('result:');
        $this->extLog($result);
        
        $status = $result->details->status;
        $operation = $result->operation;
   
        $this->extLog('    order_id: ' . $order_id);
        $this->extLog('    status: ' . $status);
        
        $orderStatus_id = $this->getRozetkaPayStatusToOrderStatus($status, $operation);
        
        $this->extLog('    orderStatus_id: ' . $orderStatus_id);
        
        $status_holding = isset($this->request->get['holding']);        
        $this->extLog('    hasHolding: ' . $status_holding);
        
        $refund = isset($this->request->get['refund']);        
        $this->extLog('    hasRefund: ' . $refund);

        $order_info = $this->model_checkout_order->getOrder($order_id);

		if ($orderStatus_id != "0" && $order_info['order_status_id'] != $orderStatus_id) {
			$this->model_checkout_order->addOrderHistory($order_id, $orderStatus_id, 'RozetkaPay' . (($status_holding)?' holding':''), false);
		}
    }

    public function result() {
        
        $this->extLog('fun: result');
        
        
        if (isset($this->request->get['external_id'])) {
            $external_id = $this->request->get['external_id'];
        }else{
            $external_id = '';
        }
        
        list($order_id) = explode("_", $external_id);
        
        $this->extLog('    order_id: ' . $order_id);
		
		$data['lang'] = $this->language->get('code');
		$data['redirect_success'] = $this->url->link('checkout/success', '', 'SSL');
		$data['redirect_fail'] = $this->url->link('checkout/failure', '', 'SSL');
		$data['query_url'] = $this->url->link($this->path . '/checkStatusPay', 'order_id=' . $external_id, 'SSL');
		
		$this->response->setOutput($this->load->view('extension/payment/rozetkapay_check', $data));        
    }
	
	public function checkStatusPay() {
		$json = array();
		
		$order_id = !empty($this->request->get['order_id']) ? $this->request->get['order_id'] : false;
		
		$result = $this->convertToObjectArray($this->rpay->paymentInfo($order_id));

		if(!empty($result[0]['purchased']) && !empty($order_id)) {
			$json['status'] = true;
		} else {			
			$json['status'] = false;
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    public function getRozetkaPayStatusToOrderStatus($status, $operation) {
		if($operation == 'refund') {
			return $this->config->get($this->prefix.'order_status_refund');
		}

        switch ($status) {
            case "init":
                return $this->config->get($this->prefix.'order_status_init');
                break;
            case "pending":
                return $this->config->get($this->prefix.'order_status_pending');
                break;
            case "success":
                return $this->config->get($this->prefix.'order_status_success');
                break;
            case "failure":
                return $this->config->get($this->prefix.'order_status_failure');
                break;

            default:
                return "0";
                break;
        }
    }
    
    public function genQrCode() {
        
        if(isset($this->request->post['text'])){
            
            include_once __DIR__ .'/rozetkapay/phpqrcode.php';
            
            $text = (string)$this->request->post['text'];
            
            ob_start();
            QRcode::png($text, null, QR_ECLEVEL_L, 10, 2);
            $imageData = ob_get_contents(); 
            ob_end_clean(); 

            echo 'data:image/png;base64,'.base64_encode($imageData);
        }
    }
	
	public function referrer() {
		$order_id = !empty($this->session->data['order_id']) ? $this->session->data['order_id'] : 0;
		
		if($order_id) {
			$this->model_checkout_order->addOrderHistory($order_id, $this->config->get($this->prefix.'order_status_init'));
		}
	}
	
	private function convertToObjectArray($data) {
		if (is_object($data)) {
			$data = (array) $data;
		}
		
		if (is_array($data)) {
			foreach ($data as &$value) {
				$value = $this->convertToObjectArray($value);
			}
		}
		
		return $data;
	}

}