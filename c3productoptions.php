<?php

//if major problem with prestashop, abort
if (!defined('_PS_VERSION_'))
    exit;
/*
 * The module aims: add custom product's option logic to shop
 * - each product can have a free combination of options and there values, ex: Option: Size (list), values: [xxs,s,m,l]
 * - there is 4 option types:
 *     text: the customer needs to input some text to order the product, ex: what name to print on the shirt ? <bob>
 *     checkbox: the customer can check the option when he orders the product, ex: extend the guarantee 5 years for 19.99 yes/no ?
 *     radio: the customer needs to choose one of the value to order the product, ex: in what color do you want the shirt (this option is kept for legacy purpose, all new product should use a list instead)
 *     list: the customer needs to choose one option to order the product, ex: Size values: [xxs,s,m,l]
 * - list and radio are always required, checkbox may be required, text is optional
*/


require_once(dirname(__FILE__).'/c3module.php');


//declare the module
class C3ProductOptions extends Module
{

	function __construct()
	{
		//declare the module's infos
		$this->name = 'c3productoptions';
		$this->tab = 'front_office_features';
		$this->version = '1.0.5';
		$this->author = 'Schnepp David';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('C3ProductOptions block');
		$this->description = $this->l("Adds C3's custom product option logic v2.");
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	//steps to execute when the module is installed
	function install()
	{
		//execute installation sql commands
		if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'install'))
			return false;
		// create custom cache
		if (!NsC3ProductOptions\C3Module::createModuleCacheDir($this->name))
			return false;
		//install module in needed prestashops's hooks
		if (
			!parent::install() ||
			!$this->registerHook('header') || //ajax data
			!$this->registerHook('footer') || //data processing logic
			//replace product option
			!Configuration::updateValue('C3PRODUCTOPTIONS2_RESET', false)
		)
			return false;//abort if error
		return true;//install success
	}



	//steps to execute when the module is removed
	public function uninstall()
	{
		// delete custom cache dir
		if (!NsC3ProductOptions\C3Module::removeModuleCacheDir($this->name))
			return false;
		//execute uninstall sql commands
		/*if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'uninstall'))
			return false;*/
		//uninstall module
		return parent::uninstall();
	}

	// add css/js file to header
	public function hookHeader($params)
	{
		if (!$this->active)
			return;
		$productOptionFilePath = $this->getProductOptionJsonFile();
		if ($productOptionFilePath != 'missing'){
			$this->context->controller->addCSS(($this->_path).'views/css/c3productoptions2.css', 'all');
			$this->context->controller->addJS($productOptionFilePath);
		}
	}

	// add css/js file to header
	public function hookFooter($params)
	{
		if (!$this->active)
			return;
		$productOptionFilePath = $this->getProductOptionJsonFile();
		if ($productOptionFilePath != 'missing')
			$this->context->controller->addJS(($this->_path).'views/js/c3productoptioncomputation.js');
	}

	// get current product's options json file
	//return the path (if exists) or missing (if not generated)
	private function getProductOptionJsonFile($id_product = 0)
	{
		if($id_product == 0)
			$id_product = (int)(Tools::getValue('id_product'));
		if ($id_product > 0){
			if(Product::c3GetProductManagedByC3Module($id_product)) {
				$moduleCachePath = NsC3ProductOptions\C3Module::getModuleCachePath($this->name);
				$filePathFormat = '%s/%s.js';
				$productOptionFilePath = sprintf($filePathFormat, $moduleCachePath, $id_product);
				if (file_exists($productOptionFilePath)) {
					return $productOptionFilePath;
				}
			}
		}
		return 'missing';
	}

	//backend form checks
	public function getContent()
	{
		$output = '';
		$errors = array();
		if (Tools::isSubmit('submitC3ProductOptions2')) {
			//check if C3PRODUCTOPTIONS_RESET was provided
			$c3po_reset = Tools::getValue('C3PRODUCTOPTIONS2_RESET');
			if (!strlen($c3po_reset))
				$errors[] = $this->l('Please complete the data reset field.');
			elseif (!Validate::isBool($c3po_reset))
				$errors[] = $this->l('Invalid value for data reset. It has to be a boolean.');
			//if errors, display error messages
			if (count($errors))
				$output = $this->displayError(implode('<br />', $errors));
			else {
				//update module values
				Configuration::updateValue('C3PRODUCTOPTIONS2_RESET', (bool)$c3po_reset);

				NsC3ProductOptions\C3Module::removeModuleCacheFiles($this->name);

				$this->renewProductOptionJsonFile('with', $errors);//renew product with options
				$this->renewProductOptionJsonFile('without', $errors);//renew product without options

				$output = $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->renderForm();
	}

	private function renewProductOptionJsonFile($type_products, &$errors)
	{
		$sql = 'SELECT id_product, price FROM `'._DB_PREFIX_.'vc3_product_'.$type_products.'_option_json_data`';
		$products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql); //should add groups
		if (!count($products))
			$errors[] = $this->l('No product with options');
		else{
			for($p = 0; $p < count($products); $p++){
				$id_product = (int)$products[$p]['id_product'];
				$product_base_price = (float)$products[$p]['price'];

				$product_data = [];
				$product_data['id_product'] = $id_product;
				$product_data['product_base_price'] = $product_base_price;
				$product_data['data'] = [];

				if($type_products == 'with'){
					$id_lang = (int)$this->context->language->id;
					$sql = 'SELECT id_attribute_group, required_option, group_type, public_name FROM `'._DB_PREFIX_.'vc3_product_option` WHERE id_lang = '.$id_lang.' AND id_product = '.$id_product;

					$options = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql); //should add groups
					if (!count($options))
						$errors[] = $this->l('No option for product '.$id_product);
					else
						$this->getProductOptionValueData($options, $id_lang, $id_product, $product_data);
				}
				$this->saveProductOptionJsonFile($product_data, $id_product);
			}
		}
	}


	private function saveProductOptionJsonFile(&$product_data, &$id_product)
	{
		//sav new product option data
		$js = 'var c3_product_options = '.json_encode($product_data).';';
		$productOptionFile = sprintf('%s.js', $id_product);
		NsC3ProductOptions\C3Module::writeStringToModuleCache($this->name, $productOptionFile, $js);
	}

	private function getProductOptionValueData(&$options, &$id_lang, &$id_product, &$product_data)
	{
		//get each option data
		for($i = 0; $i < count($options); $i++){
			$id_attribute_group = (int)$options[$i]['id_attribute_group'];
			$required_option = (bool)$options[$i]['required_option'];
			$group_type = (string)$options[$i]['group_type'];
			$public_name = (string)$options[$i]['public_name'];

			$attribute = [];
			$attribute['id_attribute_group'] = $id_attribute_group;
			$attribute['required_option'] = $required_option;
			$attribute['group_type'] = $group_type;
			$attribute['lbl_attribute_group'] = $public_name;
			$attribute['data'] = [];

			$sql = 'SELECT id_attribute, price, name FROM `'._DB_PREFIX_.'vc3_product_option_value` WHERE id_lang = '.$id_lang.' AND id_product = '.$id_product.' AND id_attribute_group = '.$id_attribute_group;
			$option_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
			for($j = 0; $j < count($option_values); $j++){
				$attribute_value = [];
				$attribute_value['id_attribute'] = (int)$option_values[$j]['id_attribute'];
				$attribute_value['price_attribute'] = (float)$option_values[$j]['price'];
				$attribute_value['lbl_attribute'] = (string)$option_values[$j]['name'];
				array_push($attribute['data'], $attribute_value);
			}
			if(count($attribute['data']) > 0)
				array_push($product_data['data'], $attribute);
		}
	}

	//backend form creation
	public function renderForm()
	{
		//setup form fields
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'switch',
						'label' => $this->l('Reset data'),
						'name' => 'C3PRODUCTOPTIONS2_RESET',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('If enabled, reset caches.'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Enabled')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('Disabled')
							)
						)
					)
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);
		//setup form infos
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitC3ProductOptions2';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&c3productoptions2_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);
		//generate form
		return $helper->generateForm(array($fields_form));
	}
	//output config fields to array
	public function getConfigFieldsValues()
	{
		return array(
			'C3PRODUCTOPTIONS2_RESET' => Tools::getValue('C3PRODUCTOPTIONS2_RESET', (bool)Configuration::get('C3PRODUCTOPTIONS2_RESET')),
			//'C3PRODUCTOPTIONS2_LASTREGEN' => Tools::getValue('C3PRODUCTOPTIONS2_LASTREGEN', (string)Configuration::get('C3PRODUCTOPTIONS2_LASTREGEN')),
		);
	}

	public function displayAjaxC3UpdateList(){
	return "hello";	
	}

}
