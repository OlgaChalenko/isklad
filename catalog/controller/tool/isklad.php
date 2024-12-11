<?php

require_once( DIR_SYSTEM . '/engine/neoseo_controller.php');
require_once( DIR_SYSTEM . '/engine/neoseo_view.php' );

class ControllerToolIsklad extends NeoSeoController
{

    private $error = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->_moduleSysName = "isklad";
        $this->_modulePostfix = ""; // Постфикс для разных типов модуля, поэтому переходим на испольлзование $this->_moduleSysName()
        $this->_logFile = $this->_moduleSysName() . ".log";
        $this->debug = $this->config->get($this->_moduleSysName() . "_debug") == 1;
    }

    public function updateOrder(){
        $this->log("Запрос обновления заказа от ISKLAD ".print_r(htmlspecialchars_decode($this->request->post),true));

        if(isset($this->request->post['req'])){
            $req = htmlspecialchars_decode($this->request->post['req']);
            $req = json_decode($req, true);
            $request = $req['request'];
            $req_data = $request['req_data'];
            $isklad_order_id = $req_data['order_id'];
            $isklad_order_status_id = $req_data['status_id'];

           $this->load->model('tool/'.$this->_moduleSysName);
           $this->{"model_tool_".$this->_moduleSysName}->updateOrderProductStatus($isklad_order_id, $isklad_order_status_id);
           $this->log("C ISKLAD пришел статус с id {$isklad_order_status_id} для заказа id_isklad {$isklad_order_id}");

           $response = array(
               'auth_status' => 1,
               'response' => array(
                    'resp_status' =>  1,
               ),
           );
           echo json_encode($response);
        }
    }


}
