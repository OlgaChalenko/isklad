<?php

require_once( DIR_SYSTEM . "/engine/neoseo_model.php");

class ModelModuleIsklad extends NeoSeoModel
{

	public function __construct($registry)
	{
		parent::__construct($registry);
		$this->_moduleSysName = 'isklad';
		$this->_modulePostfix = ""; // Постфикс для разных типов модуля, поэтому переходим на испольлзование $this->_moduleSysName()()
		$this->_logFile = $this->_moduleSysName() . '.log';
		$this->debug = $this->config->get($this->_moduleSysName() . '_debug') == 1;

		$this->params = array(
			'status' => 1,
			'debug' => 0,
			'auth_id' => '',
			'auth_key' => '',
			'auth_token' => '',
			'shop_id' => '',
            'business_relationship' => 'b2c',
            'relation_payment_methods' => array(),
            'relation_order_statuses' => array(),
            'invoice_statuses' => array(),
            'countries_need_state' => array(),
            'change_order_status' => array(),
		);
	}

	public function install()
	{
		// Значения параметров по умолчанию
		$this->initParams($this->params);

		// Создаем новые и недостающие таблицы в актуальной структуре
		$this->installTables();

		return TRUE;
	}

	public function installTables()
	{
		return TRUE;
	}

	public function upgrade()
	{

		// Добавляем недостающие новые параметры
		$this->initParams($this->params);

		// Создаем недостающие таблицы в актуальной структуре
		$this->installTables();

		return TRUE;
	}

	public function uninstall()
	{
		return TRUE;
	}

}
