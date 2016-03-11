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
		//execute installation sql commands
		$this->executeSqlFile('install.sql');
		$this->executeSqlFile('test.sql');
		//install module in needed prestashops's hooks
		if(!parent::install() || !$this->registerHook('header') || !$this->registerHook('productfooter') || !$this->registerHook('footer'))
			return false;//abort if error
		);
		return true;//install success
	}



	//steps to execute when the module is removed
	public function uninstall() {
		//execute uninstall sql commands
		$this->executeSqlFile('uninstall.sql');
                //uninstall module
		return parent::uninstall();
	}

	/*
	* read the content of given file and executes it
	* return true if no error, or else if something went wrong
	*/
	public function executeSqlFile($file) {
		//the file is always in the module's root dir
		$sql_file_path = dirname(__FILE__) . "/$file";
		if (!file_exists($sql_file_path))
			return false;//abort
		//get file content in $sql
		else if (!$sql = file_get_contents($sql_file_path))
			return false;//abort
		//replace dummy PREFIX_ with prestashop's prefix value
		$sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
		//splite sql commands on each ";"
		$sql = preg_split("/;\s*[\r\n]+/", $sql);
		foreach ($sql as $query){
			if($sql != ''){
				if (!Db::getInstance()->Execute(trim($query))) return false;//abort if sql error
			}
		}
		return true;//success
	}

	//add css file to header
	public function hookHeader($params) {
		$this->context->controller->addCSS(($this->_path).'views/css/c3productoptions.css', 'all');
	}
	//add block in footer
	public function hookFooter(){

	}
	//Add block under the product description
	public function hookProductFooter($params){
	
	}
}
