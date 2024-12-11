<?php

require_once( DIR_SYSTEM . "/engine/neoseo_model.php");

class ModelToolIsklad extends NeoSeoModel
{
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "isklad";
        $this->_logFile = $this->_moduleSysName . ".log";
        $this->debug = $this->config->get($this->_moduleSysName() . "_debug") == 1;

    }
    public function updateOrderProductStatus($isklad_order_id, $isklad_order_status_id){
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE isklad_order_id={$isklad_order_id}");
        if($query->num_rows){
            $this->log("Найден заказ на сайте {$query->row['isklad_order_original_id']} для обновления статуса заказа ISKLAD");
            $this->db->query("UPDATE " . DB_PREFIX . "order_product SET `isklad_order_status_id`='".(int) $isklad_order_status_id."' WHERE isklad_order_id={$isklad_order_id}");
            $this->log("Обновляем заказу isklad №{$isklad_order_id} на статус заказ с ID {$isklad_order_status_id}");

            $order_id = $query->row['order_id'];
            $change_order_status_isklad = $this->config->get($this->_moduleSysName.'_change_order_status');
            if($change_order_status_isklad && in_array($isklad_order_status_id, $change_order_status_isklad)){
                $this->log("C ISKLAD пришел статус завершенного заказа. Изменяем общий статус заказа № {$order_id}");

                $query_product = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id={$order_id} AND isklad_order_status_id NOT IN (".implode(',', $change_order_status_isklad).")");
                if($query_product->num_rows){
                    $new_order_status = 284; // Partially dispatched
                }else{
                    $new_order_status = 285; // Dispatched
                }
                $this->load->model('checkout/order');
                $this->model_checkout_order->addOrderHistory($order_id, $new_order_status);
            }
        }else{
            $this->log("Не найден заказ на сайте {$query->row['isklad_order_original_id']} для обновления статуса заказа ISKLAD!");
        }
    }

    public function getIskladOrderStatuses(){
        $isklad_order_statuses = array();
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "isklad_order_statuses ");
        if($query->num_rows){
            foreach ($query->rows as $row){
                $isklad_order_statuses[$row['ID']] = $row['NAME_EN'];
            }
        }
        return $isklad_order_statuses;
    }
}
