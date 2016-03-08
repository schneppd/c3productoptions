<?php

//if major problem with prestashop, abort
if (!defined('_PS_VERSION_'))
	exit;
/*
 * The module aims: add custom product's option logic to shop
 * - each product can have a free combination of options and there values, ex: Option: Size (list), values: [xxs,s,m,l]
 * - there is 4 option types:
 * 	text: the customer needs to input some text to order the product, ex: what name to print on the shirt [bob]
 * 	checkbox: the customer can check the option when he orders the product, ex: extend the guarantee 5 years
 * 	radio: the customer needs to choose one of the value to order the product, ex: in what color do you whant the shirt (this option is kept for legacy purpose, all new product should use a list instead)
 * 	list: the customer needs to choose one option to order the product, ex: Size values: [xxs,s,m,l]
 * - each option's value can influence the product final price
 * - when displayed in shelf, the product will show the lowest price combination
 * - each option may or may not be required
*/


//declare the module
class C3ProductOptions extends Module
{

	function __construct() {
		//declare the module's infos
		$this->name = 'c3productoptions';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'Schnepp David';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('C3ProductOptions block');
		$this->description = $this->l("Adds C3's custom product option logic.");
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	//steps to execute when the module is installed
	function install() {
		//install module
		$success = (parent::install()
		);
		return $success;
	}

	//steps to execute when the module is removed
	public function uninstall() {

                //uninstall module
		return parent::uninstall();
	}



}
