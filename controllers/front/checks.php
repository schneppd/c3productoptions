<?php

class C3ProductOptionsChecksModuleFrontController extends ModuleFrontController {
	//public $php_self = 'c3productoptions2';
	//must rerord module front controller as c3productoptioncheck
	public function __construct() {
		parent::__construct();
		if (Tools::getValue('ajax')) {
			$this->ajax = true;
		}
		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
			$this->context = Context::getContext();
		else
			$this->context = (object) null;
	}
	public function init() {
		parent::init();
	}
	public function postProcess() {
		if(!$this->ajax)
			die;
		switch (Tools::getValue('action')) {
			case 'c3checkselectionavailability':
				die($this->getSelectedProductOptionAvailability());
				break;
			default:
				exit;
		}
	}

	public function getSelectedProductOptionAvailability() {
		$quantity = Tools::getValue('quantity');
		$id_product = Tools::getValue('id_product');
		$combination_data = Tools::getValue('combination_data');

		$res['available'] = 0;

		if(!is_numeric($quantity) || !is_numeric($id_product))
			$this->reportUserHackTry('bad data given');
		
		$quantity = (int)$quantity;
		$id_product = (int)$id_product;
		$combination_data = (string)$combination_data;

		if($id_product > 0){
			$id_shop = $this->context->shop->id;
			$sql = new DbQuery();
			$sql->select('out_of_stock, quantity');
			$sql->from('stock_available');
			$sql->where('id_product = '.$id_product);
			$sql->where('id_shop = '.$id_shop);
			$stocks = Db::getInstance()->getRow($sql);
			$out_of_stock = (int)$stocks['out_of_stock'];
			if($out_of_stock == 2){//available
				$product_quantity = (int)$stocks['quantity'];
				if($quantity <= $product_quantity)//can fulfill the order
					$res['available'] = 1;
			}
		}

		/*if($id_combination > 0 && $combination_data == ""){//simple product
			$res['msg'] = 'normal product';

			$sql = 'SELECT quantity FROM `'._DB_PREFIX_.'c3_product_option_combination_stock_available` 
				 WHERE `id_product` = '.$id_product.' AND id_combination = '.$id_combination;
			$stocks = Db::getInstance()->executeS($sql);

			foreach ($stocks as $row){
				$q = (int)$row['quantity'];
				if($q >= $quantity)//selection can be shipped
					$res['available'] = 1;
				else if($q > 0)//tell costumer about remaining
					$res['available'] = -1;
			}
		}
		else{//product with options
			//test if combination exists and available
			//test each part of combination
			$res['msg'] = 'product with options';
			$optionsRawData = explode('_', $combination_data);

			if(count($optionsRawData) == 0 || $optionsRawData == false)
				$this->reportUserHackTry('bad combination data');
			//process data and get attributs
			$options = [];
			foreach ($optionsRawData as $option){
				$optionData = explode('-', $option);

				if(count($optionData) != 2 || $optionData == false)
					$this->reportUserHackTry('bad option_value text data');
				
				if(!is_numeric($optionData[0]) || !is_numeric($optionData[1]))
					$this->reportUserHackTry('bad option_value ids data');

				$id_attribute_group = (int)$optionData[0];
				$id_attribute = (int)$optionData[1];

				if($id_attribute_group == 0 || $id_attribute == 0)
					$this->reportUserHackTry('bad option_value id to int data');
				array_push($options, [$id_attribute_group, $id_attribute]);
			}
			$select = 'SELECT 1';
			$from = ' FROM';
			$where = ' WHERE';
			foreach ($options as $key=>$option){
				if($key == 0){
					$from .= ' `'._DB_PREFIX_.'c3_product_option_value` AS ov'.$key;
					$where .= ' ov'.$key.'.id_attribute_group = '.$option[0];
					$where .= ' AND ov'.$key.'.id_attribute = '.$option[1];
				}
				else{
					$from .= ' INNER JOIN `'._DB_PREFIX_.'c3_product_option_value` AS ov'.$key.' ON(ov0.id_product = ov'.$key.'.id_product)';
					$where .= ' AND ov'.$key.'.id_attribute_group = '.$option[0];
					$where .= ' AND ov'.$key.'.id_attribute = '.$option[1];
				}
			}
			$combinationPossibility = Db::getInstance()->executeS($select + $from + $where);
			if(count($combinationPossibility) == 0){
				//impossible part of combination not available
				$this->reportUserHackTry('user sent combination with art unavailable');
				$res['available'] = 0;
			}
			else{
				//combination available, try see if combination exists
				$nbOptions = count($options);

				$select = 'SELECT poc.id_combination, poc.available_combination';
				$from = ' FROM `'._DB_PREFIX_.'c3_product_option_combination` AS poc';
				$where = ' WHERE poc.id_product = '.$id_product.' AND poc.nb_option = '.$nbOptions;
				
				foreach ($options as $key=>$option){
					$from .= ' INNER JOIN `'._DB_PREFIX_.'c3_product_option_combination_part` AS pocp'.$key.' ON(pocp'.$key.'.id_combination = poc.id_combination)';
					$where .= ' pocp'.$key.'.id_attribute = '.$option[1];
				}
				$combinationTry = Db::getInstance()->executeS($select + $from + $where);
				if(count($combinationTry) == 0){//combination to create
				}
				else{//combination already esxists
					$id_combination = (int)$combinationTry[0]['id_combination'];
					$available_combination = (bool)$combinationTry[0]['available_combination'];
				}
					
			}
		}*/


		return Tools::jsonEncode($res);
	}
	public function reportUserHackTry($details) {
		//log user hack
	}

}
