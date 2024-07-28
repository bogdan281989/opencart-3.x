<?php

//doc
//https://cdn.rozetkapay.com/public-docs/index.html
//php SDK simple v2.2.3
class RozetkaPay {

    const versionSDK = '2.2.3';
    const version = 'v1';
    const urlBase = 'https://api.rozetkapay.com/api/';
    const testLogin = 'a6a29002-dc68-4918-bc5d-51a6094b14a8';
    const testPassword = 'XChz3J8qrr';

    private $login = '';
    private $password = '';
    private $token = '';
    private $headers = array();
    private $callback_url = '';
    private $result_url = '';
    private $currency = 'UAH';
    private $customer_locale = "UK";

    public function __construct() {
        $this->headers[] = 'Content-Type: application/json';
    }

    public function getCallbackURL() {
        return $this->callback_url;
    }

    public function getResultURL() {
        return $this->result_url;
    }

    public function setCallbackURL($callback_url) {
        $this->callback_url = str_replace("&amp;", "&", $callback_url);
    }

    public function setResultURL($result_url) {
        $this->result_url = str_replace("&amp;", "&", $result_url);
    }

    public function setBasicAuth($login, $password) {

        $this->login = $login;
        $this->password = $password;

        $this->token = base64_encode($login . ":" . $password);
        $this->headers[] = 'Authorization: Basic ' . $this->token;
    }

    public function setBasicAuthTest($login = '', $password = '') {
        $this->setBasicAuth(
                empty($login) ? self::testLogin : $login,
                empty($password) ? self::testPassword : $password
        );
    }

    public function getHeaderSignature() {

        foreach (getallheaders() as $key => $value) {
            if ($key === "X-ROZETKAPAY-SIGNATURE") {
                return $value;
            }
        }

        return "";
    }

    public function getSignature($data) {

        if (gettype($data) !== "string") {
            $data = $this->toJSON($data);
        }

        return strtr(base64_encode(sha1($this->password . strtr(base64_encode($data), '+/', '-_') . $this->password, true)), '+/', '-_');
    }

    public function checkoutCreat($data) {

        if (empty($data->callback_url)) {
            $data->callback_url = $this->getCallbackURL();
        }

        if (empty($data->result_url)) {
            $data->result_url = $this->getResultURL();
        }

        if ($data->amount <= 0) {
            throw new \Exception('Fatal error: amount!');
        }
        //fix
        $data->amount = $this->fixAmount($data->amount);
        foreach ($data->products as $key => $product) {
            $data->products->net_amount = $this->fixAmount($product->net_amount);
        }

        $data = (array) $data;

        $data['customer'] = (array) $data['customer'];
        $data['products'] = (array) $data['products'];

        if (isset($data['customer']) && !empty($data['customer'])) {

            if (isset($data['customer']['locale']) && !empty($data['customer']['locale'])) {

                if (!in_array($data['customer']['locale'], array("UK", "EN", "ES", "PL", "FR", "SK", "DE"))) {
                    $data['customer']['locale'] = $this->customer_locale;
                }
            }
            
            if (isset($data['customer']['phone']) && !empty($data['customer']['phone'])) {
                
                $data['customer']['phone'] = str_replace(['(', ")", "-", " "], "", $data['customer']['phone']);
                
            }
        }

        $data['external_id'] = (string) $data['external_id'];

        foreach ($data as $key => $value) {
            if (is_null($value) || empty($value)) {
                unset($data[$key]);
            }
        }

        return $this->sendRequest("payments/" . self::version . "/new", "POST", $data);
    }

    public function paymentRefund($data) {

        if (empty($data->callback_url)) {
            $data->callback_url = $this->getCallbackURL();
        }

        if (empty($data->result_url)) {
            $data->result_url = $this->getResultURL();
        }

        $data->amount = $this->fixAmount($data->amount);

        $data = (array) ($data);

        $data['external_id'] = (string) $data['external_id'];

        foreach ($data as $key => $value) {
            if (is_null($value) || empty($value)) {
                unset($data[$key]);
            }
        }

        return $this->sendRequest("payments/" . self::version . "/refund", "POST", $data);
    }

    public function paymentInfo($external_id) {

        return $this->sendRequest("payments/" . self::version . "/info?external_id=" . $external_id);
    }

    public function сallbacks() {

        $entityBody = file_get_contents('php://input');

        if ($this->getSignature($entityBody) !== $this->getHeaderSignature()) {
            return [];
        }

        $json = [];

        try {
            return json_decode($entityBody);
        } catch (Exception $exc) {
            return [];
        }
    }

    public function getdebug() {
        $this->debug;
    }

    private $debug;

    private function sendRequest($path, $method = 'GET', $data = array(), $headers = array(), $useToken = true) {


        $data_ = $data;
        $url = self::urlBase . $path;

        $method = strtoupper($method);

        $headers = $this->headers;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'simple-php-sdk',
        ));

        switch ($method) {

            case 'POST':
                $data = json_encode($data);

                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
        }

        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headerCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $responseBody = substr($response, $header_size);
        $responseHeaders = substr($response, 0, $header_size);
        $ip = curl_getinfo($curl, CURLINFO_PRIMARY_IP);
        $curlErrors = curl_error($curl);

        curl_close($curl);

        $jsonResponse = [];

        try {
            $jsonResponse = json_decode($responseBody);
        } catch (\Exception $exc) {
            echo $exc->getTraceAsString();
        }

        $retval = new \stdClass();
        $retval->request = new \stdClass();

        $retval->request->url = $url;
        $retval->request->headers = $headers;
        $retval->request->data = $data_;
        $retval->data = $jsonResponse;
        $retval->http_code = $headerCode;
        $retval->headers = $responseHeaders;
        $retval->ip = $ip;
        $retval->curlErrors = $curlErrors;
        $retval->method = $method . ':' . $url;
        $retval->timestamp = date('Y-m-d h:i:sP');

        $this->debug = $retval;

        if ($headerCode == 200) {
            return [$jsonResponse, false];
        } else {
            return [false, $jsonResponse];
        }
    }

    public function fixAmount($amount) {

        $amounts = explode(".", $amount, 2);

        if (count($amounts) > 1) {
            list($amount1, $amount2) = $amounts;
            $amount = ($amount1 . "." . substr($amount2, 0, 2));
        }

        return $amount;
    }
}

class RPayCheckoutCreatRequest {

    /**
     * 
     * @var int
     */
    public $amount = 0;

    /**
     * 
     * @var string
     */
    public $callback_url = '';

    /**
     * 
     * @var string
     */
    public $result_url = '';
    public $confirm = true;

    /**
     * 
     * @var string
     */
    public $currency = 'UAH';
    public $customer;

    /**
     * 
     * @var string
     */
    public $description = '';

    /**
     * 
     * @var string
     */
    public $external_id = '';

    /**
     * 
     * @var string
     */
    public $payload = '';
    public $products = [];
    public $properties;
    public $recipient;

    /**
     * 
     * @var string
     */
    public $mode = 'hosted'; //direct or hosted    
}

class RPayCustomer {

    /**
     * 
     * @var string
     */
    public $color_mode = "light";

    /**
     * 
     * @var string
     */
    public $locale = "";

    /**
     * 
     * @var string
     */
    public $account_number = "";

    /**
     * 
     * @var string
     */
    public $address = "";

    /**
     * 
     * @var string
     */
    public $city = "";

    /**
     * 
     * @var string
     */
    public $country = "";

    /**
     * 
     * @var string
     */
    public $email = "";

    /**
     * 
     * @var string
     */
    public $external_id = "";

    /**
     * 
     * @var string
     */
    public $first_name = "";

    /**
     * 
     * @var string
     */
    public $last_name = "";

    /**
     * 
     * @var string
     */
    public $patronym = "";

    /**
     * 
     * @var 
     */
    public $payment_method;

    /**
     * 
     * @var string
     */
    public $phone = "";

    /**
     * 
     * @var string
     */
    public $postal_code = "";
}

class RPayProduct {

    /**
     * 
     * @var string
     */
    public $id;

    /**
     * 
     * @var string
     */
    public $currency;

    /**
     * 
     * @var string
     */
    public $name;

    /**
     * 
     * @var string
     */
    public $description;

    /**
     * 
     * @var string
     */
    public $category;

    /**
     * 
     * @var string
     */
    public $image;

    /**
     * 
     * @var string
     */
    public $quantity;

    /**
     * 
     * @var string
     */
    public $net_amount;

    /**
     * 
     * @var string
     */
    public $vat_amount;

    /**
     * 
     * @var string
     */
    public $url;
}