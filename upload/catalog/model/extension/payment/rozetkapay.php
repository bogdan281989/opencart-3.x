<?php


class ModelExtensionPaymentRozetkaPay extends Model {

    private $type = 'payment';
    private $code = 'rozetkapay';
    private $path = 'extension/payment/rozetkapay';
    private $prefix = 'payment_';
    private $token_name = 'user_token';

    public function getMethod($address, $total) {

        $test = false;
        
        if ($this->config->get($this->prefix . 'rozetkapay_test_status') == "1" && isset($this->session->data[$this->token_name])) {
            $test = true;
        }
        
        $this->load->language($this->path);
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" .
                (int) $this->config->get('rozetkapay_geo_zone_id') . "' AND "
                . "country_id = '" . (int) $address['country_id'] . "' AND "
                . "(zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
        
        if (!$this->config->get($this->prefix . 'rozetkapay_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {

            $title = '';

            if ($this->config->get($this->prefix . 'rozetkapay_view_icon_status') == "1") {

                $title .= '<img src="' . HTTPS_SERVER . 'catalog/view/theme/default/image/rpay.png" height="32">';
            }

            if ($this->config->get($this->prefix . 'rozetkapay_view_title_default') == "1") {
                $title .= $this->language->get('text_title');
            } else {

                if ($this->config->get($this->prefix . 'rozetkapay_view_title') !== null &&
                        isset($this->config->get($this->prefix . 'rozetkapay_view_title')[$this->config->get('config_language_id')])) {

                    $title .= $this->config->get($this->prefix . 'rozetkapay_view_title')[$this->config->get('config_language_id')];
                } else {
                    $title .= $this->language->get('text_title');
                }
            }

            if ($test) {
                $title .= '(Test)';
            }

            $method_data = array(
                'code' => $this->code,
                'title' => $title,
                'terms' => '',
                'sort_order' => $this->config->get($this->prefix . 'rozetkapay_sort_order')
            );
        }


        return $method_data;
    }

}
