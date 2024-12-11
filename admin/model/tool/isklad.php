<?php

require_once( DIR_SYSTEM . "/engine/neoseo_model.php");

class ModelToolIsklad extends NeoSeoModel
{
    public $connector;
    public $auth_id;
    public $auth_key;
    public $auth_token;
    public $count_request = 0;
    public $method_inventory_card = 'UpdateInventoryCard';
    public $method_create_order = 'CreateNewOrder';
    public $method_create_supplier = 'CreateSupplier';
    public $method_shipment_notify = 'ShipmentNotify';
    public $filename_delivery = 'https://api.isklad.eu/xml-feed/delivery.xml';
    public $filename_payment_method = 'https://api.isklad.eu/xml-feed/payment.xml';
    public $filename_order_status = 'https://api.isklad.eu/xml-feed/order-status.xml';

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "isklad";
        $this->_logFile = $this->_moduleSysName . ".log";
        $this->debug = $this->config->get($this->_moduleSysName . "_debug") == 1;
        $this->init();
    }

    public function init(){
        $this->library('Isklad/Connector');
        $this->auth_id = $this->config->get($this->_moduleSysName.'_auth_id');
        $this->auth_key = $this->config->get($this->_moduleSysName.'_auth_key');
        $this->auth_token = $this->config->get($this->_moduleSysName.'_auth_token');
        $this->connector = new Connector($this->auth_id, $this->auth_key, $this->auth_token);
    }

    public function insertInventoryCard(){
      $product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE status=1 AND product_id > 20689");
        if($product_query->num_rows){
            foreach ($product_query->rows as $product_row) {
                $product_isklad_query = $this->db->query("SELECT isklad_id FROM " . DB_PREFIX . "product_isklad WHERE product_id=".$product_row['product_id']);
                if(!$product_isklad_query->num_rows){
                    $this->UpdateInventoryCard($product_row);
                }
            }
        }
    }

    public function updateProductIsklad(){
        $product_query = $this->db->query("SELECT p.* FROM " . DB_PREFIX . "product_need_update_isklad pi LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id=pi.product_id)");
        if($product_query->num_rows){
            foreach ($product_query->rows as $product_row) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_need_update_isklad WHERE product_id=".(int)$product_row['product_id']);
                $this->UpdateInventoryCard($product_row);
            }
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_need_update_isklad");
		if(!$query->num_rows){
			$this->db->query("TRUNCATE TABLE " . DB_PREFIX . "product_need_update_isklad");
		}
    }

    public function UpdateInventoryCard($product_row){
        $product_id = $product_row['product_id'];
        $product_isklad_id = $product_id;
        $product_model = $product_row['model'];

        $data_product = array(
            "item_id"=> $product_isklad_id,
            "shop_setting_id"=> $this->config->get($this->_moduleSysName.'_isklad_shop_id'),
            "mj"=> "шт.",
            "ean"=> $product_row['barcode'],
            "enabled"=> 1,
            "tax"=> 21,
            "min_order_count"=> 1,
            "is_electronic_product"=> 0,
            "declaration_description"=> $product_row['declaration_description'],
            "commodity_code"=> $product_row['hs_code'],
        );

        $price = $product_row['price'];
        if($product_row['currency_id'] != 3){
            //Переводим цену в евро
            $currency_code = $this->currency->getCodeById($product_row['currency_id']);
            $price = $this->currency->convertAdmin($product_row['price'], $currency_code, 'EUR');
        }
        $data_product['price_without_tax'] = $price;

        $brand = '';
        $brand_info = $this->getManufacturerInfo($product_row['manufacturer_id']);
        if($brand_info){
            $brand = $brand_info['name'];
            $data_product["producer"] = $brand;
            $data_product["supplier"] = $brand;
        }
        $product_name = $brand. ' '. $product_model;
        $data_product["name"] = $product_name;

        if($product_row['image']){
            $data_product["images"] = array(
                array(
                    "url"=> 'https://bravo-dance.com/image/'.$product_row['image'],
                    "order"=> 1,
                    "enabled"=> 1
                )
            );
        }

        $category_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE main_category=1 AND product_id=". $product_id);
        if($category_query->num_rows){
            $category_id = $category_query->row['category_id'];
            $category_path_query = $this->db->query("SELECT DISTINCT (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '/') FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id AND cp.category_id != cp.path_id) WHERE cp.category_id = c.category_id AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.category_id) AS path, (SELECT DISTINCT keyword FROM " . DB_PREFIX . "url_alias WHERE query = 'category_id=" . (int)$category_id . "') AS keyword FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (c.category_id = cd2.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'");
            if($category_path_query->num_rows){
                $category_path = explode('/', $category_path_query->row['path']);
                if($category_path){
                    $data_product["category"] = $category_path;
                }
            }
        }

        $product_option_pro_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_pro_to_product WHERE product_id=". $product_id);
        if($product_option_pro_query->num_rows) {
            //Выгружаем связанные опции
            $product_option_pro_value_query = $this->db->query("SELECT popv.*, popp.price FROM " . DB_PREFIX . "product_option_pro_value popv"
                . " LEFT JOIN " . DB_PREFIX . "product_option_pro_price popp ON (popp.product_option_pro_value_id=popv.product_option_pro_value_id)"
                . " LEFT JOIN " . DB_PREFIX . "product_isklad pi ON (pi.product_option_pro_value_id=popv.product_option_pro_value_id)"
                //. " WHERE popv.product_id=" . $product_id . " AND pi.isklad_id IS NULL AND popp.customer_group_id = 5");
                . " WHERE popv.product_id=" . $product_id . " AND popp.customer_group_id = 5");
            if ($product_option_pro_value_query->num_rows) {
                foreach ($product_option_pro_value_query->rows as $product_pro_row) {
                    $product_option_pro_value_id = $product_pro_row['product_option_pro_value_id'];
                    $product_isklad_id = $product_id . $product_option_pro_value_id;
                    $data_product['item_id'] = $product_isklad_id;
                    $data_product["name"] = $product_name . ' ';
                    $data_product["ean"] = $product_pro_row['barcode'];
                    $price = $product_pro_row['price'];
                    if ($product_row['currency_id'] != 3) {
                        //Переводим цену в евро
                        $currency_code = $this->currency->getCodeById($product_row['currency_id']);
                        $price = $this->currency->convertAdmin($price, $currency_code, 'EUR');
                    }
                    $product_option_info_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_pro_detail popd"
                        . " LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ovd.option_value_id=popd.option_value_id)"
                        . " WHERE ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND popd.product_option_pro_value_id=" . $product_option_pro_value_id . " ORDER BY popd.option_value_id ASC");
                    if ($product_option_info_query->num_rows) {
                        foreach ($product_option_info_query->rows as $option_info_row) {
                            $data_product["name"] .= '/' . str_replace(" ", '', html_entity_decode($option_info_row['name']));
                        }
                    }
                    $response = $this->createRequest($this->method_inventory_card, $data_product);
                    if (isset($response['response']['resp_data']['inventory_id'])) {
                        $isklad_id = $response['response']['resp_data']['inventory_id'];
                        $isset_product_in_skald = $this->getProductIskladId($product_id, $product_option_pro_value_id, 0);
                        if (!$isset_product_in_skald) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_isklad SET product_id={$product_id}, product_option_pro_value_id={$product_option_pro_value_id}, product_option_value_id=0, product_isklad_id={$product_isklad_id}, isklad_id={$isklad_id}");
                        }
                    }
                }
            }
            return;
        }

        $product_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option WHERE product_id=". $product_id);
        if($product_option_query->num_rows) {
            //Выгружаем обычные опции
            $product_option_value_query = $this->db->query("SELECT pov.*,ovd.name  FROM " . DB_PREFIX . "product_option_value pov"
                ." LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ovd.option_value_id=pov.option_value_id)"
                //." LEFT JOIN " . DB_PREFIX . "product_isklad pi ON (pi.product_option_value_id=pov.product_option_value_id)"
                //." WHERE pov.product_id=".$product_id." AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND pi.isklad_id IS NULL ORDER BY ovd.name ASC");
                ." WHERE pov.product_id=".$product_id." AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ovd.name ASC");
            if($product_option_value_query->num_rows){
                foreach ($product_option_value_query->rows as $product_option_row) {
                    $product_option_value_id = $product_option_row['product_option_value_id'];
                    $product_isklad_id = $product_id . $product_option_value_id;
                    $data_product['item_id'] = $product_isklad_id;
                    $data_product["name"] = $product_name . ' ' . $product_option_row['name'];
                    $data_product["ean"] = $product_option_row['barcode'];
                    $price = $product_row['price'] + $product_option_row['price'];
                    if ($product_row['currency_id'] != 3) {
                        //Переводим цену в евро
                        $currency_code = $this->currency->getCodeById($product_row['currency_id']);
                        $price = $this->currency->convertAdmin($price, $currency_code, 'EUR');
                    }
                    $response = $this->createRequest($this->method_inventory_card, $data_product);
                    if (isset($response['response']['resp_data']['inventory_id'])) {
                        $isklad_id = $response['response']['resp_data']['inventory_id'];
                        $isset_product_in_skald = $this->getProductIskladId($product_id, 0, $product_option_value_id);
                        if (!$isset_product_in_skald) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_isklad SET product_id={$product_id}, product_option_pro_value_id=0, product_option_value_id={$product_option_value_id}, product_isklad_id={$product_isklad_id}, isklad_id={$isklad_id}");
                        }
                    }
                }
            }
            return;
        }

        $response = $this->createRequest($this->method_inventory_card, $data_product);
        if(isset($response['response']['resp_data']['inventory_id'])) {
            $isklad_id = $response['response']['resp_data']['inventory_id'];
            $isset_product_in_skald = $this->getProductIskladId($product_id, 0, 0);
            if (!$isset_product_in_skald) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_isklad SET product_id={$product_id}, product_option_pro_value_id=0, product_option_value_id=0, product_isklad_id={$product_isklad_id}, isklad_id={$isklad_id}");
            }
        }
    }

    public function getManufacturerInfo($manufacturer_id){
        $brand_info = array();
        $brand_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id=". (int) $manufacturer_id);
        if ($brand_query->num_rows) {
            $brand_info = $brand_query->row;
        }
        return $brand_info;
    }

    /* Isklad CreateNewOrder - begin */
    public function exportOrders(){
        $orders = $this->getOrdersToExport();
        if(!$orders){
           $this->log("Нет заказов для экспорта");
           return TRUE;
        }
        $shop_id = $this->config->get($this->_moduleSysName.'_shop_id');
        $business_relationship = $this->config->get($this->_moduleSysName.'_business_relationship');
        $relation_payment_methods = $this->config->get($this->_moduleSysName.'_relation_payment_methods');

        $this->load->model('sale/order');
        $this->load->model('localisation/country');
        foreach ($orders as $order_id => $plan_shipping) {
            $this->log("Подготавливаем данные для заказа {$order_id}");
            $order_info = $this->model_sale_order->getOrder($order_id);
            $country_info = $this->model_localisation_country->getCountry($order_info['shipping_country_id']);
            $payment_country_info = array();
            if($order_info['payment_address_country']){
                $payment_country_info = $this->model_localisation_country->getCountry($order_info['payment_address_country']);
            }

            $id_delivery = $this->getIdDelivery($order_info['shipping_code']);

            if(!$order_info['branch_id']){
                //Проверим, возможно адрес доставки - склад.
                $shipping_address = explode(' ', $order_info['shipping_address_1']);
                $street_number = end($shipping_address);
                $street = trim(str_replace( $street_number, '',$order_info['shipping_address_1']));

                $data_branch = array(
                    'id_delivery' => $id_delivery,
                    'post_code'=> $order_info['shipping_postcode'],
                    'city'=> $order_info['shipping_city'],
                    'street'=> $street,
                    'street_number' => $street_number,

                );
                $branch_id = $this->getBranchId($data_branch);
                if($branch_id){
                    $order_info['branch_id'] = $branch_id;
                }
            }
            $branch_info = $this->getBranchInfo($order_info['branch_id']);
            $shipping_cost = $this->getShippingCost($order_id);
            $discount_cost = $this->getDiscountCost($order_id);
            $invoice_date = $this->getInvoiceDate($order_id);

            $data_export = array(
                'shop_setting_id' => $shop_id,
                'business_relationship' => $business_relationship,
                'customer_name' => $order_info['firstname'],
                'customer_surname' => $order_info['lastname'],
                'customer_phone' => $order_info['telephone'],
                'customer_email' => $order_info['email'],
                'name' => $order_info['firstname'],
                'surname' => $order_info['lastname'],
                'phone' => $order_info['telephone'],
                'email' => $order_info['email'],
                'street' => $branch_info ? $branch_info['STREET'] : $order_info['street'],
                'street_number' => $branch_info ? $branch_info['STREET_NUMBER'] : $order_info['house'],
                'door_number' => !$branch_info ? $order_info['door_number'] : 0,
                'county' => !$branch_info ? $order_info['county'] : '',
                'city' => $order_info['shipping_city'],
                'country' => $country_info['iso_code_2'],
                'destination_country_code' => $country_info['iso_code_2'],
                'postal_code' => $order_info['shipping_postcode'],
                'note' => $order_info['comment'],
                'currency' => $order_info['currency_code'],
                'id_delivery' => $id_delivery,
                'id_payment' => isset($relation_payment_methods[$order_info['payment_code']]) ? $relation_payment_methods[$order_info['payment_code']] : 0,
                'payment_card' => 1,
                'payment_cod' => 0,
            );

			if($order_info['branch_id']){
				$data_export['delivery_branch_id'] = $order_info['branch_id'];
			}

			if ($order_info['payment_address_street']) {
				$data_export['fa_company'] = $order_info['payment_address_company'];
				$data_export['fa_street'] = $order_info['payment_address_street'];
				$data_export['fa_street_number'] = $order_info['payment_address_street_number'];
				$data_export['fa_city'] = $order_info['payment_address_city'];
				$data_export['fa_postal_code'] = $order_info['payment_address_postal_code'];
				$data_export['fa_country'] = $payment_country_info ? $payment_country_info['iso_code_2'] : $country_info['iso_code_2'];
				$data_export['fa_icdph'] = $order_info['payment_address_vat'];
			}elseif($branch_info){
				$data_export['fa_street'] = $branch_info['STREET'];
				$data_export['fa_street_number'] = $branch_info['STREET_NUMBER'];
				$data_export['fa_city'] = $order_info['shipping_city'];
				$data_export['fa_country'] = $country_info['iso_code_2'];
				$data_export['fa_postal_code'] = $order_info['shipping_postcode'];
			}else{
				$data_export['fa_street'] = $order_info['street'];
				$data_export['fa_street_number'] = $order_info['house'];
				$data_export['fa_city'] = $order_info['shipping_city'];
				$data_export['fa_country'] = $country_info['iso_code_2'];
				$data_export['fa_postal_code'] = $order_info['shipping_postcode'];
			}

            foreach ($plan_shipping as $date => $order_products) {
                $i = $this->getNextOrderId($order_id);
                $data_export['original_order_id'] = $order_id.'/'.$i;
                if($invoice_date){
                    $data_export['invoice'] = array(
                        'invoice_id' => $order_id,
                        'invoice_date' => $invoice_date,
                    );
                }
                //$data_export['min_delivery_date'] = $date;
                $data_export['delivery_price'] = isset($shipping_cost[$i]) ? $shipping_cost[$i] : 0;
                $data_export['discount_price'] = $discount_cost ? $discount_cost/count($plan_shipping) : 0;

                $products_info = array();
                foreach ($order_products as $order_product_id) {
                    $order_product_info = $this->getOrderProductInfo($order_id, $order_product_id);
                    if($order_product_info && $order_product_info['return_status']!=1) {
                        $products_info[] = array(
                            'item_id' => $order_product_info['product_isklad_id'],
                            'name' => $order_product_info['name'],
                            'count' => $order_product_info['count'],
                            'price' => $order_product_info['price'],
                            'price_with_tax' => $order_product_info['price_with_tax'],
                        );
                    }
                }

                $data_export['items'] = $products_info;

                $this->log('Сформированы данные по заказу '. print_r($data_export,true));
                $response = $this->createRequest($this->method_create_order, $data_export);
                $this->log('Ответ Isklad '. print_r($response,true));
                if(isset($response['response']['resp_data']['order_id'])){
                    $isklad_order_id = $response['response']['resp_data']['order_id'];
                    foreach ($order_products as $order_product_id) {
                        $this->db->query("UPDATE " . DB_PREFIX . "order_product SET isklad_status=2, isklad_order_id={$isklad_order_id}, isklad_order_original_id = '{$data_export['original_order_id']}'  WHERE order_product_id={$order_product_id}");
                    }
                }
                $i++;
            }
        }
    }

    public function getNextOrderId($order_id){
        $i = 1;
        $ids = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id={$order_id} AND isklad_order_original_id!=''");
        if($query->num_rows){
            foreach ($query->rows as $row){
                if($row['isklad_order_original_id']){
                    $isklad_order_original_id = explode('/', $row['isklad_order_original_id']);
                    $ids[] = $isklad_order_original_id[1];
                }
            }
        }
        if($ids){
            $i = max($ids);
            $i ++;
        }

        return $i;
    }

    public function getOrdersToExport(){ 
        $orders = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE isklad_status = 1 ORDER BY plan_shipping_date ASC");$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE isklad_status = 1 ORDER BY plan_shipping_date ASC");
		if($query->num_rows){
            foreach ($query->rows as $row){
                $orders[$row['order_id']][$row['plan_shipping_date']][] = $row['order_product_id'];
            }
        }

        return $orders;
    }

    public function getBranchInfo($branch_id){
        $branch_info = array();

        if($branch_id) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shippingplus_branches WHERE ID = {$branch_id}");
            if ($query->num_rows) {
                $branch_info = $query->row;
            }
        }

        return $branch_info;
    }

    public function getIdDelivery($shipping_code){
        $id_delivery = 0;
        $shipping_id = preg_replace('/[^0-9]/', '', $shipping_code);
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shippingplus WHERE shipping_id = {$shipping_id}");
        if ($query->num_rows) {
            $id_delivery = $query->row['isklad_id'];
        }
        return $id_delivery;
    }

    public function getBranchId($data){
        $branch_id = 0;

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shippingplus_branches WHERE `isklad_id` = '{$data['id_delivery']}' AND `POSTAL_CODE` = '{$data['post_code']}' AND `CITY` = '{$data['city']}' AND `STREET` = '{$data['street']}' AND `STREET_NUMBER` = '{$data['street_number']}'");
        if ($query->num_rows) {
            $branch_id = $query->row['ID'];
        }
        return $branch_id;
    }

    public function getOrderProductInfo($order_id, $order_product_id){
        $order_product_info = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_product_id = {$order_product_id}");
        if ($query->num_rows) {
            $product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = {$query->row['product_id']}");
            if($product_query->num_rows) {
                $product_row = $product_query->row;
                $product_id = $product_row['product_id'];
                $manufacturer_id = $product_row['manufacturer_id'];
                $model = $product_row['model'];

                $brand = '';
                $brand_info = $this->getManufacturerInfo($manufacturer_id);
                if($brand_info){
                    $brand = $brand_info['name'];
                }
                $product_name = $brand. ' '. $model;

                $product_option_pro_value_id = 0;
                $product_option_value_id = 0;
                $query_order_option_pro = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option_pro WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "' AND product_option_pro_value_id >0");
                if($query_order_option_pro->num_rows){
                    $product_option_pro_value_id = $query_order_option_pro->row['product_option_pro_value_id'];

                }
                if(!$product_option_pro_value_id){
                    $query_order_option = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "' LIMIT 1");
                    if($query_order_option->num_rows){
                        $product_option_value_id = $query_order_option->row['product_option_value_id'];
                        $product_name .= ' '.$query_order_option->row['value'];
                    }
                }else{
                    $query_order_option = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "'  ORDER BY `product_option_id` ASC");
                    if($query_order_option->num_rows){
                        foreach ($query_order_option->rows as $row_option) {
                            $product_name .= '/'.$row_option['value'];
                       }
                    }
                }
                //$product_isklad_id = $this->getProductIskladId($product_id, $product_option_pro_value_id, $product_option_value_id);
                //if(!$product_isklad_id){
                $this->UpdateInventoryCard($product_row);
                $product_isklad_id = $this->getProductIskladId($product_id, $product_option_pro_value_id, $product_option_value_id);
               // }

                $order_product_info = array(
                    'product_isklad_id' => $product_isklad_id,
                    'name' => $product_name,
                    'count' => 1,
                    'price' => $query->row['price'],
                    'return_status' => $query->row['return_status'],
                    'price_with_tax' => $query->row['price']+$query->row['tax'],
                );
            }
        }
        return $order_product_info;
    }

    public function getProductIskladId($product_id, $product_option_pro_value_id, $product_option_value_id){
        $product_isklad_id = 0;
        $query_product_isklad = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_isklad WHERE product_id = '" . (int)$product_id . "' AND product_option_pro_value_id = '" . (int)$product_option_pro_value_id . "' AND product_option_value_id = '" . (int)$product_option_value_id . "'");
        if($query_product_isklad->num_rows){
            $product_isklad_id = $query_product_isklad->row['product_isklad_id'];
        }
        return $product_isklad_id;
    }

    public function getShippingCost($order_id){
        $shipping_cost = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = {$order_id} AND code LIKE 'shipping%' ORDER BY order_total_id ASC");
        if ($query->num_rows) {
            foreach ($query->rows as $row){
                $shipping_cost[] = $row['value'];
            }
        }
        return $shipping_cost;
    }

    public function getDiscountCost($order_id){
        $discount_cost = 0;
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = {$order_id} AND value < 0 ORDER BY order_total_id ASC");
        if ($query->num_rows) {
            foreach ($query->rows as $row) {
                $discount_cost += abs($row['value']);
            }
        }
        return $discount_cost;
    }

    public function getInvoiceDate($order_id){
        $isklad_invoice_statuses = $this->config->get($this->_moduleSysName.'_isklad_invoice_statuses');
        $date = false;
        if($isklad_invoice_statuses) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_history WHERE order_status_id IN (".implode(',',$isklad_invoice_statuses).") AND order_id = {$order_id} ORDER BY date_added DESC LIMIT 1");
            if ($query->num_rows) {
                $date = date("Y-m-d", strtotime( $query->row['date_added']));
            }
        }
        return $date;
    }

    /* Isklad CreateNewOrder - end */

    /* Isklad Payment Methods - begin */
    public function paymentMethodList(){
        $xml = simplexml_load_file($this->filename_payment_method, null, LIBXML_NOCDATA);
        if($xml) {
            $isklad_payment_methods = $this->getIskladPaymentMethods();

            $json_string = json_encode($xml);
            $result_array = json_decode($json_string, TRUE);
            foreach ($result_array['PAYMENT'] as $payment) {
                $main_data = array(
                    'ID' =>$payment['ID'],
                    'NAME' => $payment['NAME'],
                    'NAME_EN' => $payment['NAME_EN'],
                    'IS_PAID' => $payment['IS_PAID'],
                    'IS_CARD' => $payment['IS_CARD'],
                    'IS_COD' => $payment['IS_COD'],
                );

                if(isset($isklad_payment_methods[$payment['ID']])){
                    $this->log("Обновляем данные по методу оплаты с ID {$payment['ID']} ({$payment['NAME_EN']})");
                    $this->updateIskladPaymentMethod($main_data);
                }else{
                    $this->log("Добавляем данные по методу оплаты с ID {$payment['ID']} ({$payment['NAME_EN']})");
                    $this->addIskladPaymentMethod($main_data);
                }
            }
        }
    }

    public function getIskladPaymentMethods(){
        $isklad_delivery_types = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "isklad_payment_methods ");
        if($query->num_rows){
            foreach ($query->rows as $row){
                $isklad_delivery_types[$row['ID']] = $row;
            }
        }
        return $isklad_delivery_types;
    }

    public function addIskladPaymentMethod($data){
        $this->db->query("INSERT INTO " . DB_PREFIX . "isklad_payment_methods SET `ID`=".(int)$data['ID'].", `NAME`='".$this->db->escape($data['NAME'])."', `NAME_EN`='".$this->db->escape($data['NAME_EN'])."', `IS_PAID`='".$this->db->escape($data['IS_PAID'])."', `IS_CARD`='".$this->db->escape($data['IS_CARD'])."', `IS_COD`='".$this->db->escape($data['IS_COD'])."'");
        return TRUE;
    }

    public function updateIskladPaymentMethod($data){
        $this->db->query("UPDATE " . DB_PREFIX . "isklad_payment_methods SET `NAME`='".$this->db->escape($data['NAME'])."', `NAME_EN`='".$this->db->escape($data['NAME_EN'])."', `IS_PAID`='".$this->db->escape($data['IS_PAID'])."', `IS_CARD`='".$this->db->escape($data['IS_CARD'])."', `IS_COD`='".$this->db->escape($data['IS_COD'])."' WHERE `ID`=".(int)$data['ID']);
        return TRUE;
    }
    /* Isklad Payment Methods - end */

    /* Isklad Shipment Notify - begin */
    public function exportPreordersManufacturer(){
        $orders = $this->getPreordersToExport();
        if(!$orders){
            $this->log("Нет предзаказов для экспорта");
            return TRUE;
        }

        $shop_id = $this->config->get($this->_moduleSysName.'_shop_id');

        foreach ($orders as $order) {
            $this->log("Подготавливаем данные для предзаказа {$order['preorder_id']}");
            $brand_info = $this->getManufacturerInfo($order['manufacturer_id']);
            if(!$brand_info['isklad_supplier_id']){
                $this->log("ОШИБКА! Бренд не привязан к Isklad");
                continue;
            }
            if(!$order['isklad_tracking_number']){
                $this->log("ОШИБКА! Заказу не присвоен tracking number");
                continue;
            }
            $data_export = array(
                'reference_number' => $order['preorder_id'],
                'shop_setting_id' => $shop_id,
                'date_from' => date("Y-m-d"),
                'date_to' => $order['date_delivery']  && $order['date_delivery'] != '0000-00-00' ? date("Y-m-d", strtotime($order['date_delivery'])) : '',
                'tracking_number' => $order['isklad_tracking_number'],
                'supplier_id' => $brand_info['isklad_supplier_id'],
                'items' => array(),

            );

            if($order['isklad_shipment_notify_id']){
                $data_export['shipment_notify_id'] = $order['isklad_shipment_notify_id'];
            }

            $products = $this->getPreordersProduct($order['preorder_id']);
            if($products){
                $items = array();
                foreach ($products as $product){
                    $order_product_info = $this->getOrderProductInfo($product['order_id'], $product['order_product_id']);
                    if($order_product_info) {
                        $items[$order_product_info['product_isklad_id']][] = $order_product_info['name'];
                    }
                }
                if($items){
                    foreach ($items as $item_id => $item_product){
                        $data_export['items'][] = array(
                            'item_id' => $item_id,
                            'quantity' => count($item_product),
                        );
                    }
                }
            }

            $this->log('Сформированы данные по предзаказу '. print_r($data_export,true));
            $response = $this->createRequest($this->method_shipment_notify, $data_export);
            $this->log('Ответ Isklad '. print_r($response,true));
            if(isset($response['response']['shipment_notify_id'])){
                $shipment_notify_id = $response['response']['shipment_notify_id'];
                $this->db->query("UPDATE " . DB_PREFIX . "preorder_created SET isklad_shipment_notify_id={$shipment_notify_id},isklad_shipment_status=2 WHERE preorder_id={$order['preorder_id']}");
            }
        }
    }

    public function getPreordersToExport(){
        $orders = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "preorder_created WHERE isklad_shipment_status = 1");
        if($query->num_rows){
            $orders = $query->rows;
        }
        return $orders;
    }

    public function getPreordersProduct($preorder_id){
        $products = array();
        $query_products = $this->db->query("SELECT * FROM " . DB_PREFIX . "preorder_created_product WHERE isklad_not_sync_status=0 AND preorder_id = ".$preorder_id);
        if($query_products->num_rows){
            $products = $query_products->rows;
        }
        return $products;
    }
    /* Isklad Shipment Notify - end */

    /* Isklad Delivery Types - begin */
    public function transportTypeList(){
        $this->updatePaymentForShipping();

        $xml = simplexml_load_file($this->filename_delivery, null, LIBXML_NOCDATA);
        if($xml) {
            $isklad_delivery_types = $this->getIskladDeliveryTypes();

            $json_string = json_encode($xml);
            $result_array = json_decode($json_string, TRUE);
            foreach ($result_array['DELIVERY'] as $delivery) {
            // if($delivery['ID']!=14){continue;}
                 $main_data = array(
                     'ID' =>$delivery['ID'],
                     'NAME' => $delivery['NAME'],
                     'IMAGE' => $this->getImageIskladDelivery($delivery['IMAGE'], $delivery['NAME']),
                     'COD_AVAILABILITY' => $delivery['COD_AVAILABILITY'],
                     'TRANSFER_TYPE_NAME' => $delivery['TRANSFER_TYPE_NAME'],
                     'COUNTRIES' => isset($delivery['COUNTRIES']['COUNTRY']) ? $delivery['COUNTRIES']['COUNTRY'] : array(),
                     'PRICES_BY_WEIGHT' => isset($delivery['PRICES_BY_WEIGHT']['TARIFF']) ? $delivery['PRICES_BY_WEIGHT']['TARIFF'] : array(),
                     'BRANCH' => isset($delivery['BRANCH']) ? $delivery['BRANCH'] : array(),
                  );

                 if(isset($isklad_delivery_types[$delivery['ID']])){
                    $this->log("Обновляем данные по складу с ID {$delivery['ID']} ({$delivery['NAME']})");
                    $this->updateIskladDeliveryTypes($main_data);
                 }else{
                     $this->log("Добавляем данные по складу с ID {$delivery['ID']} ({$delivery['NAME']})");
                     $this->addIskladDeliveryTypes($main_data);
                 }

                $this->ShippingPlus($main_data);

            }
            $this->updatePaymentForShipping();
        }
    }

    public function getIskladDeliveryTypes(){
        $isklad_delivery_types = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "isklad_delivery_types ");
        if($query->num_rows){
            foreach ($query->rows as $row){
                $isklad_delivery_types[$row['ID']] = $row;
            }
        }
        return $isklad_delivery_types;
    }

    public function updatePaymentForShipping(){
        $query_shippingplus = $this->db->query("SELECT * FROM " . DB_PREFIX . "shippingplus WHERE isklad_id > 0");
        if($query_shippingplus->num_rows){
            $neoseo_checkout_payment_for_shipping = $this->config->get('neoseo_checkout_payment_for_shipping');
            foreach ($query_shippingplus->rows as $shippingplus){
                $key = 'neoseo_shippingplus.neoseo_shippingplus'.$shippingplus['shipping_id'];
                if(!isset($neoseo_checkout_payment_for_shipping[$key])){
                    $neoseo_checkout_payment_for_shipping[$key] = array(
                        'gopay' => 1,
                        'gopay_google_pay' => 1,
                        'gopay_apple_pay' => 1,
                    );
                }
            }
            $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `key`='neoseo_checkout_payment_for_shipping'");
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET `store_id`=0, `code`='neoseo_checkout', `key`='neoseo_checkout_payment_for_shipping', `value`='".$this->db->escape(json_encode($neoseo_checkout_payment_for_shipping))."', `serialized`=1");
        }

    }

    public function addIskladDeliveryTypes($data){
        $this->db->query("INSERT INTO " . DB_PREFIX . "isklad_delivery_types SET `ID`=".(int)$data['ID'].", `NAME`='".$this->db->escape($data['NAME'])."', `IMAGE`='".$this->db->escape($data['IMAGE'])."', `TRANSFER_TYPE_NAME`='".$this->db->escape($data['TRANSFER_TYPE_NAME'])."', `COD_AVAILABILITY`=".(int)$data['COD_AVAILABILITY']);
        return TRUE;
    }

    public function updateIskladDeliveryTypes($data){
        $this->db->query("UPDATE " . DB_PREFIX . "isklad_delivery_types SET `NAME`='".$this->db->escape($data['NAME'])."', `IMAGE`='".$this->db->escape($data['IMAGE'])."', `TRANSFER_TYPE_NAME`='".$this->db->escape($data['TRANSFER_TYPE_NAME'])."', `COD_AVAILABILITY`=".(int)$data['COD_AVAILABILITY']." WHERE `ID`=".(int)$data['ID']);
        return TRUE;
    }

    public function getImageIskladDelivery($image_isklad, $name){
        $image = '';
        if($image_isklad) {
            $productFolder = $this->translit($name);
            $folder = 'isklad_delivery_types/';
            $image = $folder.$this->saveImage(DIR_IMAGE . $folder, $productFolder,$image_isklad, 0, -1);
        }
        return $image;
    }

    public function ShippingPlus($data){

        $isklad_id = $data['ID'];
        $shipping_name = $data['NAME'];
        //$status = $data['COD_AVAILABILITY'];

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "shippingplus WHERE isklad_id={$isklad_id}");
        if($query->num_rows){
            $shipping_id = $query->row['shipping_id'];
            $this->log("Метод доставки {$shipping_name} найден по привязке ИД {$shipping_id}. Обновляем данные по доставке");
            $this->updateShippingPlus($shipping_id, $data);
        }else{
            $this->log("Метод доставки {$shipping_name} НЕ найден в системе. Создаем новый метод доставки.");
            $this->addShippingPlus($data);
           /* if($status){
                $this->log("Метод доставки {$shipping_name} НЕ найден в системе. Создаем новый метод доставки.");
                $this->addShippingPlus($data);
            }else{
                $this->log("Метод доставки {$shipping_name} отключен в isklad. Пропускаем метод.");
            }*/
        }
        return TRUE;
    }

    public function updateShippingPlus($shipping_id, $data){
        $isklad_id = $data['ID'];
        $shipping_name = $data['NAME'];
        $status = 1;
        if(!$status){
            $this->log("Метод доставки {$shipping_name} отключен в системе Isklad. Оключаем метод и ничего не обновляем.");
            $this->db->query("UPDATE " . DB_PREFIX . "shippingplus SET `status`=0 WHERE `isklad_id`={$isklad_id}");
            return TRUE;
        }
        $this->log("Обновляем метод доставки {$shipping_name}");

        $geo_zone_ids = $this->getGeoZones($data);

        $this->db->query("DELETE FROM " . DB_PREFIX . "shippingplus_shipping_countries WHERE `shipping_id`={$shipping_id}");
        $this->addShippingCountries($shipping_id, $geo_zone_ids);

        $prices = $data['PRICES_BY_WEIGHT'];
        $this->db->query("DELETE FROM " . DB_PREFIX . "shippingplus_shipping_zones WHERE `shipping_id`={$shipping_id}");
        $this->addPriceByWeight($shipping_id, $geo_zone_ids, $prices);

        $branches = $data['BRANCH'];
        $this->db->query("DELETE FROM " . DB_PREFIX . "shippingplus_branches WHERE `shipping_id`={$shipping_id}");
        $this->addBranches($shipping_id, $isklad_id, $branches);

    }

    public function addShippingPlus($data){
        $status = 0;
        $sort_order = 0;
        $price_min = 0;
        $price_max = 0;
        $zone_status = 0;
        $stores = '["on"]';
        $isklad_id = $data['ID'];
        $geo_zone_ids = $this->getGeoZones($data);
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        $this->db->query("INSERT INTO " . DB_PREFIX . "shippingplus SET `status`={$status}, `sort_order`={$sort_order}, `price_min`={$price_min}, `price_max`={$price_max},  `geo_zones_id`='".$this->db->escape( serialize($geo_zone_ids))."', `stores`='".$this->db->escape($stores)."', `zone_status`={$zone_status}, `isklad_id`={$isklad_id}");
        $shipping_id = $this->db->getLastId();
        $shipping_name = $data['NAME'];

        foreach ($languages as $language){
            $this->db->query("INSERT INTO " . DB_PREFIX . "shippingplus_description SET `shipping_id`={$shipping_id}, `language_id`={$language['language_id']}, `name`='".$this->db->escape($shipping_name)."', `description`=''");
        }

        $prices = $data['PRICES_BY_WEIGHT'];
        $this->addPriceByWeight($shipping_id, $geo_zone_ids, $prices);

        $branches = $data['BRANCH'];
        $this->db->query("DELETE FROM " . DB_PREFIX . "shippingplus_branches WHERE `shipping_id`={$shipping_id}");
        $this->addBranches($shipping_id, $isklad_id, $branches);
        return TRUE;
    }

    public function getGeoZones($data){
        $countries = $data['COUNTRIES'];
        $shipping_name = $data['NAME'];
        $isklad_id = $data['ID'];

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "geo_zone WHERE isklad_id={$isklad_id}");
        if(!$query->num_rows){
            $this->db->query("INSERT INTO " . DB_PREFIX . "geo_zone SET name = '" . $this->db->escape($shipping_name. ' isklad' ) . "', description = '" . $this->db->escape($shipping_name) . "', isklad_id='".(int)$isklad_id."', date_added = NOW()");
            $geo_zone_id = $this->db->getLastId();
            $this->log("В системе создана геозона с названием {$shipping_name}");
        }else{
            $geo_zone_id = $query->row['geo_zone_id'];
        }

        $geo_zone_ids[] = $geo_zone_id;

        $this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE  geo_zone_id='".(int)$geo_zone_id."'");
        if($countries){
            foreach ($countries as $key => $country) {
                if($key==="ID"){
                    $this->addGeoZone($geo_zone_id, $countries);
                    break;
                }else{
                    $this->addGeoZone($geo_zone_id, $country);
                }
            }
        }
        return $geo_zone_ids;
    }

    public function addGeoZone($geo_zone_id, $data){
        $iso_code_2 = $data['CODE'];
        $query_country = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "country WHERE iso_code_2 = '" . $this->db->escape($iso_code_2) . "'");
        if(!$query_country->num_rows){
            $this->log("В системе не найдена страна с кодом {$iso_code_2}");
            return false;
        }
        $country_id = $query_country->row['country_id'];

        $this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$country_id . "' AND zone_id = 0 AND geo_zone_id = '" . (int)$geo_zone_id . "'");
        $this->db->query("INSERT INTO " . DB_PREFIX . "zone_to_geo_zone SET country_id = '" . (int)$country_id . "', zone_id = 0, geo_zone_id = '" . (int)$geo_zone_id . "', date_added = NOW()");
        return TRUE;
    }

    public function addShippingCountries($shipping_id, $geo_zone_ids){
        foreach ($geo_zone_ids as $geo_zone_id){
            $query_zone_to_geo_zone = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $geo_zone_id . "'");
            if(!$query_zone_to_geo_zone->num_rows){
                $this->log("В системе не найдена zone_to_geo_zone c geo_zone_id={$geo_zone_id}");
                continue;
            }else{
                foreach ($query_zone_to_geo_zone->rows as $zone_to_geo_zone) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "shippingplus_shipping_countries SET `shipping_id`={$shipping_id}, `country_id`={$zone_to_geo_zone['country_id']}, `geo_zone_id`={$geo_zone_id}");
                }
            }
        }
    }

    public function addPriceByWeight($shipping_id, $geo_zone_ids, $prices){
        if($prices) {
            foreach ($geo_zone_ids as $geo_zone_id) {
                foreach ($prices as $key => $price) {
                    if($key==="FROM"){
                        $this->addPrice($shipping_id, $geo_zone_id, $prices);
                        break;
                    }else{
                        $this->addPrice($shipping_id, $geo_zone_id, $price);
                    }
                }
            }
        }
    }

    public function addPrice($shipping_id, $geo_zone_id, $data){
        $weight = preg_replace("/[^,.0-9]/", '',$data['TO'])/1000;
        $price = preg_replace("/[^,.0-9]/", '',$data['PRICE']);
        $currency = 'EUR';
        if(in_array($shipping_id, array(327, 328, 329, 334))){
            //увеличиваем на 30 процентов
            $price = $price * (1 + 0.30);
        }else{
            //увеличиваем на 36 процентов
            $price = $price * (1 + 0.36);
        }

        $dhl_shipping = array(328,327,334,329,346,344,398,392,393,394,262,15,326,5);
        if(!in_array($shipping_id, $dhl_shipping)){
            //увеличиваем на 22 процентов, все доставки, кроме DHL
            $price = $price * (1 + 0.22);
        }

        //увеличиваем на 1.5 евро все доставки
        $price = $price + 1.5;

        $this->db->query("INSERT INTO " . DB_PREFIX . "shippingplus_shipping_zones SET zone_id = '" . (int)$geo_zone_id . "', shipping_id = '" . (int)$shipping_id . "', weight = '" . (float)$weight . "', price ='".(float) $price."', currency='".$this->db->escape($currency)."'");
        return TRUE;
    }

    public function addBranches($shipping_id, $isklad_id, $branches){
        if($branches) {
            foreach ($branches as $key => $branch) {
                if($key==="ID"){
                    $this->addBranch($shipping_id, $isklad_id, $branches);
                    break;
                }else{
                    $this->addBranch($shipping_id, $isklad_id, $branch);
                }
            }
        }
    }

    public function addBranch($shipping_id, $isklad_id, $data){
        $city = is_array($data['CITY']) ? $data['CITY'][0] : $data['CITY'];
        if(is_array($data['STREET_NUMBER'])){
                $street_number = isset($data['STREET_NUMBER'][0]) ? $data['STREET_NUMBER'][0] : '';
        }else{
            $street_number = $data['STREET_NUMBER'];
        }
        $this->db->query("INSERT INTO " . DB_PREFIX . "shippingplus_branches SET
        `ID`=".(int)$data['ID'].",
        `EXTERNAL_ID`=".(int)$data['EXTERNAL_ID'].",
        `NAME`='".$this->db->escape($data['NAME'])."',
        `STREET`='".$this->db->escape($data['STREET'])."',
        `STREET_NUMBER`='".$this->db->escape($street_number)."',
        `CITY`='".$this->db->escape($city)."',
        `POSTAL_CODE`='".$this->db->escape($data['POSTAL_CODE'])."',
        `GPS_LAT`='".$this->db->escape($data['GPS_LAT'])."',
        `GPS_LONG`='".$this->db->escape($data['GPS_LONG'])."',
        `PACKET_CONSIGNMENT`=".(int)$data['PACKET_CONSIGNMENT'].",
        `IN_OPERATION`=".(int)$data['IN_OPERATION'].",
        `shipping_id`=".(int)$shipping_id.",
        `isklad_id`=".(int)$isklad_id);

        return TRUE;
    }
    /* Isklad Delivery Types - end */

    /* Isklad Supplier - begin */
    public function CreateSupplier(){
        $manufacturers = $this->getManufacturers();
        if(!$manufacturers){
            $this->log("Нет производителей для экспорта");
            return TRUE;
        }

        $this->load->model('localisation/country');
        foreach ($manufacturers as $manufacturer) {
            $this->log("Подготавливаем данные для бренда {$manufacturer['manufacturer_id']}");
            $country_info = $this->model_localisation_country->getCountry($manufacturer['country_id']);
            if(!$country_info || !$country_info['iso_code_2']){
                $this->log("ОШИБКА! Для бренда не заполена страна {$manufacturer['manufacturer_id']}");
                continue;
            }
            $data_export = array(
                'name' => $manufacturer['name'],
                'auto_shipment_load' => 1,
                'country_code' =>  $country_info['iso_code_2'],
                'delivery_days' => $manufacturer['max_shipping_days'],
                'street' => $manufacturer['address'],
                'city' => $manufacturer['city'],
                'email_orders' => $manufacturer['email'],
                'email_info' => $manufacturer['email'],
            );

            $this->log('Сформированы данные для бренда '. print_r($data_export,true));
            $response = $this->createRequest($this->method_create_supplier, $data_export);
            $this->log('Ответ Isklad '. print_r($response,true));
            if(isset($response['response']['SUPPLIER_ID'])){
                $supplier_id = $response['response']['SUPPLIER_ID'];
                $this->db->query("UPDATE " . DB_PREFIX . "manufacturer SET isklad_supplier_id={$supplier_id} WHERE manufacturer_id={$manufacturer['manufacturer_id']}");
            }
        }
    }

    public function getManufacturers(){
        $manufacturers = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer m LEFT JOIN crm_manufacturer_info mi ON (m.manufacturer_id=mi.manufacturer_id) WHERE m.isklad_supplier_id=0");
        if($query->num_rows){
            $manufacturers = $query->rows;
        }
        return $manufacturers;
    }
    /* Isklad Supplier - end */

    /* Isklad Payment Methods - begin */
    public function OrderStatusList(){
        $xml = simplexml_load_file($this->filename_order_status, null, LIBXML_NOCDATA);
        if($xml) {
            $isklad_order_statuses = $this->getIskladOrderStatuses();

            $json_string = json_encode($xml);
            $result_array = json_decode($json_string, TRUE);
            foreach ($result_array['STATUS'] as $payment) {
                $main_data = array(
                    'ID' =>$payment['ID'],
                    'NAME' => $payment['NAME'],
                    'NAME_EN' => $payment['NAME_EN'],
                );

                if(isset($isklad_order_statuses[$payment['ID']])){
                    $this->log("Обновляем данные по статусу заказа с ID {$payment['ID']} ({$payment['NAME_EN']})");
                    $this->updateIskladOrderStatus($main_data);
                }else{
                    $this->log("Добавляем данные по статусу заказа с ID {$payment['ID']} ({$payment['NAME_EN']})");
                    $this->addIskladOrderStatus($main_data);
                }
            }
        }
    }

    public function getIskladOrderStatuses(){
        $isklad_order_statuses = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "isklad_order_statuses ");
        if($query->num_rows){
            foreach ($query->rows as $row){
                $isklad_order_statuses[$row['ID']] = $row;
            }
        }
        return $isklad_order_statuses;
    }

    public function addIskladOrderStatus($data){
        $this->db->query("INSERT INTO " . DB_PREFIX . "isklad_order_statuses SET `ID`=".(int)$data['ID'].", `NAME`='".$this->db->escape($data['NAME'])."', `NAME_EN`='".$this->db->escape($data['NAME_EN'])."'");
        return TRUE;
    }

    public function updateIskladOrderStatus($data){
        $this->db->query("UPDATE " . DB_PREFIX . "isklad_order_statuses SET `NAME`='".$this->db->escape($data['NAME'])."', `NAME_EN`='".$this->db->escape($data['NAME_EN'])."' WHERE `ID`=".(int)$data['ID']);
        return TRUE;
    }
    /* Isklad Payment Methods - end */

    public function createRequest($method, $data)
    {
        if($this->count_request == 179){
            sleep(60);
            $this->count_request = 0;
        }
        $response = $this->connector->createRequest($method, $data)->send()->getResponse();
        $this->count_request ++;
        //var_dump($response);
        return $response;
    }

    public function library($library)
    {
        $file = DIR_SYSTEM . 'library/' . $library . '.php';
        if (file_exists($file)) {
            require_once($file);
        } else {
            trigger_error('Error: Could not load library' . $file . '!');
            exit();
        }
    }

    public function saveImage($dir, $folder, $url, $is_subdir, $id = -1)
    {
        if (!is_dir($dir)) {
            $res = @mkdir($dir);
            if (!$res) {
                $this->log("Ошибка создания каталога " . $dir);
                return false;
            }
        }

        if ($is_subdir == 1)
            $dir = $dir . $folder . '/';

        if (!is_dir($dir)) {
            $res = @mkdir($dir);
            if (!$res) {
                $this->log("Ошибка создания каталога " . $dir);
                return false;
            }
        }

        $info = pathinfo($url);
        $extension = $info['extension'];
        $paramsPos = strpos($extension, "?");
        if ($paramsPos !== false)
            $extension = substr($extension, 0, $paramsPos);

        $file = $folder . "." . $extension;
        if ($id >= 0)
            $file = $folder . "-" . $id . "." . $extension;

        $path_file = $file;
        if ($is_subdir == 1)
            $path_file = $folder . '/' . $file;

        if (is_file($dir . $file)) {
            $this->log("Пропускаем изображение, т.к. в каталоге $dir оно уже сохранено под именем $path_file");

            return $path_file;
        }

        $this->log("Сохраняем изображение $url в каталоге $dir под именем $path_file");

        file_put_contents($dir . $file, $this->getUrl($url));

        return $path_file;
    }

    public function translit($name)
    {
        $rus = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');
        $rusUp = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', ' ');
        $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sh', '', 'i', '', 'e', 'u', 'ya', '-');
        $characters = 'abcdefghijklmnopqrstuvwxyz1234567890-_';

        $res = str_replace($rus, $lat, trim($name));
        $res = str_replace($rusUp, $lat, $res);

        $return = '';

        for ($i = 0; $i < strlen($res); $i++) {
            $c = strtolower(substr($res, $i, 1));
            if (strpos($characters, $c) === false)
                $c = '';
            $return .= $c;
        }

        return $return;
    }

    public function getUrl($url)
    {
        $_proxy = '';

        $url = str_replace("&amp;", "&", $url);
        $result = false;
        for ($i = 0; $i < 5; $i++) {
            // это чтобы не увлечься и не положить донора
            sleep(2);
            // защита от лагов проксей
            try {
                //$this->log( "Запрашивается ссылка " . $url . (($_proxy != "") ? " через прокси " . $_proxy : " без прокси" ) );
                $result = $this->_curl($url, $_proxy);
                break;
            } catch (Exception $e) {
                $this->log("Скачивание ссылки " . $url . " закончилось ошибкой " . $i . " раз - " . $e->getMessage());
                sleep(5); // передышка 5 секунд перед следующей попыткой
            }
        }
        return $result;
    }

    public function _curl($url, $proxy = "", $connect_timeout = 5, $total_timeout = 60)
    {
        if (!$this->isAccesible(__FUNCTION__, true)) {
            return "";
        }

        $user_agent = array();
        $user_agent[] = 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.205 Safari/534.16';
        $user_agent[] = 'Mozilla/5.0 (X11; U; Linux i686 (x86_64); en-US; rv:1.8.1.6) Gecko/2007072300 Iceweasel/2.0.0.6 (Debian-2.0.0.6-0etch1+lenny1)';
        $user_agent[] = 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)';
        $user_agent[] = 'Mozilla/5.0 (X11; U; Linux i686; cs-CZ; rv:1.7.12) Gecko/20050929';
        $user_agent[] = 'Opera/9.80 (Windows NT 5.1; U; ru) Presto/2.9.168 Version/11.51';
        $user_agent[] = 'Mozilla/5.0 (Windows; I; Windows NT 5.1; ru; rv:1.9.2.13) Gecko/20100101 Firefox/4.0';
        $user_agent[] = 'Opera/9.80 (Windows NT 6.1; U; ru) Presto/2.8.131 Version/11.10';
        $user_agent[] = 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.7; U; ru) Presto/2.8.131 Version/11.10';
        $user_agent[] = 'Mozilla/5.0 (Macintosh; I; Intel Mac OS X 10_6_7; ru-ru) AppleWebKit/534.31+ (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent[array_rand($user_agent)]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_COOKIEFILE, DIR_LOGS . 'cookie.txt');
        //curl_setopt($ch, CURLOPT_COOKIEJAR,  DIR_LOGS . 'cookie.txt');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout); // 10 секунд на подключение
        if ($proxy != "") {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout); // 10 секунд на подключение, иначе считаем что прокси сдохла
            curl_setopt($ch, CURLOPT_TIMEOUT, $total_timeout); // 60 секунд на скачивание странички, иначе считаем что прокси сдохла
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        @curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // против защиты от бесконечного "перейдите сюда"
        $html = curl_exec($ch);
        if ($html === false) {
            // таймаут или редиректы
            throw new Exception("Ошибка при скачивании: " . curl_error($ch));
        }
        curl_close($ch);
        return $html;
    }

}
