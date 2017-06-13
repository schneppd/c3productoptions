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
	private $module_import_folder;
	private $module_export_folder;
	private $module_database_import_path_start;
	
	function __construct()
	{
		//declare the module's infos
		$this->name = 'c3productoptions';
		$this->tab = 'front_office_features';
		$this->version = '1.3.7';
		$this->author = 'Schnepp David';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('C3ProductOptions block');
		$this->description = $this->l("Adds C3's custom product option logic v2.");
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		
		$this->module_import_folder = realpath(dirname(__FILE__)). '/import';
		$this->module_export_folder = realpath(dirname(__FILE__)). '/export';
		$this->module_database_import_path_start = realpath(dirname(__FILE__)). '/import/bundle/c3ProductOptions_';
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
			!parent::install()
			|| !$this->registerHook('header') //ajax data
			|| !$this->registerHook('footer') //data processing logic
			|| !$this->registerHook('backOfficeHeader')
			//replace product option
			|| !Configuration::updateValue('C3PRODUCTOPTIONS2_RESET', false)
			|| !Configuration::updateValue('C3PRODUCTOPTIONS_ACTION', 0)
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
		if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'uninstall'))
			return false;
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
		if ($productOptionFilePath != 'missing'){
			$js_script = ($this->_path).'views/js/c3productoptioncomputation.js';
			$this->context->controller->addJS($js_script);
		}
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
	
	public function hookBackOfficeHeader()//note the case of hook name
	{
		$this->context->controller->addJS(($this->_path).'views/js/admin/c3productoptionbackend.js');
	}

	//backend form checks
	public function getContent()
	{

		$output = '';
		$errors = array();
		if (Tools::isSubmit('submitC3ProductOptions2')) {
			//check if C3PRODUCTOPTIONS_RESET was provided
			//if errors, display error messages
			if (count($errors))
				$output = $this->displayError(implode('<br />', $errors));
			else {
					$id_action = (int) Tools::getValue('C3PRODUCTOPTIONS_ACTION');
					$import_folder = realpath(dirname(__FILE__)). '/import';
					$export_folder = realpath(dirname(__FILE__)). '/export';
					// prevent script from leaving early
					set_time_limit (0);
					
					if($id_action == 1) {
						//update module values
						//Configuration::updateValue('C3PRODUCTOPTIONS2_RESET', (bool)$c3po_reset);

						NsC3ProductOptions\C3Module::removeModuleCacheFiles($this->name);

						$this->renewProductOptionJsonFile('with', $errors);//renew product with options
						// $this->renewProductOptionJsonFile('without', $errors);//renew product without options

						$output = $this->displayConfirmation($this->l('Settings updated'));
					}
					else if ($id_action == 2) {
						//unzip data
						$file_path = realpath(dirname(__FILE__));
						$work_dir = $import_folder . '/import';
						$zip_file = $work_dir . '/bundle.7z';
						
						$unzip_dir = $work_dir . '/bundle';
						mkdir($unzip_dir, 0755);
						
						$cmd = "7za x $zip_file -o$work_dir/";
						exec($cmd); 
						//exec($cmd . " > /dev/null &"); 
						//7za x $file -o/srv/vault/schneppd/really-free/dev/prestashop/import/db/
						$output = $this->displayConfirmation($this->l("$zip_file extracted!"));
					}

					else if ($id_action == 3) {
						// recompress data
						$file_path = realpath(dirname(__FILE__));
						$source = $import_folder . '/bundle';
						$destination = $export_folder . '/bundle.7z';
						
						$cmd = '7za a -r -t7z -m0=lzma -mx=9 -mfb=64 -md=32m -ms=on '.$destination.' '.$source;
						exec($cmd);
						
						$output = $this->displayConfirmation($this->l("$destination created!"));
					}
					else if ($id_action == 4) {
						//test prestashop functionalities
						$infos = 'test functionalities';
						$res = "";
						//$res = $this->registerHook('backOfficeHeader');
						//Configuration::updateValue('C3PRODUCTOPTIONS_ACTION', 0);
						//delete existing product

						/*
						$data = new Tag(null, 'test tag create', 5);
						$data->name = 'test tag create';
						$data->id_lang = 5;
						$data->add();
						*/
						/*
						$data = new Tag(968, 'test tag update', 5);
						$data->name = 'test tag update';
						$data->id_lang = 5;
						$data->update();
						*/
						/*
						$data = new Tag(968);
						$data->delete();
						*/
						/*
						$data = new Product(56436, false, 5);
						$data->delete();
						$infos = 'product deleted';
						*/
						/*
						$data = new Manufacturer(null, 5);
						$data->name = 'manufacturer create';
						$data->active = true;
						$data->add();
						*/
						/*
						$data = new Manufacturer(420, 5);
						$data->name = 'manufacturer update';
						$data->update();
						*/
						/*
						$data = new Manufacturer(420, 5);
						$data->delete();
						*/
						/*
						$data = new Supplier(null, 5);
						$data->name = 'supplier create';
						$data->add();
						*/
						/*
						$data = new Supplier(19, 5);
						$data->name = 'supplier update';
						$data->update();
						*/
						/*
						$data = new Supplier(19, 5);
						$data->delete();
						*/
						//create
						/*
						$data = new Category(null, 5, 1);
						$data->name = 'Category create';
						$data->active = true;
						//$url = Link::getCategoryLink($data);
						$url = Tools::str2url($data->name);
						$data->link_rewrite = $url;//Link::getQuickLink('Category create');
						$data->id_parent = 2;
						$data->add();
						*/
						//update
						/*
						$data = new Category(561, 5, 1);
						$data->name = 'Category create update';
						$data->update();
						*/
						//delete
						/*
						$data = new Category(561, 5, 1);
						$data->delete();
						$data = new Category(562, 5, 1);
						$data->delete();
						$data = new Category(563, 5, 1);
						$data->delete();
						*/
						//creation
						/*
						$dt = new Feature(null, 5, 1);
						$dt->name = "Test féàture creat1 update";
						$dt->add();
						$res .= $dt->id;
						/*
						Feature::addFeatureImport("Test féàture creat1");
						Feature::addFeatureImport("Test féàture creation2");
						Feature::addFeatureImport("Test féàture creation3");
						*/
						//update
						/*
						$data = new Feature(160, 5, 1);
						$data->name = "Test féàture creat1 update";
						$data->update();
						*/
						//delete
						/*
						$data = new Feature(160, 5, 1);
						$data->delete();
						*/
						//creation
						/*
						$data = new FeatureValue(null, 5, 1);
						$data->id_feature = 161;
						$data->value = "Test féàtureValue creat1 update";
						$data->add();
						*/
						//update
						/*
						$data = new FeatureValue(3676, 5, 1);
						//$data->id_feature = 161;
						$data->value = "Test féàtureValue creat1 update36";
						$data->update();
						*/
						//delete
						/*
						$data = new FeatureValue(3676, 5, 1);
						$data->delete();
						*/
						//creation
						
						/*
						$id_lang = 5;
						$data = new AttributeGroup(null, $id_lang, 1);
						$name_data = "Test AtributeGroup creaéèáît1 <>;=#{}";
						$data->name = $this->ConformToPrefestashopAttribute($name_data);
						$data->group_type = "checkbox";
						$data->public_name = "Test AtributeGroup creat123";
						$data->add();
						$id_attribute_group = $data->id;
						
						$dbp = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_,  array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
						
						
						$sql = 'UPDATE `'._DB_PREFIX_.'attribute_group_lang` SET name = :name, public_name = :name WHERE id_attribute_group = :id_attribute_group AND id_lang = :id_lang';
						$stmt = $dbp->prepare($sql);
						$stmt->bindValue(':name', $name_data, SQLITE3_INTEGER);
						$stmt->bindValue(':id_attribute_group', $id_attribute_group, SQLITE3_INTEGER);
						$stmt->bindValue(':id_lang', $id_lang, SQLITE3_INTEGER);
						$result = $stmt->execute();
						*/
						
						/*
						Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'attribute_group_lang` SET name=\''.pSQL($name_data).'\', public_name = \''.pSQL($name_data).'\' WHERE id_attribute_group = '.$id_attribute_group.' AND id_lang = '.$id_lang);
						*/
						
						$res .= $id_attribute_group;
						
						//update
						/*
						$data = new AttributeGroup(197, 5, 1);
						$data->name = "Test AtributeGroup creat1-23";
						$data->update();
						*/
						//delete
						//create
						/*
						$data = new Attribute(null, 5, 1);
						$data->name = "Test Atribute creat1";
						$data->id_attribute_group = 197;
						$data->add();
						*/
						/*
						$data = new Attribute(null, 5, 1);
						$data->name = "Test Atribute creat1";
						$data->id_attribute_group = 196;
						$data->add();
						*/
						
						//update
						/*
						$data = new Attribute(40379, 5, 1);
						$data->name = "Test Atribute creat1 update";
						$data->update();
						*/
						//create product
						/*
						$data = new Product(null, false, 5, 1);
						$data->id_manufacturer = 279;
						$data->id_supplier = 11;
						$data->id_category_default = 472;
						$data->id_shop_default = 1;
						$data->name = "test product é mais";
						$data->description = "<p>bla bla</p>";
						$data->description_short = "<p>bla bla 2</p>";
						$data->quantity = 50;
						$data->available_now = "0000-00-00";
						$data->price = 123.50;
						$data->on_sale = true;
						$data->reference = 'cvbn-mjk';
						$data->supplier_reference = '4f1f25-fff';
						$data->weight = '10.2';
						$data->link_rewrite = Tools::str2url($data->name);
						$data->meta_description = "test description";
						$data->meta_keywords = "test,description";
						$data->meta_title = "test description title";
						$data->new = true;
						$data->active = true;
						$data->visibility = 'both';
						$data->date_add = '2017-01-23';
						$data->date_upd = '2017-01-23';
						
						$data->add();
						*/
						/*
						$data = new Product(56430, false, 5, 1);
						
						$data->id_tax_rules_group = 53;
						$data->quantity = 150;
						$data->name = "test product é mais update";
						$data->available_now = "product available now";
						$data->update();
						$data->addToCategories([3, 4, 18, 11]);
						
						Product::addFeatureProductImport(56430, 154, 3614);
						Product::addFeatureProductImport(56430, 154, 3615);
						
						//$data->id_supplier = 11;
						$data->addSupplierReference(11, null, 'ddddd');
						$data->update();
						*/
						/*
						$data = new StockAvailable(null, 5, 1);
						$data->id_product = 56430;
						$data->id_product_attribute = 0;
						$data->id_shop = 1;
						$data->quantity = 150;
						$data->add();
						*/
						
						/*
						$data = new Image(null, 5);
						$data->id_product = 56430;
						//$data->cover = true;
						//$data->position = Image::getHighestPosition(56430) + 1;
						$data->legend = "test legend";
						$data->add();
						$file_path = realpath(dirname(__FILE__));
						//$source = $file_path . '/import/products/bundle/img/0a1a583acc82cd9b3a8a715000cfbdc5287dca0b2cc30cc35ff5c214afd48cad8e684bc2896e37f535a23e9eef216b52b3d13b8fec8e39a079e788d7fda83353';
						$source = $file_path . '/import/products/bundle/img/0d9ab6adbd1ad18caa36b067389eec2727a5368f581401984694818b6d0bf25a796680ff412dea57a63bdd4a182985c5bcc9869ea964259fb738b259c4984ff9.old';
						$res .= ' id_image'.$data->id;
						$this->copyImg(56430, $data->id, $source);
						*/
						
						$i = 0;
						while ($i < 1000) 
						{
							$i++;
						}
						
						/*
						$data = new Image(86918, 5);
						$data->legend = "test legend update";
						$data->update();
						*/
						/*
						//update stocks
						StockAvailable::setQuantity(56430, 0, 500, 1);
						*/
						/*
						$data = new SpecificPrice(null, 5, 1);
						$data->id_product = 56430;
						$data->id_specific_price_rule = 2;
						$data->id_shop = 1;
						$data->price = -1.0;
						$data->from_quantity = 1;
						$data->reduction = 0.03;
						$data->reduction_type = 'percentage';
						$data->from = date('Y-m-d H:i:s', strtotime("2017-01-25"));
						$data->to = date('Y-m-d H:i:s', strtotime("2017-01-29"));
						$data->id_currency = 0;
						$data->id_country = 0;
						$data->id_group = 0;
						$data->id_customer = 0;
						$data->id_product_attribute = 0;
						$data->add();
						*/
						
						//3, 4, 18, 11
						/**/
						/*
						// delete product
						$data = new Product(56435, false, 5);
						$data->delete();
						$infos = 'product deleted';
						*/
						
						/*
						$data = new Product(56435, false, 5);
						$data->active = false;
						$data->update();
						StockAvailable::setQuantity($data->id, null, 0);
						$infos = 'product updated';
						*/
						/*
						$data = new Product(2, false, 5);
						$data->delete();
						*/
						/*
						$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
						$query = 'SELECT id_product FROM `'._DB_PREFIX_.'specific_price_priority` WHERE id_product=:id_product';
						$stmt = $dbPrestashop->prepare($query);
						$id_product = 53;
						$stmt->execute(array(':id_product' => $id_product));
						foreach ($stmt->fetchAll() as $to_create) {
							$id_product_new = $to_create['id_product'];
							$res .= " id found $id_product_new";
						}
						*/
						
						$output = $this->displayConfirmation($this->l("$infos read!".$res));
					}
					else if ($id_action > 4 && $id_action < 16) {
						// creation action
						$database_path = $import_folder . '/bundle/c3ProductOptions_';
						
						
						return $this->ApplyCreationData($database_path, $id_action);
					}
					else if ($id_action == 16) {
						$database_path = $import_folder . '/bundle/c3ProductOptions_';
						return $this->GetProductWithDefects($database_path);
					}
					else if ($id_action == 17) {
						$database_path = $import_folder . '/bundle/c3ProductOptions_';
						return $this->CancelProductOptionsImport($database_path);
					}
					else if ($id_action > 17 && $id_action < 21) {
						// update action
						return $this->ApplyUpdateData($id_action);
					}
					else if ($id_action > 20 && $id_action < 25) {
						// delete action
						return $this->ApplyDeleteData($id_action);
					}
			}
		}
		return $output.$this->renderForm();
	}
	
	protected function ConformToPrefestashopAttribute($str) {
		$forbidden = array('<', '>', ';', '=', '#', '{', '}');
		$result = str_replace($forbidden, "", $str);
		return $result;
	}
	
	protected function CancelProductOptionsImport($db_path_start)
	{
		$database_path = $db_path_start . 'ProductOptionToCreate.db';
		
		$dbModule = new PDO('sqlite:'.$database_path);
		$dbModule->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$dbModule->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		
		$query = 'SELECT id_product_prestashop, id_option_prestashop, id_option_value_prestashop FROM vProductOptionValueToCreate';
		
		foreach ($dbModule->query($query) as $row){
			$id_product_prestashop = (int)$row['id_product_prestashop'];
			$id_option_prestashop = (int)$row['id_option_prestashop'];
			$id_option_value_prestashop = (int)$row['id_option_value_prestashop'];
			
			$stmt = $dbPrestashop->prepare('DELETE FROM `'._DB_PREFIX_.'c3_product_option_value` WHERE id_product=:id_product AND id_attribute_group=:id_attribute_group AND id_attribute=:id_attribute');
			$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
			$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
			$stmt->bindValue(':id_attribute', $id_option_value_prestashop, PDO::PARAM_INT);
			$result = $stmt->execute();
		}
		
		$query = 'SELECT id_product_prestashop, id_option_prestashop FROM vProductOptionToCreate';
		
		foreach ($dbModule->query($query) as $row){
			$id_product_prestashop = (int)$row['id_product_prestashop'];
			$id_option_prestashop = (int)$row['id_option_prestashop'];
			
			$stmt = $dbPrestashop->prepare('DELETE FROM `'._DB_PREFIX_.'c3_product_option` WHERE id_product=:id_product AND id_attribute_group=:id_attribute_group');
			$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
			$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
			$result = $stmt->execute();
		}
		
		$result = $this->displayConfirmation($this->l('All product options with defect are deleted'));
		
		return $result;
		
	}
	
	protected function GetProductWithDefects($db_path_start)
	{
		$database_path = $db_path_start . 'ProductDeletedInShop.sqlite';
		
		$dbModule = new PDO('sqlite:'.$database_path);
		$dbModule->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$dbModule->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		
		$query = 'SELECT DISTINCT dt.id_product AS id_product_prestashop FROM (SELECT DISTINCT p.id_product AS id_product, MAX(cpov.price) AS max_option_price FROM `'._DB_PREFIX_.'product` AS p INNER JOIN `'._DB_PREFIX_.'c3_product_option_value` AS cpov ON (cpov.id_product = p.id_product) WHERE p.price = 0 GROUP BY id_product) AS dt WHERE dt.max_option_price = 0';
		
		foreach ($dbPrestashop->query($query) as $row){
			$id_product_prestashop = (int)$row['id_product_prestashop'];
			
			$stmt = $dbModule->prepare('INSERT INTO ProductDeletedInShop(id_product_prestashop) VALUES (:id_product_prestashop)');
			$stmt->bindValue(':id_product_prestashop', $id_product_prestashop, SQLITE3_INTEGER);
			$result = $stmt->execute();
			
			
			$data = new Product($id_product_prestashop, false, 5, 1);
			$data->delete();
				
			
		}
		
		$result = $this->displayConfirmation($this->l('All product with defect are deleted'));
		
		return $result;
		
	}
	
	protected function ApplyCreationData($db_path_start, $id_action)
	{
		$database_path = $db_path_start;
		$result_txt = "creation executed!";
		$id_db = 4;
		$id_lang = 5;
		$dbs = array (
			++$id_db => array('DatabaseName' => 'Tag', 'View' => 'vExportTagToCreate')
			, ++$id_db => array('DatabaseName' => 'Brand', 'View' => 'vExportBrandToCreate')
			, ++$id_db => array('DatabaseName' => 'Feature', 'View' => 'vExportFeatureToCreate')
			, ++$id_db => array('DatabaseName' => 'Option', 'View' => 'vExportShopOptionToCreate')
			, ++$id_db => array('DatabaseName' => 'Product', 'View' => 'vProductInformationsToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductImage', 'View' => 'vProductImageToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductTag', 'View' => 'vProductTagToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductShelf', 'View' => 'vProductShelfToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductFeatureValue', 'View' => 'vProductFeatureValueToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductOption', 'View' => 'vProductOptionToCreate')
			, ++$id_db => array('DatabaseName' => 'ProductOptionValue', 'View' => 'vProductOptionValueToCreate')
		);
		$db_name = $dbs[$id_action]['DatabaseName'];
		$view_name = $dbs[$id_action]['View'];
		$database_path .= $db_name;
		$database_path .= 'ToCreate.db';
		
		$dbModule = new PDO('sqlite:'.$database_path);
		$dbModule->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$dbModule->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		
		if($db_name == 'Tag') {
			$query = 'SELECT id_tag, name FROM vExportTagToCreate';
			
			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_tag'];
				$name_data = $to_create['name'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data = new Tag(null, $id_lang);//pb id_lang
				$data->id_lang = $id_lang;
				$data->name = $name_conform_data;
				$data->add();
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'tag` SET name=:name WHERE id_tag=:id_tag AND id_lang=:id_lang');
				$stmt->bindValue(':name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_tag', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE Tag SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_tag=:id_tag');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_tag', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
		}
		else if($db_name == 'Brand') {
			$query = 'SELECT id_brand, name FROM vExportBrandToCreate';

			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_brand'];
				$name_data = $to_create['name'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data = new Manufacturer(null, $id_lang);
				$data->name = $name_conform_data;
				$data->active = true;
				$data->add();
				
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'manufacturer` SET name=:name WHERE id_manufacturer=:id_manufacturer');
				$stmt->bindValue(':name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_manufacturer', $id_prestashop, PDO::PARAM_INT);
				$stmt->execute();

				$stmt = $dbModule->prepare('UPDATE Brand SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_brand=:id_brand');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_brand', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
		}
		else if($db_name == 'Feature') {
			$query = 'SELECT id_feature, name FROM vExportFeatureToCreate';
			
			$to_create = $dbModule->query($query);
			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_feature'];
				$name_data = $to_create['name'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data =  new Feature(null, $id_lang, 1);
				$data->name = $name_conform_data;
				$data->add();
				
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'feature_lang` SET name=:name WHERE id_feature=:id_feature AND id_lang=:id_lang');
				$stmt->bindValue(':name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_feature', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE Feature SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_feature=:id_feature');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_feature', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
			
			$query = 'SELECT id_feature_value, id_feature, id_feature_ext, name FROM vExportFeatureValueToCreate';
			$to_create = $dbModule->query($query);

			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_feature_value'];
				$name_data = $to_create['name'];
				$id_feature = (int)$to_create['id_feature'];
				$id_feature_ext = (int)$to_create['id_feature_ext'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data =  new FeatureValue(null, $id_lang, 1);
				$data->id_feature = $id_feature_ext;
				$data->value = $name_conform_data;
				$data->add();
				
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'feature_value_lang` SET value=:value WHERE id_feature_value=:id_feature_value AND id_lang=:id_lang');
				$stmt->bindValue(':value', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_feature_value', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE FeatureValue SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_feature=:id_feature AND id_feature_value=:id_feature_value');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_feature', $id_feature, SQLITE3_INTEGER);
				$stmt->bindValue(':id_feature_value', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
		}
		else if($db_name == 'Option') {
			$query = 'SELECT id_option, name, 5 AS id_lang, option_type FROM vExportShopOptionToCreate';
			
			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_option'];
				$name_data = $this->LimitStringLength((string)$to_create['name'], 127);
				$id_lang = (int)$to_create['id_lang'];
				$option_type = (int)$to_create['option_type'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data =  new AttributeGroup(null, $id_lang, 1);
				$data->name = $name_conform_data;
				$data->public_name = $name_conform_data;
				switch($option_type) {
					case 2:
						$data->group_type = "select";
						break;
					case 3:
						$data->group_type = "text";
						break;
					case 4: // radio
						$data->group_type = "select";
						break;
					case 5:
						$data->group_type = "checkbox";
						break;
				}
				$data->add();
				
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'attribute_group_lang` SET name=:name, public_name=:public_name WHERE id_attribute_group=:id_attribute_group AND id_lang=:id_lang');
				$stmt->bindValue(':name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':public_name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_attribute_group', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ShopOption SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_option=:id_option');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
			
			$query = 'SELECT id_option_value, id_option, id_option_ext, name, 5 AS id_lang FROM vExportShopOptionValueToCreate';

			foreach ($dbModule->query($query) as $to_create) {
				$id_app = (int)$to_create['id_option_value'];
				$name_data = $this->LimitStringLength($to_create['name'], 127);
				$id_lang = (int)$to_create['id_lang'];
				$id_option = (int)$to_create['id_option'];
				$id_option_ext = (int)$to_create['id_option_ext'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($name_data);
				
				$data =  new Attribute(null, $id_lang, 1);
				$data->id_attribute_group = $id_option_ext;
				$data->name = $name_conform_data;
				$data->add();
				
				$id_prestashop = (int)$data->id;
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'attribute_lang` SET name=:name WHERE id_attribute=:id_attribute AND id_lang=:id_lang');
				$stmt->bindValue(':name', $name_data, PDO::PARAM_STR);
				$stmt->bindValue(':id_attribute', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ShopOptionValue SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_option=:id_option AND id_option_value=:id_option_value');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option', $id_option, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option_value', $id_app, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
		}
		else if($db_name == 'Product') {
			$query = 'SELECT id_product, description, description_short, meta_title, meta_description, title, old_price, current_price, weight, reference, is_available, id_manufacturer_prestashop, id_supplier_prestashop FROM vProductInformationsToCreate';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_product = (int)$to_create['id_product'];
				$special_description_value = false;
				$description_original = (string)$to_create['description'];
				$description = $description_original;
				
				if (strpos($description, 'iframe') !== false) {
					$description = "";
					$special_description_value = true;
				}
				
				$description_short_original = $this->LimitStringLength((string)$to_create['description_short'], 700);
				$description_short = $description_short_original;
				
				if (strpos($description_short, 'iframe') !== false) {
					$description_short = "";
					$special_description_value = true;
				}
					
				$meta_title = $this->LimitStringLength((string)$to_create['meta_title'], 125);
				$meta_description = $this->LimitStringLength((string)$to_create['meta_description'], 255);
				$title = $this->LimitStringLength((string)$to_create['title'], 125);
				$old_price = round((float)$to_create['old_price'], 2);
				$current_price = round((float)$to_create['current_price'], 2);
				$weight = round((float)$to_create['weight'], 2);
				$reference = (string)$to_create['reference'];
				$available = (bool)$to_create['is_available'];
				$id_manufacturer = (int)$to_create['id_manufacturer_prestashop'];
				$id_supplier = (int)$to_create['id_supplier_prestashop'];
				
				$name_conform_data = $this->ConformToPrefestashopAttribute($title);
				
				$data = new Product(null, false, $id_lang, 1);
				$data->id_manufacturer = $id_manufacturer;
				$data->id_supplier = $id_supplier;
				// $data->id_category_default = 472;
				$data->id_shop_default = 1;
				$data->name = $name_conform_data;
				$data->description = $description;
				$data->description_short = $description_short;
				
				$date = date('Y-m-d');
				$dateTomorrow = new DateTime('tomorrow');
				$date = $dateTomorrow->format('Y-m-d');
				
				$data->quantity = 50;
				$data->available_now = $date;
				$data->price = $old_price;
				$data->on_sale = ($old_price > $current_price? true : false);
				$data->reference = $reference;
				$data->supplier_reference = $reference;
				$data->weight = (string)$weight;
				$data->link_rewrite = Tools::str2url($data->name);
				$data->meta_description = $meta_description;
				$data->meta_title = $meta_title;
				$data->new = true;
				$data->active = true;
				$data->visibility = 'both';
				$data->date_add = $date;
				$data->date_upd = $date;
	
				
				$data->add();
				$id_prestashop = (int)$data->id;
				
				$data = new StockAvailable(null, $id_lang, 1);
				$data->id_product = $id_prestashop;
				$data->id_product_attribute = 0;
				$data->id_shop = 1;
				$data->quantity = 150;
				$data->add();
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'product_lang` SET name=:name, available_now=:available_now, available_later=:available_later WHERE id_product=:id_product AND id_lang=:id_lang');
				$stmt->bindValue(':name', $title, PDO::PARAM_STR);
				$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->bindValue(':available_now', "Produit en stock", PDO::PARAM_STR);
				$stmt->bindValue(':available_later', "Produit en reliquat mais précommande autorisée", PDO::PARAM_STR);
				$stmt->execute();
				
				$formated_old_price = number_format ($old_price, 2, '.', '');
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'product_shop` SET price=:price  WHERE id_product=:id_product');
				$stmt->bindValue(':price', $formated_old_price, PDO::PARAM_STR);
				$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				if($special_description_value) {
					$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'product_lang` SET description=:description, description_short=:description_short WHERE id_product=:id_product AND id_lang=:id_lang');
					$stmt->bindValue(':description', $description_original, PDO::PARAM_STR);
					$stmt->bindValue(':description_short', $description_short_original, PDO::PARAM_STR);
					$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
					$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
					$stmt->execute();
				}
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'product_supplier` SET id_product=:id_product, id_product_attribute=:id_product_attribute, id_supplier=:id_supplier, product_supplier_reference=:product_supplier_reference, id_currency=:id_currency');
				$stmt->bindValue(':id_currency', 2, PDO::PARAM_STR);
				$stmt->bindValue(':product_supplier_reference', $reference, PDO::PARAM_STR);
				$stmt->bindValue(':id_supplier', $id_supplier, PDO::PARAM_INT);
				$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_product_attribute', 0, PDO::PARAM_INT);
				$stmt->execute();
				
				/*
				$stmt = $dbPrestashop->prepare('INSERT INTO `'._DB_PREFIX_.'stock_available` (id_product, id_product_attribute, id_shop, id_shop_group, quantity, depends_on_stock, out_of_stock) SELECT :id_product, :id_product_attribute, :id_shop, :id_shop_group, :quantity, :depends_on_stock, :out_of_stock WHERE NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'stock_available` AS sa ON sa.id_product = :id_product AND sa.id_product_attribute = :id_product_attribute)');
				$stmt->bindValue(':id_shop_group', 0, PDO::PARAM_INT);
				$stmt->bindValue(':quantity', 100, PDO::PARAM_INT);
				$stmt->bindValue(':depends_on_stock', 1, PDO::PARAM_INT);
				$stmt->bindValue(':out_of_stock', 2, PDO::PARAM_INT);
				$stmt->bindValue(':id_shop', 1, PDO::PARAM_INT);
				$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_product_attribute', 0, PDO::PARAM_INT);
				$stmt->execute();
				*/
				
				if($available) {
					$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'stock_available` SET quantity=:quantity WHERE id_product=:id_product AND id_product_attribute=:id_product_attribute AND id_shop=:id_shop');
					$stmt->bindValue(':quantity', 100, PDO::PARAM_INT);
					$stmt->bindValue(':id_shop', 1, PDO::PARAM_INT);
					$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
					$stmt->bindValue(':id_product_attribute', 0, PDO::PARAM_INT);
					$stmt->execute();
				}
				
				$stmt = $dbModule->prepare('UPDATE ProductInformations SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_product=:id_product');
				$stmt->bindValue(':id_prestashop', $id_prestashop, SQLITE3_INTEGER);
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
			// correct product prices
			$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'product` AS pp INNER JOIN  `'._DB_PREFIX_.'product_shop` AS pps ON (pps.id_product = pp.id_product) SET pps.price = pp.price WHERE pps.price = 0 AND pp.price > 0');
			$stmt->execute();
		}
		else if($db_name == 'ProductImage') {
			$query = 'SELECT id_image, title, alt, id_product, id_product_prestashop, file_image, type_image FROM vProductImageToCreate';
			
			$last_product = 0;
			//$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_image = (int)$to_create['id_image'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				$title = (string)$to_create['title'];
				$alt = (string)$to_create['alt'];
				$file_image = (string)$to_create['file_image'];
				$type_image = (string)$to_create['type_image'];
				
				$title_conform_data = $this->ConformToPrefestashopAttribute($title);
				
				$data = new Image(null, $id_lang);
				$data->id_product = $id_product_prestashop;
				$data->legend = $title_conform_data;
				if($last_product != $id_product_prestashop) {
					$last_product = $id_product_prestashop;
					$data->cover = true;
				}
				$data->add();
				$id_image_prestashop = (int)$data->id;
				
				
				$source = realpath(dirname(__FILE__)). '/import/bundle/img/' . $file_image;
				$this->copyImg($id_product_prestashop, $id_image_prestashop, $source);
				
				$stmt = $dbPrestashop->prepare('UPDATE `'._DB_PREFIX_.'image_lang` SET legend=:legend WHERE id_image=:id_tag AND id_lang=:id_lang');
				$stmt->bindValue(':legend', $title, PDO::PARAM_STR);
				$stmt->bindValue(':id_image', $id_image_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_lang', $id_lang, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductImage SET id_prestashop=:id_prestashop, execution_result=1 WHERE id_product=:id_product AND id_image=:id_image');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_image', $id_image, SQLITE3_INTEGER);
				$stmt->bindValue(':id_prestashop', $id_image_prestashop, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
				
			}
			
		}
		else if($db_name == 'ProductTag') {
			$query = 'SELECT id_tag, id_tag_prestashop, id_product, id_product_prestashop FROM vProductTagToCreate';
			
			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_tag = (int)$to_create['id_tag'];
				$id_tag_prestashop = (int)$to_create['id_tag_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'product_tag` SET id_product=:id_product, id_tag=:id_tag');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_tag', $id_tag_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductTag SET execution_result=1 WHERE id_product=:id_product AND id_tag=:id_tag');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_tag', $id_tag, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
		}
		else if($db_name == 'ProductShelf') {
			$query = 'SELECT id_shelf, id_shelf_prestashop, id_product, id_product_prestashop FROM vProductShelfToCreate';
			
			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_shelf = (int)$to_create['id_shelf'];
				$id_shelf_prestashop = (int)$to_create['id_shelf_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'category_product` SET id_category=:id_category, id_product=:id_product, position=(SELECT COALESCE((MAX(cp3.position) + 1), 1) FROM `'._DB_PREFIX_.'category_product` AS cp3 WHERE cp3.id_category = :id_category)');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_category', $id_shelf_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductShelf SET execution_result=1 WHERE id_product=:id_product AND id_shelf=:id_shelf');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_shelf', $id_shelf, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
		}
		else if($db_name == 'ProductFeatureValue') {
			$query = 'SELECT id_feature_value, id_feature_value_prestashop, id_feature, id_feature_prestashop, id_product, id_product_prestashop FROM vProductFeatureValueToCreate';
			
			$to_create = $dbModule->query($query);
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_feature_value = (int)$to_create['id_feature_value'];
				$id_feature_value_prestashop = (int)$to_create['id_feature_value_prestashop'];
				$id_feature = (int)$to_create['id_feature'];
				$id_feature_prestashop = (int)$to_create['id_feature_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'feature_product` SET id_product=:id_product, id_feature=:id_feature, id_feature_value=:id_feature_value');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_feature', $id_feature_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_feature_value', $id_feature_value_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductFeatureValue SET execution_result=1 WHERE id_product=:id_product AND id_feature_value=:id_feature_value');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_feature_value', $id_feature_value, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
		}
		else if($db_name == 'ProductOption') {
			$query = 'SELECT id_product, id_product_prestashop, id_option, id_option_prestashop, position_option, is_required FROM vProductOptionToCreate';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_option = (int)$to_create['id_option'];
				$id_option_prestashop = (int)$to_create['id_option_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				$position_option = (int)$to_create['position_option'];
				$is_required = (int)$to_create['is_required'];
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'c3_product_option` SET id_product=:id_product, id_attribute_group=:id_attribute_group, required_option=:required_option, position=:position');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':required_option', $is_required, PDO::PARAM_INT);
				$stmt->bindValue(':position', $position_option, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductOption SET execution_result=1 WHERE id_product=:id_product AND id_option=:id_option');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option', $id_option, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
			
		}
		else if($db_name == 'ProductOptionValue') {
			
			$query = 'SELECT id_product, id_product_prestashop, id_option, id_option_prestashop, id_option_value, id_option_value_prestashop, reference, price, is_available FROM vProductOptionValueToCreate';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_option = (int)$to_create['id_option'];
				$id_option_prestashop = (int)$to_create['id_option_prestashop'];
				$id_option_value = (int)$to_create['id_option_value'];
				$id_option_value_prestashop = (int)$to_create['id_option_value_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				$price = (float)$to_create['price'];
				$is_available = (int)$to_create['is_available'];
				
				$stmt = $dbPrestashop->prepare('INSERT IGNORE INTO `'._DB_PREFIX_.'c3_product_option_value` SET id_product=:id_product, id_attribute_group=:id_attribute_group, id_attribute=:id_attribute, available_option=:available_option, price=:price');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute', $id_option_value_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':available_option', $is_available, PDO::PARAM_INT);
				$stmt->bindValue(':price', number_format($price, 2, '.', ''), PDO::PARAM_STR);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductOptionValue SET execution_result=1 WHERE id_product=:id_product AND id_option_value=:id_option_value');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option_value', $id_option_value, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
				
			}
		}
		
		$query = 'SELECT COUNT(*) AS nb_remaining FROM '.$view_name;
		$nb_remaining = 0;
		foreach ($dbModule->query($query) as $dt_r) {
			$nb_remaining = (int)$dt_r['nb_remaining'];
		}
		
		if($nb_remaining > 0)
			$result_txt .= " $nb_remaining products remaining, must repeat action[$id_action]";
		
		$result = $this->displayConfirmation($this->l($result_txt));
		
		return $result;
	}

	protected function ApplyUpdateData($id_action)
	{
		$database_path = $this->module_database_import_path_start;
		$result_txt = "update executed!";
		$id_db = 17;
		$id_lang = 5;
		$dbs = array (
			++$id_db => array('DatabaseName' => 'ProductPromo', 'db' => 'ProductPromoToMaintain.db')
			, ++$id_db => array('DatabaseName' => 'Product', 'db' => 'ProductToUpdate.db')
			, ++$id_db => array('DatabaseName' => 'ProductOptionValue', 'db' => 'ProductOptionValueToUpdate.db')
		);
		echo $id_action;
		$db_name = $dbs[$id_action]['DatabaseName'];
		$database_path .= $dbs[$id_action]['db'];
		
		$dbModule = new PDO('sqlite:'.$database_path);
		$dbModule->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$dbModule->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		if($db_name == 'ProductPromo') {
			$query = 'SELECT id_product, id_product_prestashop, reduction_amount, reduction_type FROM  vProductPromoToMaintain';
			$priority = 'id_shop;id_currency;id_country;id_group';
			foreach ($dbModule->query($query) as $to_create) {
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				$reduction_amount = round((float)$to_create['reduction_amount'], 2);
				$reduction_type= (string)$to_create['reduction_type'];
				
				// test if product promo rule is created
				$query = 'SELECT id_product FROM `'._DB_PREFIX_.'specific_price_priority` WHERE id_product=:id_product';
				$stmt = $dbPrestashop->prepare($query);
				$stmt->execute(array(':id_product' => $id_product_prestashop));
				$test_rule = 0;
				foreach ($stmt->fetchAll() as $to_create) {
					$test_rule = (int)$to_create['id_product'];
				}
				// create rule for promo
				if($test_rule == 0) {
					$query = 'INSERT INTO `'._DB_PREFIX_.'specific_price_priority`(id_product, priority) VALUES (:id_product, :priority)';
					$stmt = $dbPrestashop->prepare($query);
					$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
					$stmt->bindValue(':priority', $priority, PDO::PARAM_STR);
					$stmt->execute();
				}
				
				// test if product promo was already created
				$query = 'SELECT id_product FROM `'._DB_PREFIX_.'specific_price` WHERE id_product=:id_product AND `to` > NOW()';
				$stmt = $dbPrestashop->prepare($query);
				$stmt->execute(array(':id_product' => $id_product_prestashop));
				$test_creation = 0;
				foreach ($stmt->fetchAll() as $to_create) {
					$test_creation = (int)$to_create['id_product'];
				}
				// create promo
				if($test_creation == 0) {
					$reduction = number_format ($reduction_amount/100.0, 2, '.', '');
					$query = 'INSERT INTO `'._DB_PREFIX_.'specific_price`(id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group, id_currency, id_country, id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax, reduction_type, `from`, `to`) VALUES (0, 0, :id_product, 1, 0, 0, 0, 0, 0, 0, -1.0, 1, :reduction, 0, :reduction_type, NOW(), DATE_ADD(NOW(), INTERVAL 1 WEEK))';
					$stmt = $dbPrestashop->prepare($query);
					$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
					$stmt->bindValue(':reduction', $reduction, PDO::PARAM_STR);
					$stmt->bindValue(':reduction_type', $reduction_type, PDO::PARAM_STR);
					$stmt->execute();
				}
				
				$query = 'UPDATE ProductPromo SET execution_result=1 WHERE id_product=:id_product';
				$stmt = $dbModule->prepare($query);
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
		}
		else if($db_name == 'Product') {
			$query = 'SELECT id_product, id_product_prestashop, old_price, current_price, is_available FROM vProductInformationsToUpdate';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_product = (int)$to_create['id_product'];
				$id_prestashop = (int)$to_create['id_product_prestashop'];
				$old_price = round((float)$to_create['old_price'], 2);
				$current_price = round((float)$to_create['current_price'], 2);
				$available = (bool)$to_create['is_available'];
				
				$quantity = 100;
				$out_of_stock = 2;
				if (!$available) {
					$quantity = 0;
					$out_of_stock = 0;
				}
				
				$query = 'UPDATE `'._DB_PREFIX_.'stock_available` SET quantity=:quantity, out_of_stock=:out_of_stock WHERE id_product=:id_product';
				$stmt = $dbPrestashop->prepare($query);
				$stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
				$stmt->bindValue(':out_of_stock', $out_of_stock, PDO::PARAM_INT);
				$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				if($old_price > 0) {
					$query = 'UPDATE `'._DB_PREFIX_.'product_shop` SET price=:price, date_upd = NOW() WHERE id_product=:id_product';
					$formated_old_price = number_format ($old_price, 2, '.', '');
					$stmt = $dbPrestashop->prepare($query);
					$stmt->bindValue(':price', $formated_old_price, PDO::PARAM_STR);
					$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
					$stmt->execute();
				}
				else {
					$query = 'UPDATE `'._DB_PREFIX_.'product_shop` SET date_upd = NOW() WHERE id_product=:id_product';
					$stmt = $dbPrestashop->prepare($query);
					$stmt->bindValue(':id_product', $id_prestashop, PDO::PARAM_INT);
					$stmt->execute();
				}
				
				$query = 'UPDATE ProductInformations SET execution_result=1 WHERE id_product=:id_product';
				$stmt = $dbModule->prepare($query);
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$result = $stmt->execute();
				$result_txt .= $query;
				
			}
		}
		else if($db_name == 'ProductOptionValue') {
			
			$query = 'SELECT id_product, id_product_prestashop, id_option, id_option_prestashop, id_option_value, id_option_value_prestashop, price, is_available FROM vProductOptionValueToUpdate';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_option = (int)$to_create['id_option'];
				$id_option_prestashop = (int)$to_create['id_option_prestashop'];
				$id_option_value = (int)$to_create['id_option_value'];
				$id_option_value_prestashop = (int)$to_create['id_option_value_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				$price = (float)$to_create['price'];
				$is_available = (int)$to_create['is_available'];
				
				$query = 'UPDATE `'._DB_PREFIX_.'c3_product_option_value` SET available_option=:available_option, price=:price WHERE id_product=:id_product, id_attribute_group=:id_attribute_group, id_attribute=:id_attribute';
				$stmt = $dbPrestashop->prepare($query);
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute', $id_option_value_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':available_option', $is_available, PDO::PARAM_INT);
				$stmt->bindValue(':price', number_format($price, 2, '.', ''), PDO::PARAM_STR);
				$stmt->execute();
				
				$query = 'UPDATE ProductOptionValue SET execution_result=1 WHERE id_product=:id_product AND id_option_value=:id_option_value';
				$stmt = $dbModule->prepare($query);
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option_value', $id_option_value, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
		}
		
		$result = $this->displayConfirmation($this->l($result_txt));
		
		return $result;
	}
	
	protected function ApplyDeleteData($id_action)
	{
		$database_path = $this->module_database_import_path_start;
		$result_txt = "delete executed!";
		$id_db = 20;
		$id_lang = 5;
		$dbs = array (
			++$id_db => array('DatabaseName' => 'Product', 'db' => 'ProductToDelete.db')
			, ++$id_db => array('DatabaseName' => 'ProductOption', 'db' => 'ProductOptionToDelete.db')
			, ++$id_db => array('DatabaseName' => 'ProductOptionValue', 'db' => 'ProductOptionValueToDelete.db')
		);
		$db_name = $dbs[$id_action]['DatabaseName'];
		$database_path .= $dbs[$id_action]['db'];
		
		$dbModule = new PDO('sqlite:'.$database_path);
		$dbModule->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$dbModule->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$dbPrestashop = new PDO('mysql:host='._DB_SERVER_.';dbname='._DB_NAME_, _DB_USER_, _DB_PASSWD_, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		
		if($db_name == 'Product') {
			$query = 'SELECT id_product, id_product_prestashop FROM vProductToDelete LIMIT 50';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$query = 'SELECT 1 AS found_product_used FROM `'._DB_PREFIX_.'order_detail` WHERE product_id = :id_product';
				$stmt = $dbPrestashop->prepare($query);
				$stmt->execute(array(':id_product' => $id_product_prestashop));
				$test_creation = 0;
				foreach ($stmt->fetchAll() as $to_create) {
					$test_creation = (int)$to_create['found_product_used'];
				}
				// create promo
				if($test_creation == 0) {
					$product = new Product($id_product_prestashop, false, $id_lang, 1);
					$product->delete();
				}
				else {
					$query = 'UPDATE `'._DB_PREFIX_.'stock_available` SET quantitiy = 0 WHERE id_product = :id_product';
					$stmt = $dbPrestashop->prepare($query);
					$stmt->execute(array(':id_product' => $id_product_prestashop));
				}
				
				$stmt = $dbModule->prepare('UPDATE Product SET execution_result=1 WHERE id_product=:id_product');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$result = $stmt->execute();
				
			}
			
			$query = 'SELECT COUNT(*) AS nb_remaining FROM vProductToDelete';
			$nb_remaining = 0;
			foreach ($dbModule->query($query) as $dt_r) {
				$nb_remaining = (int)$dt_r['nb_remaining'];
			}
			
			if($nb_remaining > 0)
				$result_txt = " $nb_remaining products remaining, must repeat action[$id_action]";

		}
		else if($db_name == 'ProductOption') {
			
			$query = 'SELECT id_product, id_product_prestashop, id_option, id_option_prestashop FROM vProductOptionToDelete';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_option = (int)$to_create['id_option'];
				$id_option_prestashop = (int)$to_create['id_option_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$stmt = $dbPrestashop->prepare('DELETE FROM `'._DB_PREFIX_.'c3_product_option` WHERE id_product=:id_product AND id_attribute_group=:id_attribute_group');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductOption SET execution_result=1 WHERE id_product=:id_product AND id_option=:id_option');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option', $id_option, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
		}
		else if($db_name == 'ProductOptionValue') {
			
			$query = 'SELECT id_product, id_product_prestashop, id_option, id_option_prestashop, id_option_value, id_option_value_prestashop FROM vProductOptionValueToDelete';
			
			foreach ($dbModule->query($query) as $to_create) {
				$id_option = (int)$to_create['id_option'];
				$id_option_prestashop = (int)$to_create['id_option_prestashop'];
				$id_option_value = (int)$to_create['id_option_value'];
				$id_option_value_prestashop = (int)$to_create['id_option_value_prestashop'];
				$id_product = (int)$to_create['id_product'];
				$id_product_prestashop = (int)$to_create['id_product_prestashop'];
				
				$stmt = $dbPrestashop->prepare('DELETE FROM `'._DB_PREFIX_.'c3_product_option_value` WHERE id_product=:id_product AND id_attribute_group=:id_attribute_group AND id_attribute=:id_attribute');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute', $id_option_value_prestashop, PDO::PARAM_INT);
				$stmt->execute();
				
				$stmt = $dbModule->prepare('UPDATE ProductOptionValue SET execution_result=1 WHERE id_product=:id_product AND id_option_value=:id_option_value');
				$stmt->bindValue(':id_product', $id_product, SQLITE3_INTEGER);
				$stmt->bindValue(':id_option_value', $id_option_value, SQLITE3_INTEGER);
				$result = $stmt->execute();
			}
			
			// delete option without values
			$query = 'SELECT po.id_product, po.id_attribute_group FROM `'._DB_PREFIX_.'c3_product_option` AS po LEFT JOIN `'._DB_PREFIX_.'c3_product_option_value` AS pov ON (pov.id_product = po.id_product AND pov.id_attribute_group = po.id_attribute_group) WHERE pov.id_product';
			
			foreach ($dbPrestashop->query($query) as $to_delete) {
				$id_product = (int)$to_create['id_product'];
				$id_attribute_group = (int)$to_create['id_attribute_group'];
				
				$stmt = $dbPrestashop->prepare('DELETE FROM `'._DB_PREFIX_.'c3_product_option` WHERE id_product=:id_product AND id_attribute_group=:id_attribute_group');
				$stmt->bindValue(':id_product', $id_product_prestashop, PDO::PARAM_INT);
				$stmt->bindValue(':id_attribute_group', $id_option_prestashop, PDO::PARAM_INT);
				$stmt->execute();
			}
		}
		
		$result = $this->displayConfirmation($this->l($result_txt));
		
		return $result;
	}
	
	protected function LimitStringLength($str, $length)
	{
		if(strlen($str) > $length)
			return substr($str, 0, ($length - 1));
		else
			return $str;
	}
	
	protected function copyImg($id_entity, $id_image = null, $url, $entity = 'products', $regenerate = true)
	{
		$tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
		$watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

		switch ($entity)
		{
			default:
			case 'products':
				$image_obj = new Image($id_image);
				$path = $image_obj->getPathForCreation();
			break;
			case 'categories':
				$path = _PS_CAT_IMG_DIR_.(int)$id_entity;
			break;
			case 'manufacturers':
				$path = _PS_MANU_IMG_DIR_.(int)$id_entity;
			break;
			case 'suppliers':
				$path = _PS_SUPP_IMG_DIR_.(int)$id_entity;
			break;
		}

		$url = str_replace(' ', '%20', trim($url));
		$url = urldecode($url);
		$parced_url = parse_url($url);

		if (isset($parced_url['path']))
		{
			$uri = ltrim($parced_url['path'], '/');
			$parts = explode('/', $uri);
			foreach ($parts as &$part)
				$part = urlencode ($part);
			unset($part);
			$parced_url['path'] = '/'.implode('/', $parts);
		}

		if (isset($parced_url['query']))
		{
			$query_parts = array();
			parse_str($parced_url['query'], $query_parts);
			$parced_url['query'] = http_build_query($query_parts);
		}

		if (!function_exists('http_build_url'))
			require_once(_PS_TOOL_DIR_.'http_build_url/http_build_url.php');

		$url = http_build_url('', $parced_url);

		// Evaluate the memory required to resize the image: if it's too much, you can't resize it.
		if (!ImageManager::checkImageMemoryLimit($url))
			return false;

		// 'file_exists' doesn't work on distant file, and getimagesize makes the import slower.
		// Just hide the warning, the processing will be the same.
		if (Tools::copy($url, $tmpfile))
		{
			ImageManager::resize($tmpfile, $path.'.jpg');
			$images_types = ImageType::getImagesTypes($entity);

			if ($regenerate)
				foreach ($images_types as $image_type)
				{
					ImageManager::resize($tmpfile, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'], $image_type['height']);
					if (in_array($image_type['id_image_type'], $watermark_types))
						Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
				}
		}
		else
		{
			unlink($tmpfile);
			return false;
		}
		unlink($tmpfile);
		return true;
	}

	protected function flushProductDeletedToDatabase($db, $all_id_to_delete, $products_to_delete) {
		// detect product already sold one time
		$products_to_keep = [];
		$query = 'SELECT DISTINCT cp.`id_product`
			FROM `'._DB_PREFIX_.'orders` o 
			INNER JOIN `'._DB_PREFIX_.'cart_product` cp ON (cp.id_cart = o.id_cart) 
			WHERE cp.`id_product` IN ('.$all_id_to_delete.')';
		$found_products_to_keep = Db::getInstance()->executeS($query);
		foreach ($found_products_to_keep as $product_to_keep) {
			$id_product = (int)$product_to_keep['id_product'];
			if(($key = array_search($id_product, $products_to_delete)) !== false)
				unset($products_to_delete[$key]);
			$products_to_keep[] = $id_product;
		}
		
		//delete product not used
		$products_deleted = '';
		$first_product = true;
		foreach ($products_to_delete as $id_product) {
			$data = new Product($id_product, false, $id_lang);
			$data->delete();
			
			if($first_product)
				$first_product = false;
			else
				$products_deleted .= ',';
			$products_deleted .= $id_product;
		}
		
		//mark unavailable all product bought
		$products_kept = '';
		$first_product = true;
		foreach ($products_to_keep as $id_product) {
			$data = new Product($id_product, false, $id_lang);
			$data->active = false;
			$data->advanced_stock_management = 0;
			$data->update();
			StockAvailable::setQuantity($data->id, null, 0);
			
			if($first_product)
				$first_product = false;
			else
				$products_kept .= ',';
			$products_kept .= $id_product;
			
		}
		
		
		$query = 'UPDATE Product SET execution_result=1 WHERE id_prestashop IN ('.$products_deleted.')';
		$db->exec($query);
		
		
		$query = 'UPDATE Product SET execution_result=2 WHERE id_prestashop IN ('.$products_kept.')';
		$db->exec($query);
		
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
			array_push($product_data['data'], $attribute);
			//if(count($attribute['data']) > 0)
			//	array_push($product_data['data'], $attribute);
		}
	}

	//backend form creation
	public function renderForm()
	{
		$actions = array(
			array('id_action' => (string)0, 'name' => $this->l('Nothing'))
			,array('id_action' => (string)1, 'name' => $this->l('Regenerate products options caches'))
			,array('id_action' => (string)2, 'name' => $this->l('Extract data'))
			,array('id_action' => (string)3, 'name' => $this->l('Prepare data for export'))
			,array('id_action' => (string)4, 'name' => $this->l('test data'))
			// all creation commande
			,array('id_action' => (string)5, 'name' => $this->l('Apply Tag creation'))
			,array('id_action' => (string)6, 'name' => $this->l('Apply Brand creation'))
			,array('id_action' => (string)7, 'name' => $this->l('Apply Feature creation'))
			,array('id_action' => (string)8, 'name' => $this->l('Apply Option creation'))
			,array('id_action' => (string)9, 'name' => $this->l('Apply Product creation'))
			,array('id_action' => (string)10, 'name' => $this->l('Apply ProductImage creation'))
			,array('id_action' => (string)11, 'name' => $this->l('Apply ProductTag creation'))
			,array('id_action' => (string)12, 'name' => $this->l('Apply ProductShelf creation'))
			,array('id_action' => (string)13, 'name' => $this->l('Apply ProductFeature creation'))
			,array('id_action' => (string)14, 'name' => $this->l('Apply ProductOption creation'))
			,array('id_action' => (string)15, 'name' => $this->l('Apply ProductOptionValue creation'))
			,array('id_action' => (string)16, 'name' => $this->l('Get Product with defect to delete'))
			,array('id_action' => (string)17, 'name' => $this->l('Cancel Product Options'))
			// all update commande
			,array('id_action' => (string)18, 'name' => $this->l('Apply promo update'))
			,array('id_action' => (string)19, 'name' => $this->l('Apply Product update'))
			,array('id_action' => (string)20, 'name' => $this->l('Apply ProductOption update'))
			// all delete commande
			,array('id_action' => (string)21, 'name' => $this->l('Apply Product deletion'))
			,array('id_action' => (string)22, 'name' => $this->l('Apply ProductOption deletion'))
			,array('id_action' => (string)23, 'name' => $this->l('Apply ProductOptionValue deletion'))
		);
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
					,array(
							'type' => 'select',
							'label' => $this->l('Action to launch.'),
							'name' => 'C3PRODUCTOPTIONS_ACTION',
							'class' => 'fixed-width-xs',
							'desc' => $this->l('Defines the action for the backend to performe'),
							'options' => array(
							'query' => $actions,
							'id' => 'id_action',
							'name' => 'name'
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
