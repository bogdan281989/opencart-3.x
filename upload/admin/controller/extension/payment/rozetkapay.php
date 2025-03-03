<?php

class ControllerExtensionPaymentRozetkaPay extends Controller {
    protected $version = '2.2.4';

    private $type = 'payment';
    private $code = 'rozetkapay';
    private $path = 'extension/payment/rozetkapay';    
    private $prefix = 'payment_rozetkapay_';    
    private $token_name = 'user_token';
    
    private $type_code = '';
    
    private $error = array();
    
    private $token_value = '';
    private $tokenUrl = '';

    private $log_file = 'rozetkapay';    
    private $extLog;
    
    public function __construct($registry) {
        parent::__construct($registry);
        
        $this->load->language($this->path);
        $this->token_value = $this->session->data[$this->token_name];
        $this->tokenUrl = '&' . $this->token_name . '=' . $this->token_value;
        
        include_once DIR_CATALOG  .'controller/'.$this->path.'/php_sdk_simple.php';
		
		$this->rpay = new \RozetkaPay();
		
		if ($this->config->get($this->prefix.'test_status') === "1") {
            $this->rpay->setBasicAuthTest();
        } else {
            $this->rpay->setBasicAuth($this->config->get($this->prefix.'login'), $this->config->get($this->prefix.'password'));
        }
		
		if ($this->config->get($this->prefix.'log_status') === "1") {
            $this->_extlog = new Log('rozetkapay.log');
        }
    }
    
    public function getDefaultValue($key, $isArray = false) {
        
        $setting = [
            "login" => "",
            "password" => "",
            "status" => false,
            "mode" 	=> 'pay',
            "sort_order" => "0",
            "geo_zone_id" => "0",
            "holding_status" => false,
            "qrcode_status" => false,
            
            "send_info_customer_status" => false,
            "send_info_products_status" => false,
            
            "order_status_init" => "0",
            "order_status_pending" => "0",
            "order_status_success" => "",
            "order_status_failure" => "",
            
            "test_status" => false,
            "log_status" => false,
            
            "view_icon_status" => false,
            "view_title_default" => false,
            "view_title" => false,
            "view_language_detect" => "avto"
        ];
        
        return isset($setting[$key])?$setting[$key]:($isArray ? [] : "");
        
    }

    public function index() {
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->save($data);
        
        $data['breadcrumbs'] = $this->breadcrumbs();

        $data['action'] = $this->SysUrl($this->path, $this->tokenUrl, true);
        $data['cancel'] = $this->SysUrl('extension/extension', $this->tokenUrl . '&type=payment', true);
        
        $data['href_log_download'] = $this->SysUrl($this->path . '/logdownload', $this->tokenUrl, true);
        $data['href_log_clear'] =  $this->SysUrl($this->path . '/logclear', $this->tokenUrl, true);
        $data['log'] = '';        

        $arr = array(
            "login", "password", "status", "mode", "sort_order", "geo_zone_id", "holding_status", "qrcode_status",
            "language_detect", "currency_detect",
            
            
            "order_status_init","order_status_pending", "order_status_success","order_status_failure",
            "test_status", "log_status",
            
            'send_info_customer_status', 'send_info_products_status', 
            "view_icon_status", "view_title_default", "view_title");

        foreach ($arr as $v) {
            
            $key = $this->prefix . $v;
            
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } elseif ($this->config->get($key) !== null) {
                $data[$key] = $this->config->get($key);
            } else {
                $data[$key] = $this->getDefaultValue($key);
            }
            
        }
        
        
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages(array('start' => 0,'limit' => 999));
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();        
        $this->load->model('localisation/currency');
        
        $data['currencys'] = [];
        $data['currencys'][] = [
            'id' => "avto",
            'name' => $this->language->get("text_rpay_detect_avto"),
        ];
        $currencys = $this->model_localisation_currency->getCurrencies(array('start' => 0,'limit' => 999));
        foreach ($currencys as $currency) {
            $data['currencys'][] = [
                'id' => $currency['currency_id'],
                'name' => $currency['title'] . " (" . $currency['code'] . ")",
            ];
        }
        $data['rpay_languages'] = [];
        $data['rpay_languages'][] = [
            'id' => "avto",
            'name' => $this->language->get("text_rpay_detect_avto"),
        ];
        foreach (["UK", "EN", "ES","PL", "FR", "SK", "DE"] as $value) {
            $data['rpay_languages'][] = [
                'id' => $value,
                'name' => $this->language->get("text_rpay_language_" . $value),
            ];
        }
        
        $data['path'] = $this->path;
        $data['tokenUrl'] = $this->tokenUrl;
		$data['prefix'] = $this->prefix;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->path, $data));
    }

    private function validate() {
        
        if (!$this->user->hasPermission('modify', $this->path)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if((isset($this->request->post[$this->prefix.'status']) && $this->request->post[$this->prefix.'status'] == "1")){
            
            if(isset($this->request->post[$this->prefix.'test_status']) && $this->request->post[$this->prefix.'test_status'] != "1"){

                if (empty($this->request->post[$this->prefix.'login'])) {
                    $this->error['login'] = $this->language->get('error_login');
                }

                if (empty($this->request->post[$this->prefix.'password'])) {
                    $this->error['password'] = $this->language->get('error_password');
                }

            }
            
            $this->load->model('localisation/currency');
            
            $iUAH = $this->model_localisation_currency->getCurrencyByCode('UAH');
            
            if(empty($iUAH)){
                $this->error['warning'] = $this->language->get('error_currency_not_uah');
            }
            
        }
        
        return  !$this->error;
    }
    
    private function save(&$data) {
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            
            if(isset($this->request->post[$this->prefix.'login'])){
                $this->request->post[$this->prefix.'login'] = trim($this->request->post[$this->prefix.'login']);
            }
            
            if(isset($this->request->post[$this->prefix.'password'])){
                $this->request->post[$this->prefix.'password'] = trim($this->request->post[$this->prefix.'password']);
            }
            
            
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting($this->prefix, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->SysUrl('marketplace/extension', $this->tokenUrl . '&type=payment', true));
        }
        
        if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error'] = '';
		}
        
        $arr = array('warning', 'login', 'password', 'order_status_success', 'order_status_failure', 'title');
        
        foreach ($arr as $v)
            $data['error_' . $v] = (isset($this->error[$v])) ? $this->error[$v] : false;
        
        
    }
    
    public function breadcrumbs() {
        
        $breadcrumbs = array();
        
        $breadcrumbs[] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->SysUrl('common/dashboard', $this->tokenUrl, true),
            'separator' => false            
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->SysUrl('marketplace/extension', '&type=payment'. $this->tokenUrl, true),
            'separator' => "::"
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->SysUrl($this->path, $this->tokenUrl, true),
            'separator' => "::"
        );
        
        return $breadcrumbs;
        
    }
    
    public function logdownload() {

		$file = DIR_LOGS . $this->log_file . ".log";

		if (file_exists($file) && filesize($file) > 0) {
			$this->response->addheader('Pragma: public');
			$this->response->addheader('Expires: 0');
			$this->response->addheader('Content-Description: File Transfer');
			$this->response->addheader('Content-Type: application/octet-stream');
			$this->response->addheader('Content-Disposition: attachment; filename="' . 
                    $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . $this->log_file .'.log"');
			$this->response->addheader('Content-Transfer-Encoding: binary');

			$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			$this->session->data['error'] = sprintf($this->language->get('error_warning'), basename($file), '0B');

			$this->response->redirect($this->SysUrl($this->path, $this->tokenUrl, true));
		}
	}
	
	public function logclear() {

        $file = DIR_LOGS . $this->log_file . ".log";

        $handle = fopen($file, 'w+');

        fclose($handle);

        $this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->SysUrl($this->path, $this->tokenUrl, true));
        
	}
    
    public function logrefresh(){
        
        $json = [];
        
        $json['ok'] = true;
        
        $file = DIR_LOGS . $this->log_file . ".log";

		if (file_exists($file)) {
			$size = filesize($file);

			if ($size >= 5242880) {
				$suffix = array(
					'B',
					'KB',
					'MB',
					'GB',
					'TB',
					'PB',
					'EB',
					'ZB',
					'YB'
				);

				$i = 0;

				while (($size / 1024) > 1) {
					$size = $size / 1024;
					$i++;
				}
                $json['ok'] = false;
				$json['warning'] = sprintf($this->language->get('error_log_warning'), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$json['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			}
		}
        
        $this->response->addHeader('Content-Type: application/json');        
        $this->response->setOutput(json_encode($json));
    }
    
    public function getInfoPayment() {
		$data['result'] = array();
		
		$data['user_token'] = $this->session->data['user_token'];
		
		if(isset($this->request->get['external_id'])) {
			$external_id = $this->request->get['external_id'];
		
			if($this->config->get($this->prefix.'test_status') === "1"){
				$external_id .=  "_" . md5($this->request->server['HTTP_HOST']);
			}
			
			$result = $this->convertToObjectArray($this->rpay->paymentInfo($external_id));

			if(!empty($result[0])) {
				$result_data = $result[0];
				
				//Log
				$this->extLog($result_data);
				
				$customer_rows = array('first_name', 'last_name', 'patronym', 'phone', 'email');
				
				$customer = array();
				
				foreach($result_data['customer'] as $key => $customer_) {
					if(in_array($key, $customer_rows) && !empty($customer_)) {
						$customer[] = $customer_;
					}
				}
				
				$status = $this->language->get('text_list_status_success');
				
				if($result_data['refunded']) {
					if($result_data['purchase_details'][0]['amount'] == $result_data['refund_details'][0]['amount']) {
						$status = $this->language->get('text_list_status_full_refund');
					} else {
						$status = $this->language->get('text_list_status_part_refund');
					}
				}
				
				if($result_data['amount_canceled']) {
					$status = $this->language->get('text_list_status_full_refund');
				}
				
				$amount_final = 0;
				
				if($result_data['amount_refunded']) {
					$amount_final = $result_data['amount'] - $result_data['amount_refunded'];
				}	
				
				if($result_data['amount_confirmed']) {
					$amount_final = $result_data['amount'] - $result_data['amount_confirmed'];
				}
				
				$data['result'] = array(
					'uuid'				=> $result_data['external_id'],
					'text_status'		=> $status,
					'status'			=> $result_data['purchase_details'][0]['status_code'],
					'failureReason'		=> !empty($result_data['canceled']) ? $result_data['cancellation_details'] : '',
					'amount'			=> $result_data['amount'],
					'amount_refunded'	=> $result_data['amount_refunded'],
					'amount_confirmed'	=> $result_data['amount_confirmed'],
					'amount_canceled'	=> $result_data['amount_canceled'],
					'amount_final'		=> $amount_final,
					'currency'			=> $result_data['currency'],
					'refunded'			=> $result_data['refunded'],
					'confirmed'			=> $result_data['confirmed'],
					'customer'			=> implode(" ", $customer),
					'createdDate'		=> date('Y-m-d H:i:s', strtotime($result_data['purchase_details'][0]['created_at'])),
					'modifiedDate'		=> date('Y-m-d H:i:s', strtotime($result_data['purchase_details'][0]['processed_at'])),
				);
			}
		}
		
		$this->response->setOutput($this->load->view('extension/payment/rozetkapay_info', $data));
	}
	
	public function confirmPayment() {
		$json = array();
		
		$external_id = !empty($this->request->get['external_id']) ? $this->request->get['external_id'] : '';
		
		if($external_id) {
			$this->load->model('sale/order');
			
			$order_info = $this->model_sale_order->getOrder($external_id);
				
			if($order_info) {
				$dataCheckout = new \RPayCheckoutCreatRequest();

				$dataCheckout->amount = $this->currency->format($this->request->get['amount'], $order_info['currency_code'], false, false);
				$dataCheckout->external_id = $external_id;
				$dataCheckout->currency = $order_info['currency_code'];
				
				if ($this->request->server['HTTPS']) {
					$server = HTTPS_CATALOG;
				} else {
					$server = HTTP_CATALOG;
				}
				
				$dataCheckout->callback_url = $server . 'index.php?route=extension/payment/rozetkapay/callback';
				
				$result = $this->convertToObjectArray($this->rpay->paymentConfirm($dataCheckout));
				
				if(!empty($result[0]['is_success']) && $result[0]['is_success']) {
					$json['success'] = $this->language->get('text_success_confirm');
				} elseif(!empty($result[1])) {						
					$json['error'] = sprintf($this->language->get('text_error_refund_detail'), $result[1]['message']);
				} else {						
					$json['error'] = $this->language->get('text_error_refund');
				}
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');        
        $this->response->setOutput(json_encode($json));
	}
	
	public function paymentCancel() {
		$json = array();
		
		$external_id = !empty($this->request->get['external_id']) ? $this->request->get['external_id'] : '';
		
		if($external_id) {
		
			$this->load->model('sale/order');
			
			$order_info = $this->model_sale_order->getOrder($external_id);
				
			if($order_info) {
				$dataCheckout = new \RPayCheckoutCreatRequest();

				$dataCheckout->amount = $this->currency->format($this->request->get['amount'], $order_info['currency_code'], false, false);
				$dataCheckout->external_id = $external_id;
				$dataCheckout->currency = $order_info['currency_code'];
				
				if ($this->request->server['HTTPS']) {
					$server = HTTPS_CATALOG;
				} else {
					$server = HTTP_CATALOG;
				}
				
				$dataCheckout->callback_url = $server . 'index.php?route=extension/payment/rozetkapay/callback';
				
				$result = $this->convertToObjectArray($this->rpay->paymentCancel($dataCheckout));

				if(!empty($result[0]['is_success']) && $result[0]['is_success']) {
					$json['success'] = $this->language->get('text_success_refund');
				} elseif(!empty($result[1])) {						
					$json['error'] = sprintf($this->language->get('text_error_refund_detail'), $result[1]['message']);
				} else {						
					$json['error'] = $this->language->get('text_error_refund');
				}
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');        
        $this->response->setOutput(json_encode($json));
	}	
	
	public function paymentRefund() {
		$json = array();
		
		$external_id = !empty($this->request->get['external_id']) ? $this->request->get['external_id'] : '';
		
		if($external_id) {
		
			$this->load->model('sale/order');
			
			$order_info = $this->model_sale_order->getOrder($external_id);
				
			if($order_info) {
				$dataCheckout = new \RPayCheckoutCreatRequest();

				$dataCheckout->amount = $this->currency->format($this->request->get['amount'], $order_info['currency_code'], false, false);
				$dataCheckout->external_id = $external_id;
				$dataCheckout->currency = $order_info['currency_code'];
				
				if ($this->request->server['HTTPS']) {
					$server = HTTPS_CATALOG;
				} else {
					$server = HTTP_CATALOG;
				}
				
				$dataCheckout->callback_url = $server . 'index.php?route=extension/payment/rozetkapay/callback';
				
				$result = $this->convertToObjectArray($this->rpay->paymentRefund($dataCheckout));

				if(!empty($result[0]['is_success']) && $result[0]['is_success']) {
					$json['success'] = $this->language->get('text_success_refund');
				} elseif(!empty($result[1])) {						
					$json['error'] = sprintf($this->language->get('text_error_refund_detail'), $result[1]['message']);
				} else {						
					$json['error'] = $this->language->get('text_error_refund');
				}
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');        
        $this->response->setOutput(json_encode($json));
	}
    
    public function SysloadLanguage($langs_key) {
        $results = [];
        foreach ($langs_key as $key) {
            $results[$key] = $this->language->get($key);
        }
        return $results;
    }
    
    public function SysUrl($route, $args = '', $secure = false) {
        
        return  str_replace("&amp;","&", $this->url->link($route, $args, "SSL"));
        
    }
    
    public function install() {
		$this->load->model($this->path);
		
		$this->model_extension_payment_rozetkapay->install();
    }
	
	public function uninstall() {
		$this->load->model($this->path);
		
		$this->model_extension_payment_rozetkapay->uninstall();
    }
    
    public function checkSysSupport() {
        $json = [];
        
        $json['cms'] = 'OpenCart';
        $json['cms_version'] = '3.0.3.8';
        $json['php_version'] = '7.4';
        $json['php_module'] = ['curl', 'gi'];
        
        $json['nodejs'] = '';
        $json['npm'] = '';
        
        $json['sdk'] = 'php_sdk_simple';
        $json['sdk_version'] = '1.5';
        
        $json['currencyUAH'] = true;
        
        $json['setting_module'] = '';
        
        $json['checkFiles'] = [
            'admin/controller/extension/payment/rozetkapay.php' => '4f576h09498576hg94586',
            'catalog/controller/extension/payment/rozetkapay/php_sdk_simple.php' => '659849g6745h867g4789',
        ];
        
        $json['ssl'] = true;
        
        $json['db_column_size'] = 80;
        
        $text = json_encode($json);
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
	
	public function extLog($var){
        if($this->_extlog !== false){
            $this->_extlog->write(json_encode($var, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }
    }
}