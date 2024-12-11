<?php

require_once( DIR_SYSTEM . '/engine/neoseo_controller.php');
require_once( DIR_SYSTEM . '/engine/neoseo_view.php' );

class ControllerModuleIsklad extends NeoSeoController
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

	public function index()
	{
		$this->upgrade();

		$data = $this->language->load($this->_route . '/' . $this->_moduleSysName());

		$this->document->setTitle($this->language->get('heading_title_raw'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {

			$this->model_setting_setting->editSetting($this->_moduleSysName(), $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			if ($this->request->post['action'] == "save") {
				$this->response->redirect($this->url->link($this->_route . '/' . $this->_moduleSysName(), 'token=' . $this->session->data['token'], 'SSL'));
			} else {
				$this->response->redirect($this->url->link('extension/' . $this->_route, 'token=' . $this->session->data['token'], 'SSL'));
			}
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$data = $this->initBreadcrumbs(array(
			array('extension/' . $this->_route, 'text_module'),
			array($this->_route . '/' . $this->_moduleSysName(), "heading_title_raw")
				), $data);


		$data = $this->initButtons($data);

		$this->load->model($this->_route . "/" . $this->_moduleSysName());
		$data = $this->initParamsListEx($this->{"model_" . $this->_route . "_" . $this->_moduleSysName()}->params, $data);
        $this->load->model("tool/" . $this->_moduleSysName());
        $data['delivery_types'] = array();
        $delivery_types = $this->{"model_tool_" . $this->_moduleSysName()}->getIskladDeliveryTypes();
        if($delivery_types){
            foreach ($delivery_types as $type){
                $type['IMAGE'] = HTTPS_CATALOG.'image/'.$type['IMAGE'];
                $data['delivery_types'][] = $type;
            }
        }

        $data['isklad_payment_methods'] = $this->{"model_tool_" . $this->_moduleSysName()}->getIskladPaymentMethods();

        $data['isklad_order_statuses'] = $this->{"model_tool_" . $this->_moduleSysName()}->getIskladOrderStatuses();
        $data['isklad_order_statuses_arr'] = array();
        if($data['isklad_order_statuses']){
            foreach ($data['isklad_order_statuses'] as $isklad_order_status){
                $data['isklad_order_statuses_arr'][$isklad_order_status['ID']] = $isklad_order_status['NAME_EN'];
            }
        }

            $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['arr_order_statuses'] = array();
        foreach ($data['order_statuses'] as $order_status) {
            $data['arr_order_statuses'][$order_status['order_status_id']] = $order_status['name'];
        }
        $this->load->model('localisation/country');
        $data['countries'] = array();
        $countries = $this->model_localisation_country->getCountries();
        if($countries){
            foreach ($countries as $country) {
                $data['countries'][$country['country_id']] = $country['name'];
            }
        }

        $this->load->model('extension/extension');
        $this->load->model('tool/neoseo_paymentplus');
        $data['payment_methods'] = array();
        $payment_methods = $this->model_extension_extension->getInstalled('payment');
        if($payment_methods){
            foreach ($payment_methods as $code){
                if($code == 'neoseo_paymentplus'){
                    $paymentplus = $this->model_tool_neoseo_paymentplus->getPayments();
                    if($paymentplus){
                        foreach ($paymentplus as $item){
                            $code_method = 'neoseo_paymentplus.neoseo_paymentplus'.$item['payment_id'];
                            $data['payment_methods'][] = array(
                                'code' => $code_method,
                                'name' => $item['name'],
                                'status' => $item['status'],
                            );
                        }
                    }
                }else {
                    $data_language = $this->load->language('payment/' . $code);
                    $status =$this->config->get($code.'_status');
                    $data['payment_methods'][] = array(
                        'code' => $code,
                        'name' => strip_tags($data_language['heading_title']),
                        'status' => $status,
                    );
                }
            }
        }

        $data['type_business_relationship'] = array(
            'b2b' => 'b2b',
            'b2c' => 'b2c',
        );

		$data["token"] = $this->session->data['token'];
		$data['config_language_id'] = $this->config->get('config_language_id');
		$data['params'] = $data;

		$data["logs"] = $this->getLogs();

		$widgets = new NeoSeoWidgets($this->_moduleSysName() . '_', $data);
		$widgets->text_select_all = $this->language->get('text_select_all');
		$widgets->text_unselect_all = $this->language->get('text_unselect_all');
		$data['widgets'] = $widgets;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->_route . '/' . $this->_moduleSysName() . '.tpl', $data));
	}

	private function validate()
	{
		if (!$this->user->hasPermission('modify', $this->_route . '/' . $this->_moduleSysName())) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

}
