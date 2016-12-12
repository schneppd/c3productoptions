<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/


class Product extends ProductCore
{

	/**
	* Check if product has attributes combinations
	*
	* @return integer Attributes combinations number
	*/

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function hasAttributes()
	{
		if (!Combination::isFeatureActive())
			return 0;
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
			SELECT COUNT(*)
			FROM `'._DB_PREFIX_.'c3_product_option` po 
			WHERE po.`id_product` = '.(int)$this->id
		);
	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductEcotax($id_product, $id_shop){
		//get ecotax
		$sql = new DbQuery();
		$sql->select('ecotax');
		$sql->from('product_shop');
		$sql->where('id_product = '.(int)$id_product);
		$sql->where('id_shop = '.(int)$id_shop);
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
	}
	/*
	* module: c3productoptions
	* date: 2016-07-11 01:38:57
	* version: 1.0.0
	 * return true if the given product id is managed by old prestashop logic
	*/
	public static function c3GetProductManagedByC3Module($id_product){
		//get ecotax
		$sql = new DbQuery();
		$sql->select('COUNT(id_product_attribute)');
		$sql->from('product_attribute');
		$sql->where('id_product = '.(int)$id_product);
		return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql) == 0;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductAddress($context, $id_address){
		$cur_cart = $context->cart;
		// retrieve address informations
		$id_country = (int)$context->country->id;
		$id_state = 0;
		$zipcode = 0;

		if (!$id_address && Validate::isLoadedObject($cur_cart))
			$id_address = $cur_cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};

		if ($id_address)
		{
			$address_infos = Address::getCountryAndState($id_address);
			if ($address_infos['id_country'])
			{
				$id_country = (int)$address_infos['id_country'];
				$id_state = (int)$address_infos['id_state'];
				$zipcode = $address_infos['postcode'];
			}
		}
		elseif (isset($context->customer->geoloc_id_country))
		{
			$id_country = (int)$context->customer->geoloc_id_country;
			$id_state = (int)$context->customer->id_state;
			$zipcode = $context->customer->postcode;
		}

		$address = new Address();
		// Tax
		$address->id_country = $id_country;
		$address->id_state = $id_state;
		$address->postcode = $zipcode;
		return $address;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductTaxManager($id_product, $context, $address){

		$tax_manager = TaxManagerFactory::getManager($address, Product::getIdTaxRulesGroupByIdProduct($id_product, $context));
		return $tax_manager->getTaxCalculator();
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductUseTaxe($usetax, $id_address){
		$address_infos = Address::getCountryAndState($id_address);
		if (Tax::excludeTaxeOption())
			return false;

		if ($usetax != false
			&& !empty($address_infos['vat_number'])
			&& $address_infos['id_country'] != Configuration::get('VATNUMBER_COUNTRY')
			&& Configuration::get('VATNUMBER_MANAGEMENT'))
			return false;
		return $usetax;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductOptionsFinalPrice($price, $product_tax_manager, $product_use_tax, $product_ecotax, $context, $decimals, $address, $with_ecotax){
		// Add Tax
		if ($product_use_tax)
			$price = $product_tax_manager->addTaxes($price);
		$id_currency = (int)Validate::isLoadedObject($context->currency) ? $context->currency->id : Configuration::get('PS_CURRENCY_DEFAULT');
		// Eco Tax
		if ($product_ecotax && $with_ecotax)
		{
			$ecotax = $product_ecotax;

			if ($id_currency)
				$ecotax = Tools::convertPrice($ecotax, $id_currency);
			if ($product_use_tax)
			{
				// reinit the tax manager for ecotax handling
				$tax_manager = TaxManagerFactory::getManager(
					$address,
					(int)Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID')
				);
				$ecotax_tax_calculator = $tax_manager->getTaxCalculator();
				$price += $ecotax_tax_calculator->addTaxes($ecotax);
			}
			else
				$price += $ecotax;
		}
		$price = Tools::ps_round($price, $decimals);
		if ($price < 0)
			$price = 0;
		return $price;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductOptionsPriceData($id_product, $id_cart, $id_shop, $product_tax_manager, $product_use_tax, $product_ecotax, $context, $decimals, $address, $with_ecotax){
		$res = [];
		$sql = new DbQuery();
		if($id_cart > 0){//product with options given
			$sql->from('c3_cart_product_customization');
			$sql->select('selection_value');
			$sql->where('id_product = '.(int)$id_product);
			$sql->where('id_cart = '.(int)$id_cart);
			$sql->where('id_shop = '.(int)$id_shop);
			$options = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
			foreach ($options as $data_option){

				$selection_value = $data_option['selection_value'];
				$raw_price = Product::c3GetProductOptionsRawPrice($selection_value, $id_product);
				$final_price_with_tax = Product::c3GetProductOptionsFinalPrice($raw_price, $product_tax_manager, $product_use_tax, $product_ecotax, $context, $decimals, $address, $with_ecotax);
		

				array_push($res, $final_price_with_tax);
			}
		}
		else{//must guess min price of options for product
			$sql->from('c3_product_option', 'po');
			$sql->select('po.id_attribute_group, MAX(pov.price) AS min_price');
			$sql->innerJoin('c3_product_option_value', 'pov', 'pov.id_product = po.id_product AND pov.id_attribute_group = po.id_attribute_group');
			$sql->where('po.required_option = '.true);
			$sql->groupBy('po.id_attribute_group');
			$min_options = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
			$raw_price_min_required = 0.0;
			foreach ($min_options as $data_option)
				$raw_price_min_required += (float)$data_option['min_price'];
			$final_price_with_tax = Product::c3GetProductOptionsFinalPrice($raw_price_min_required, $product_tax_manager, $product_use_tax, $product_ecotax, $context, $decimals, $address, $with_ecotax);
			array_push($res, $final_price_with_tax);
		}
		return $res;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductOptionsRawPrice($selection_value, $id_product){
		$sData = Product::c3SanitizeCombinationData($selection_value);//ex valid data: 127-12456_123-1458745 (id-attribute-group_id-attribute)
		$options_price = 0.0;
		foreach (explode('_', $sData) as $option){
			$option_ids = explode('-', $option);
			$id_attribute_group = (int)$option_ids[0];
			$id_attribute = (int)$option_ids[1];

			$sql = new DbQuery();
			$sql->from('c3_product_option_value');
			$sql->select('price');
			$sql->where('id_product = '.(int)$id_product);
			$sql->where('id_attribute_group = '.(int)$id_attribute_group);
			$sql->where('id_attribute = '.(int)$id_attribute);
			$options_price += (float)Db::getInstance()->getValue($sql);
		}
		return $options_price;
	}

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3getProductInCartPrice($id_product, $usetax = true, $id_product_attribute = null, $decimals = 6, $divisor = null,
		$only_reduc = false, $usereduc = true, $quantity = 1, $force_associated_tax = false, $id_customer = null, $id_cart = null,
		$id_address = null, &$specific_price_output = null, $with_ecotax = true, $use_group_reduction = true, Context $context = null,
		$use_customer_price = true)
	{
		$res = [];
		$res['options'] = [];
		$res['basic_price'] = Product::getPriceStatic($id_product, $usetax, $id_product_attribute, $decimals, $divisor,
		$only_reduc, $usereduc, $quantity, $force_associated_tax, $id_customer, $id_cart,
		$id_address, $specific_price_output, $with_ecotax, $use_group_reduction, $context,
		$use_customer_price);
		$id_product = (int)$id_product;
		//c3 add customizations prices
		if(Product::c3HasOptions($id_product)){
			$id_shop = (int)Context::getContext()->shop->id;

			$product_ecotax = Product::c3GetProductEcotax($id_product, $id_shop);
			$address = Product::c3GetProductAddress($context, $id_address);
			$product_tax_manager = Product::c3GetProductTaxManager($id_product, $context, $address);
			$product_use_tax = Product::c3GetProductUseTaxe($usetax, $id_address);
			$res['options'] = Product::c3GetProductOptionsPriceData($id_product, $id_cart, $id_shop, $product_tax_manager, $product_use_tax, $product_ecotax, $context, $decimals, $address, $with_ecotax);

		}
		return $res;
	}

	//check if product has options or not

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3HasOptions($id_product)
	{

		$sql = new DbQuery();
		$sql->select('COUNT(*)');
		$sql->from('c3_product_option');
		$sql->where('id_product = '.$id_product);

		$count = (int)Db::getInstance()->getValue($sql);

		if($count > 0)
			return true;
		
		return false;
	}
	/*
	 * used to allow add / remove 1 more product directly from cart
	 */
	public static function c3hasPreviousOptionCustomization($id_product, $context)
	{
		$sql = new DbQuery();
		$sql->select('COUNT(*)');
		$sql->from('cart_product');
		$sql->where('id_product = '.$id_product);
		$sql->where('id_cart = '.(int)$context->cart->id);

		$count = (int)Db::getInstance()->getValue($sql);

		if($count > 0)
			return true;

		return false;
	}

	//get minimal options combination price, without tax
	public static function c3GetProductMinOptionsPrice($id_product)
	{

		$sql = new DbQuery();
		$sql->select('price');
		$sql->from('vc3_product_min_option_price');
		$sql->where('id_product = '.$id_product);
		$min_options = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
		$raw_price_min_required = 0;
		foreach ($min_options as $data_option)
			$raw_price_min_required += (float)$data_option['price'];
		
		return $raw_price_min_required;
	}

	//get product's option description for cart summary

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function c3GetProductOptionsDescription($id_cart, $id_product, $id_shop)
	{
		$id_product = (int)$id_product;
		$res = [];
		$res['full-description'] = 'options: ';//get all options
		$res['short-description'] = 'option: ';//get last option added

		$sql = new DbQuery();
		$sql->select('selection_value, COUNT(*) AS nb');
		$sql->from('c3_cart_product_customization');
		$sql->where('id_product = '.$id_product);
		$sql->where('id_shop = '.$id_shop);
		$sql->where('id_cart = '.$id_cart);
		$sql->groupBy('selection_value');


		$options = Db::getInstance()->executeS($sql);
		foreach ($options as $key => $option){
			$selection_value = $option['selection_value'];
			$nb = (int)$option['nb'];
			$desc_options = Product::c3GetProductOptionsDataToString($selection_value);
			$add = ' ';
			if($key == 0)
				$res['short-description'] .= $desc_options;
			else
				$add = ' +';
			$res['full-description'] .= $add.$nb.' * ('.$desc_options.')';
		}
		
		return $res;
	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	private static function c3GetProductOptionsDataToString($selection_value)
	{
		$workStringJustId = Product::c3SanitizeCombinationData($selection_value);
		if($workStringJustId == 'invalid')
			return 'bad string inputs';//todo properly deal with pb
		preg_match_all('/((\d+)-(\d+)\[\[\[([^\]]+)\]\])/',$selection_value,$matchs);//get options with string input ex: 127-12546[[[bla bla bla]]] will give: 127(id_attribute_group),12546(id_attribute), bla bla bla (user input)
		$string_inputs = [];
		if(count($matchs) > 0){
			foreach ($matchs[0] as $key => $match){
				//push [id_attribute_group, id_attribute, txt_input]
				$id_attribute_group = $matchs[2][$key];
				$id_attribute = $matchs[3][$key];
				$string_inputs[$id_attribute_group.'-'.$id_attribute] = $matchs[4][$key];
			}
		}
		$res = '';
		$id_lang = (int)Context::getContext()->language->id;
		foreach (explode('_', $workStringJustId) as $key => $option){
			$option_ids = explode('-', $option);
			$id_attribute_group = $option_ids[0];
			$id_attribute = $option_ids[1];
			if($key > 0)
				$res .= ', ';

			$sql = new DbQuery();
			$sql->select('group_type, lbl_option, lbl_option_value');
			$sql->from('vc3_product_option_description');
			$sql->where('id_lang = '.$id_lang);
			$sql->where('id_attribute_group = '.(int)$id_attribute_group);
			$sql->where('id_attribute = '.(int)$id_attribute);
			$options_data = Db::getInstance()->executeS($sql);
			foreach ($options_data as $option_data){
				$group_type = $option_data['group_type'];
				switch($group_type){
					case 'select':
						$res .= $option_data['lbl_option'].': '.$option_data['lbl_option_value'];
					break;
					case 'radio':
						$res .= $option_data['lbl_option'].': '.$option_data['lbl_option_value'];
					break;
					case 'checkbox':
						$res .= $option_data['lbl_option'].': ok';
					break;
					case 'text':
						$res .= $option_data['lbl_option'].': ';
						if(array_key_exists ($id_attribute_group.'-'.$id_attribute, $string_inputs)){
							$res .= $string_inputs[$id_attribute_group.'-'.$id_attribute];
						} else
							$res .= '1 missing string inputs'.json_encode($string_inputs);//todo properly deal with pb
					break;
				}

			}
		}

		return $res;
	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	private static function c3SanitizeCombinationData($c3_combination_data)
	{
		$workString = $c3_combination_data;
		while(strpos($workString, '[[[')){//text data to remove
			$posStart = strpos($workString, '[[[');
			$posEnd = strpos($workString, ']]]');
			if(!$posEnd)//no matching brackets, pb wrong inputs
				return 'invalid';
			$txtString = substr($workString, $posStart, (($posEnd - $posStart) + 3));
			$workString = str_replace($txtString, "", $workString);
		}

		return $workString;
	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function c3HasAllRequiredCustomizableFields($id_product, $c3_combination_data, Context $context = null)
	{
		//validate if product managed by old prestashop logic
		if(Product::c3GetProductManagedByC3Module($id_product)) {
			//exemple valid value for $c3_combination_data: 123-14582_124-14589[[[text given by user]]]_129-14256
			if (!$context)
				$context = Context::getContext();
			$id_product = (int)$id_product;
			if (!Product::c3HasOptions($id_product) && $c3_combination_data == "")
				return true;
			if (Product::c3hasPreviousOptionCustomization($id_product, $context) && $c3_combination_data == "")//user add product from cart form
				return true;
			$c3_combination_data_sanitized = Product::c3SanitizeCombinationData($c3_combination_data);
			if ($c3_combination_data_sanitized == 'invalid')
				return false;
			if (!preg_match('/^[\d-_]+$/', $c3_combination_data_sanitized))
				return false;

			$processed_combination_data;
			preg_match_all('/((\d+)-(\d+))/', $c3_combination_data_sanitized, $processed_combination_data);

			$selectedIdAttributeGroups = '';
			$selectedIdAttributes = '';
			foreach ($processed_combination_data[0] as $key => $part) {
				$id_attribute_group = (int)$processed_combination_data[2][$key];
				$id_attribute = (int)$processed_combination_data[3][$key];
				if ($key > 0) {
					$selectedIdAttributeGroups .= ',';
					$selectedIdAttributes .= ',';
				}
				$selectedIdAttributeGroups .= (string)$id_attribute_group;
				$selectedIdAttributes .= (string)$id_attribute;
			}


			if (Product::c3hasMissingRequiredOptions($id_product, $selectedIdAttributeGroups))
				return false;
			if (Product::c3hasPartOfSelectionUnavailable($id_product, $selectedIdAttributes))
				return false;
		}

		return true;
	}

	public static function c3hasPartOfSelectionUnavailable($id_product, $selectedIdAttributes)
	{
		$sql = new DbQuery();
		$sql->from('c3_product_option_value', 'po');
		$sql->select('COUNT(*)');
		$sql->where('po.`id_product` = ' . $id_product);
		$sql->where('po.id_attribute IN(' . $selectedIdAttributes . ')');
		$sql->where('po.`available_option` = 0');
		$count = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
		return ($count > 0);
	}

	public static function c3hasMissingRequiredOptions($id_product, $selectedIdAttributeGroups)
	{
		$sql = new DbQuery();
		$sql->from('c3_product_option', 'po');
		$sql->select('COUNT(*)');
		$sql->where('po.`id_product` = ' . $id_product);
		$sql->where('po.id_attribute_group NOT IN(' . $selectedIdAttributeGroups . ')');
		$sql->where('po.`required_option` = 1');
		$count = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
		return ($count > 0);
	}

	/**
	* Get a random special
	* changed for better performance with huge amount of products
	*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* @param integer $id_lang Language id
	* @return array Special
	*/
	public static function getRandomSpecial($id_lang, $beginning = false, $ending = false, Context $context = null)
	{
		if (!$context)
			$context = Context::getContext();

		$front = true;
		if (!in_array($context->controller->controller_type, array('front', 'modulefront')))
			$front = false;

		$current_date = date('Y-m-d H:i:s');
		$product_reductions = Product::_getProductIdByDate((!$beginning ? $current_date : $beginning), (!$ending ? $current_date : $ending), $context, true);

		if ($product_reductions)
		{
			
			$ids_product = ' AND product_shop.`id_product` IN (';
			$nb_products_query = count($product_reductions);
			$index_product = 1;
			foreach ($product_reductions as $product_reduction){
				$ids_product .= ''.(int)$product_reduction['id_product'];
				$index_product++;
				if($index_product < $nb_products_query)
					$ids_product .= ', ';
			}
			$ids_product .= ')';
			

			$groups = FrontController::getCurrentCustomerGroups();
			$sql_groups = (count($groups) ? 'IN ('.implode(',', $groups).')' : '= 1');

			// Please keep 2 distinct queries because RAND() is an awful way to achieve this result
			$sql = 'SELECT product_shop.id_product, MAX(product_attribute_shop.id_product_attribute) id_product_attribute
					FROM `'._DB_PREFIX_.'product` p
					'.Shop::addSqlAssociation('product', 'p').'
					LEFT JOIN  `'._DB_PREFIX_.'product_attribute` pa ON (product_shop.id_product = pa.id_product)
					'.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.default_on = 1').'
					WHERE product_shop.`active` = 1
						'.(($ids_product) ? $ids_product : '').'
						AND p.`id_product` IN (
							SELECT cp.`id_product`
							FROM `'._DB_PREFIX_.'category_group` cg
							LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_category` = cg.`id_category`)
							WHERE cg.`id_group` '.$sql_groups.'
						)
					'.($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '').'
					GROUP BY product_shop.id_product
					ORDER BY RAND()';

			$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

			if (!$id_product = $result['id_product'])
				return false;

			$sql = 'SELECT p.*, product_shop.*, stock.`out_of_stock` out_of_stock, pl.`description`, pl.`description_short`,
						pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`,
						p.`ean13`, p.`upc`, MAX(image_shop.`id_image`) id_image, il.`legend`,
						DATEDIFF(product_shop.`date_add`, DATE_SUB(NOW(),
						INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).'
							DAY)) > 0 AS new
					FROM `'._DB_PREFIX_.'product` p
					LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (
						p.`id_product` = pl.`id_product`
						AND pl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('pl').'
					)
					'.Shop::addSqlAssociation('product', 'p').'
					LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = p.`id_product`)'.
					Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1').'
					LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$id_lang.')
					'.Product::sqlStock('p', 0).'
					WHERE p.id_product = '.(int)$id_product.'
					GROUP BY product_shop.id_product';

			$row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
			if (!$row)
				return false;

			if ($result['id_product_attribute'])
				$row['id_product_attribute'] = $result['id_product_attribute'];
			return Product::getProductProperties($id_lang, $row);
		}
		else
			return false;
	}
	
}
