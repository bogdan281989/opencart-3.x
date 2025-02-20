<?php

class ModelExtensionPaymentRozetkaPay extends Controller {    
    public function install() {
        $query_row = $this->db->query("SELECT column_name, data_type, character_maximum_length FROM information_schema.columns where TABLE_SCHEMA = '" . DB_DATABASE . "' and table_name = '" . DB_PREFIX . "order' and column_name = 'payment_method'");

        if($query_row->num_rows){
            $length = (int)$query_row->row['character_maximum_length'];
            $lengthNow = strlen('<img src="'.HTTPS_SERVER.'image/payment/rozetkapay/rpay.png" height="32">')+80;
            
            if($length <= $lengthNow){
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` MODIFY `payment_method` varchar(" . (int)$lengthNow . ")");
            }
        } 
    }

	public function uninstall() {
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` MODIFY `payment_method` varchar(128)");
	}

}
