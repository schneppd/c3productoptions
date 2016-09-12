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

class Cart extends CartCore
{

	/**
	 * Update product quantity and save customization text value
	 *
	 * @param integer $quantity Quantity to add (or substract)
	 * @param integer $id_product Product ID
	 * @param integer $id_product_attribute Attribute ID if needed
	 * @param string $operator Indicate if quantity must be increased or decreased
	 */

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function updateC3Qty($quantity, $id_product, $id_product_attribute = null, $id_customization = false,
		$operator = 'up', $id_address_delivery = 0, Shop $shop = null, $auto_add_cart_rule = true, $txtCustomInputs = "")
	{
		$res = CartCore::updateQty($quantity, $id_product, $id_product_attribute, $id_customization, $operator, $id_address_delivery, $shop, $auto_add_cart_rule);
		//if order normally added
		if($res > 0)
		{
			$id_shop = (int)Context::getContext()->shop->id;
			$id_product = (int)$id_product;
			$id_cart = (int)$this->id;
			//if it's an update operation
			if($operator == 'up')
			{
				if($txtCustomInputs != ""){//product has customization, save each inputs
					for($i = 0; $i < (int)$quantity; $i++){
						Db::getInstance()->insert('c3_cart_product_customization', array(
							'id_product' =>  $id_product,
							'id_cart' => $id_cart,
							'id_shop' => $id_shop,
							'selection_value' => $txtCustomInputs
						));
					}
				}
				else if(Product::c3HasOptions($id_product)){//add same as old
					for($i = 0; $i < (int)$quantity; $i++){
						$sql = 'INSERT INTO '._DB_PREFIX_.'c3_cart_product_customization(id_product,id_cart,id_shop,selection_value) (SELECT cpc.id_product,cpc.id_cart,cpc.id_shop,cpc.selection_value FROM '._DB_PREFIX_.'c3_cart_product_customization AS cpc WHERE cpc.id_product = '.$id_product.' AND cpc.id_cart = '.$id_cart.' AND cpc.id_shop = '.$id_shop.' ORDER BY cpc.date_add DESC LIMIT 1)';
						$result_add = Db::getInstance()->execute($sql);
						if (!$result_add)
							return false;
					}
				}


			}
			//if it's an delete operation
			elseif($operator == 'down')
			{
				if($txtCustomInputs != ""){//product has customization, save each inputs
					for($i = 0; $i < (int)$quantity; $i++){
						$result_remove = Db::getInstance()->delete('c3_cart_product_customization'
						, 'id_customization  = (SELECT * FROM (SELECT MAX(cpc.id_customization) FROM '._DB_PREFIX_.'c3_cart_product_customization AS cpc WHERE cpc.id_product = '.$id_product.' AND cpc.id_cart = '.$id_cart.' AND cpc.id_shop = '.$id_shop.' AND cpc.selection_value = '.pSQL($txtCustomInputs).') AS cpc)');
						if (!$result_remove)
							return false;
					}		

				}
				else if(Product::c3HasOptions($id_product)){//add same as old
					for($i = 0; $i < (int)$quantity; $i++){
						$result_remove = Db::getInstance()->delete('c3_cart_product_customization'
						, 'id_customization  = (SELECT * FROM (SELECT MAX(cpc.id_customization) FROM '._DB_PREFIX_.'c3_cart_product_customization AS cpc WHERE cpc.id_product = '.$id_product.' AND cpc.id_cart = '.$id_cart.' AND cpc.id_shop = '.$id_shop.') AS cpc)');
						if (!$result_remove)
							return false;
					}
				}
		
			}
		}
		return $res;
	}

	//dead code check and trim

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function c3ContainsProduct($id_product, $id_address_delivery = 0)
	{
		$sql = new DbQuery();
		
		$sql->select('cp.quantity');
		$sql->from('cart_product', 'cp');
		$sql->innerJoin('c3_cart_product', 'ccp', 'ccp.id_cart = cp.id_product AND ccp.id_cart = cp.id_cart');
		$sql->where('cp.id_product = '.(int)$id_product);
		$sql->where('cp.id_cart = '.(int)$this->id);

		if (Configuration::get('PS_ALLOW_MULTISHIPPING') && $this->isMultiAddressDelivery())
			$sql->where('cp.id_address_delivery = '.(int)$id_address_delivery);

		return Db::getInstance()->getRow($sql);
	}

	/**
	 * Return cart products
	 *
	 * @result array Products
	 */

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function getProducts($refresh = false, $id_product = false, $id_country = null)
	{
		if (!$this->id)
			return array();
		// Product cache must be strictly compared to NULL, or else an empty cart will add dozens of queries
		if ($this->_products !== null && !$refresh)
		{
			// Return product row with specified ID if it exists
			if (is_int($id_product))
			{
				foreach ($this->_products as $product)
					if ($product['id_product'] == $id_product)
						return array($product);
				return array();
			}
			return $this->_products;
		}

		// Build query
		$sql = new DbQuery();

		// Build SELECT
		$sql->select('cp.`id_product_attribute`, cp.`id_product`, cp.`quantity` AS cart_quantity, cp.id_shop, pl.`name`, p.`is_virtual`,
						pl.`description_short`, pl.`available_now`, pl.`available_later`, product_shop.`id_category_default`, p.`id_supplier`,
						p.`id_manufacturer`, product_shop.`on_sale`, product_shop.`ecotax`, product_shop.`additional_shipping_cost`,
						product_shop.`available_for_order`, product_shop.`price`, product_shop.`active`, product_shop.`unity`, product_shop.`unit_price_ratio`,
						stock.`quantity` AS quantity_available, p.`width`, p.`height`, p.`depth`, stock.`out_of_stock`, p.`weight`,
						p.`date_add`, p.`date_upd`, IFNULL(stock.quantity, 0) as quantity, pl.`link_rewrite`, cl.`link_rewrite` AS category,
						CONCAT(LPAD(cp.`id_product`, 10, 0), LPAD(IFNULL(cp.`id_product_attribute`, 0), 10, 0), IFNULL(cp.`id_address_delivery`, 0)) AS unique_id, cp.id_address_delivery,
						product_shop.advanced_stock_management, ps.product_supplier_reference supplier_reference, IFNULL(sp.`reduction_type`, 0) AS reduction_type');

		// Build FROM
		$sql->from('cart_product', 'cp');

		// Build JOIN
		$sql->leftJoin('product', 'p', 'p.`id_product` = cp.`id_product`');
		$sql->innerJoin('product_shop', 'product_shop', '(product_shop.`id_shop` = cp.`id_shop` AND product_shop.`id_product` = p.`id_product`)');
		$sql->leftJoin('product_lang', 'pl', '
			p.`id_product` = pl.`id_product`
			AND pl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('pl', 'cp.id_shop')
		);

		$sql->leftJoin('category_lang', 'cl', '
			product_shop.`id_category_default` = cl.`id_category`
			AND cl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('cl', 'cp.id_shop')
		);

		$sql->leftJoin('product_supplier', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_product_attribute` = cp.`id_product_attribute` AND ps.`id_supplier` = p.`id_supplier`');

		$sql->leftJoin('specific_price', 'sp', 'sp.`id_product` = cp.`id_product`'); // AND 'sp.`id_shop` = cp.`id_shop`

		// @todo test if everything is ok, then refactorise call of this method
		$sql->join(Product::sqlStock('cp', 'cp'));

		// Build WHERE clauses
		$sql->where('cp.`id_cart` = '.(int)$this->id);
		if ($id_product)
			$sql->where('cp.`id_product` = '.(int)$id_product);
		$sql->where('p.`id_product` IS NOT NULL');

		// Build GROUP BY
		$sql->groupBy('unique_id');

		// Build ORDER BY
		$sql->orderBy('cp.`date_add`, p.`id_product`, cp.`id_product_attribute` ASC');

		if (Customization::isFeatureActive())
		{
			$sql->select('cu.`id_customization`, cu.`quantity` AS customization_quantity');
			$sql->leftJoin('customization', 'cu',
				'p.`id_product` = cu.`id_product` AND cp.`id_product_attribute` = cu.`id_product_attribute` AND cu.`id_cart` = '.(int)$this->id);
		}
		else
			$sql->select('NULL AS customization_quantity, NULL AS id_customization');

		if (Combination::isFeatureActive())
		{
			$sql->select('
				product_attribute_shop.`price` AS price_attribute, product_attribute_shop.`ecotax` AS ecotax_attr,
				IF (IFNULL(pa.`reference`, \'\') = \'\', p.`reference`, pa.`reference`) AS reference,
				(p.`weight`+ pa.`weight`) weight_attribute,
				IF (IFNULL(pa.`ean13`, \'\') = \'\', p.`ean13`, pa.`ean13`) AS ean13,
				IF (IFNULL(pa.`upc`, \'\') = \'\', p.`upc`, pa.`upc`) AS upc,
				pai.`id_image` as pai_id_image, il.`legend` as pai_legend,
				IFNULL(product_attribute_shop.`minimal_quantity`, product_shop.`minimal_quantity`) as minimal_quantity,
				IF(product_attribute_shop.wholesale_price > 0,  product_attribute_shop.wholesale_price, product_shop.`wholesale_price`) wholesale_price
			');

			$sql->leftJoin('product_attribute', 'pa', 'pa.`id_product_attribute` = cp.`id_product_attribute`');
			$sql->leftJoin('product_attribute_shop', 'product_attribute_shop', '(product_attribute_shop.`id_shop` = cp.`id_shop` AND product_attribute_shop.`id_product_attribute` = pa.`id_product_attribute`)');
			$sql->leftJoin('product_attribute_image', 'pai', 'pai.`id_product_attribute` = pa.`id_product_attribute`');
			$sql->leftJoin('image_lang', 'il', 'il.`id_image` = pai.`id_image` AND il.`id_lang` = '.(int)$this->id_lang);
		}
		else
			$sql->select(
				'p.`reference` AS reference, p.`ean13`,
				p.`upc` AS upc, product_shop.`minimal_quantity` AS minimal_quantity, product_shop.`wholesale_price` wholesale_price'
			);
		$result = Db::getInstance()->executeS($sql);

		// Reset the cache before the following return, or else an empty cart will add dozens of queries
		$products_ids = array();
		$pa_ids = array();
		if ($result)
			foreach ($result as $row)
			{
				$products_ids[] = $row['id_product'];
				$pa_ids[] = $row['id_product_attribute'];
			}
		// Thus you can avoid one query per product, because there will be only one query for all the products of the cart
		Product::cacheProductsFeatures($products_ids);
		Cart::cacheSomeAttributesLists($pa_ids, $this->id_lang);

		$this->_products = array();
		if (empty($result))
			return array();

		$cart_shop_context = Context::getContext()->cloneContext();
		foreach ($result as &$row)
		{
			if (isset($row['ecotax_attr']) && $row['ecotax_attr'] > 0)
				$row['ecotax'] = (float)$row['ecotax_attr'];

			$row['stock_quantity'] = (int)$row['quantity'];
			// for compatibility with 1.2 themes
			$row['quantity'] = (int)$row['cart_quantity'];

			if (isset($row['id_product_attribute']) && (int)$row['id_product_attribute'] && isset($row['weight_attribute']))
				$row['weight'] = (float)$row['weight_attribute'];

			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$address_id = (int)$this->id_address_invoice;
			else
				$address_id = (int)$row['id_address_delivery'];
			if (!Address::addressExists($address_id))
				$address_id = null;

			if ($cart_shop_context->shop->id != $row['id_shop'])
				$cart_shop_context->shop = new Shop((int)$row['id_shop']);

			$address = Address::initialize($address_id, true);
			$id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int)$row['id_product'], $cart_shop_context);
			$tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

			//$row['price'] = Product::getPriceStatic(
			$c3_product_price_infos = Product::c3getProductInCartPrice(
				(int)$row['id_product'],
				false,
				isset($row['id_product_attribute']) ? (int)$row['id_product_attribute'] : null,
				6,
				null,
				false,
				true,
				$row['cart_quantity'],
				false,
				(int)$this->id_customer ? (int)$this->id_customer : null,
				(int)$this->id,
				$address_id,
				$specific_price_output,
				false,
				true,
				$cart_shop_context
			);
			$price_product_all_options_without_tax = 0.0;
			$price_single_product_without_tax = 0.0;
			//add options prices to final price
			if(count($c3_product_price_infos['options']) == 0){
				$price_product_all_options_without_tax += (float)$c3_product_price_infos['basic_price'] * $row['quantity'];
				$price_single_product_without_tax = (float)$c3_product_price_infos['basic_price'];
			}
			else
				foreach($c3_product_price_infos['options'] as $key => $option){
					$price_product_all_options_without_tax += (float)$c3_product_price_infos['basic_price'] + (float)$option;
					if($key == 0)
						$price_single_product_without_tax += (float)$c3_product_price_infos['basic_price'] + (float)$option;
				}

			$price_single_product_with_tax = $tax_calculator->addTaxes($price_single_product_without_tax);
			$price_product_all_options_with_tax = $tax_calculator->addTaxes($price_product_all_options_without_tax);

			$row['price'] = $price_single_product_with_tax;
			//$row['price'] = $tax_calculator->addTaxes($price_wt);
			switch (Configuration::get('PS_ROUND_TYPE'))
			{
				case Order::ROUND_TOTAL:
				case Order::ROUND_LINE:
					$row['total'] = Tools::ps_round($price_product_all_options_without_tax, _PS_PRICE_COMPUTE_PRECISION_);
					$row['total_wt'] = Tools::ps_round($price_product_all_options_with_tax, _PS_PRICE_COMPUTE_PRECISION_);
					break;

				case Order::ROUND_ITEM:
				default:
					$row['total'] = Tools::ps_round($price_product_all_options_without_tax, _PS_PRICE_COMPUTE_PRECISION_);
					$row['total_wt'] = Tools::ps_round($price_product_all_options_with_tax, _PS_PRICE_COMPUTE_PRECISION_);
					break;
			}
			$row['price_wt'] = $price_single_product_with_tax;
			$row['description_short'] = Tools::nl2br($row['description_short']);

			if (!isset($row['pai_id_image']) || $row['pai_id_image'] == 0)
			{
				$cache_id = 'Cart::getProducts_'.'-pai_id_image-'.(int)$row['id_product'].'-'.(int)$this->id_lang.'-'.(int)$row['id_shop'];
				if (!Cache::isStored($cache_id))
				{
					$row2 = Db::getInstance()->getRow('
						SELECT image_shop.`id_image` id_image, il.`legend`
						FROM `'._DB_PREFIX_.'image` i
						JOIN `'._DB_PREFIX_.'image_shop` image_shop ON (i.id_image = image_shop.id_image AND image_shop.cover=1 AND image_shop.id_shop='.(int)$row['id_shop'].')
						LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$this->id_lang.')
						WHERE i.`id_product` = '.(int)$row['id_product'].' AND image_shop.`cover` = 1'
					);
					Cache::store($cache_id, $row2);
				}
				$row2 = Cache::retrieve($cache_id);
				if (!$row2)
					$row2 = array('id_image' => false, 'legend' => false);
				else
					$row = array_merge($row, $row2);
			}
			else
			{
				$row['id_image'] = $row['pai_id_image'];
				$row['legend'] = $row['pai_legend'];
			}

			$row['reduction_applies'] = ($specific_price_output && (float)$specific_price_output['reduction']);
			$row['quantity_discount_applies'] = ($specific_price_output && $row['cart_quantity'] >= (int)$specific_price_output['from_quantity']);
			$row['id_image'] = Product::defineProductImage($row, $this->id_lang);
			$row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
			$row['features'] = Product::getFeaturesStatic((int)$row['id_product']);

			if (array_key_exists($row['id_product_attribute'].'-'.$this->id_lang, self::$_attributesLists))
				$row = array_merge($row, self::$_attributesLists[$row['id_product_attribute'].'-'.$this->id_lang]);
			if(Product::c3HasOptions((int)$row['id_product'])){
				$product_options_descs = Product::c3GetProductOptionsDescription((int)$this->id, (int)$row['id_product'], $cart_shop_context->shop->id);
				$row['attributes'] = $product_options_descs['full-description'];
				$row['attributes_small'] = $product_options_descs['short-description'];
			}
			//debug 
			//$row['attributes'] = '$price_product_all_options_without_tax: '.$price_product_all_options_without_tax.' $price_single_product_without_tax: '.$price_single_product_without_tax.' $price_single_product_with_tax:'.$price_single_product_with_tax.' $price_product_all_options_with_tax: '.$price_product_all_options_with_tax.' $row[total]: '.$row['total'].' $row[total_wt]: '.$row['total_wt'].' $row[price_without_specific_price]: '.$row['price_without_specific_price'];

			$row = Product::getTaxesInformations($row, $cart_shop_context);
			$this->_products[] = $row;
		}

		return $this->_products;
	}

	/**
	* This function returns the total cart amount
	*
	* Possible values for $type:
	* Cart::ONLY_PRODUCTS
	* Cart::ONLY_DISCOUNTS
	* Cart::BOTH
	* Cart::BOTH_WITHOUT_SHIPPING
	* Cart::ONLY_SHIPPING
	* Cart::ONLY_WRAPPING
	* Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING
	* Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING
	*
	* @param boolean $withTaxes With or without taxes
	* @param integer $type Total type
	* @param boolean $use_cache Allow using cache of the method CartRule::getContextualValue
	* @return float Order total
	*/

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function getOrderTotal($with_taxes = true, $type = Cart::BOTH, $products = null, $id_carrier = null, $use_cache = true)
	{
		static $address = null;

		if (!$this->id)
			return 0;

		$type = (int)$type;
		$array_type = array(
			Cart::ONLY_PRODUCTS,
			Cart::ONLY_DISCOUNTS,
			Cart::BOTH,
			Cart::BOTH_WITHOUT_SHIPPING,
			Cart::ONLY_SHIPPING,
			Cart::ONLY_WRAPPING,
			Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING,
			Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
		);

		// Define virtual context to prevent case where the cart is not the in the global context
		$virtual_context = Context::getContext()->cloneContext();
		$virtual_context->cart = $this;

		if (!in_array($type, $array_type))
			die(Tools::displayError());

		$with_shipping = in_array($type, array(Cart::BOTH, Cart::ONLY_SHIPPING));

		// if cart rules are not used
		if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive())
			return 0;

		// no shipping cost if is a cart with only virtuals products
		$virtual = $this->isVirtualCart();
		if ($virtual && $type == Cart::ONLY_SHIPPING)
			return 0;

		if ($virtual && $type == Cart::BOTH)
			$type = Cart::BOTH_WITHOUT_SHIPPING;

		if ($with_shipping || $type == Cart::ONLY_DISCOUNTS)
		{
			if (is_null($products) && is_null($id_carrier))
				$shipping_fees = $this->getTotalShippingCost(null, (boolean)$with_taxes);
			else
				$shipping_fees = $this->getPackageShippingCost($id_carrier, (bool)$with_taxes, null, $products);
		}
		else
			$shipping_fees = 0;

		if ($type == Cart::ONLY_SHIPPING)
			return $shipping_fees;

		if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING)
			$type = Cart::ONLY_PRODUCTS;

		$param_product = true;
		if (is_null($products))
		{
			$param_product = false;
			$products = $this->getProducts();
		}

		if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING)
		{
			foreach ($products as $key => $product)
				if ($product['is_virtual'])
					unset($products[$key]);
			$type = Cart::ONLY_PRODUCTS;
		}

		$order_total = 0;
		if (Tax::excludeTaxeOption())
			$with_taxes = false;

		$products_total = array();
		$ecotax_total = 0;

		foreach ($products as $product) // products refer to the cart details
		{
			if ($virtual_context->shop->id != $product['id_shop'])
				$virtual_context->shop = new Shop((int)$product['id_shop']);

			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$id_address = (int)$this->id_address_invoice;
			else
				$id_address = (int)$product['id_address_delivery']; // Get delivery address of the product from the cart
			if (!Address::addressExists($id_address))
				$id_address = null;

			//c3 new way to calculate price
			$c3_product_price_infos = Product::c3getProductInCartPrice(
				(int)$product['id_product'],
				false,
				(int)$product['id_product_attribute'],
				6,
				null,
				false,
				true,
				$product['cart_quantity'],
				false,
				(int)$this->id_customer ? (int)$this->id_customer : null,
				(int)$this->id,
				$id_address,
				$null,
				false,
				true,
				$virtual_context
			);
			$price_product_all_options_without_tax = 0.0;
			$price_single_product_without_tax = 0.0;
			if(count($c3_product_price_infos['options']) == 0){
				$price_product_all_options_without_tax += (float)$c3_product_price_infos['basic_price'] * (int)$product['cart_quantity'];
				$price_single_product_without_tax = (float)$c3_product_price_infos['basic_price'];
			}
			else{
				foreach($c3_product_price_infos['options'] as $key => $option)
					$price_product_all_options_without_tax += (float)$c3_product_price_infos['basic_price'] + (float)$option;
					if($key == 0)
						$price_single_product_without_tax += (float)$c3_product_price_infos['basic_price'] + (float)$option;
			}

			//$product['unit_price_tax_incl'] = $price_single_product_without_tax;
			$price = $price_single_product_without_tax;

			if (Configuration::get('PS_USE_ECOTAX'))
			{
				$ecotax = $product['ecotax'];
				if (isset($product['attribute_ecotax']) && $product['attribute_ecotax'] > 0)
					$ecotax = $product['attribute_ecotax'];
			}
			else
				$ecotax = 0;

			$address = Address::initialize($id_address, true);

			if ($with_taxes)
			{
				$id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int)$product['id_product'], $virtual_context);
				$tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

				if ($ecotax)
					$ecotax_tax_calculator = TaxManagerFactory::getManager($address, (int)Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID'))->getTaxCalculator();
			}
			else
				$id_tax_rules_group = 0;

			if (in_array(Configuration::get('PS_ROUND_TYPE'), array(Order::ROUND_ITEM, Order::ROUND_LINE)))
			{
				if (!isset($products_total[$id_tax_rules_group]))
					$products_total[$id_tax_rules_group] = 0;
			}
			else
				if (!isset($products_total[$id_tax_rules_group.'_'.$id_address]))
					$products_total[$id_tax_rules_group.'_'.$id_address] = 0;

			switch (Configuration::get('PS_ROUND_TYPE'))
			{
				case Order::ROUND_TOTAL:
					$products_total[$id_tax_rules_group.'_'.$id_address] += $price_product_all_options_without_tax;

					if ($ecotax)
						$ecotax_total += $ecotax * (int)$product['cart_quantity'];
					break;
				case Order::ROUND_LINE:
					$product_price = $price_product_all_options_without_tax; //$price * $product['cart_quantity'];
					$products_total[$id_tax_rules_group] += Tools::ps_round($product_price, _PS_PRICE_COMPUTE_PRECISION_);

					if ($with_taxes)
						$products_total[$id_tax_rules_group] += Tools::ps_round($tax_calculator->getTaxesTotalAmount($product_price), _PS_PRICE_COMPUTE_PRECISION_);

					if ($ecotax)
					{
						$ecotax_price = $ecotax * (int)$product['cart_quantity'];
						$ecotax_total += Tools::ps_round($ecotax_price, _PS_PRICE_COMPUTE_PRECISION_);

						if ($with_taxes)
							$ecotax_total += Tools::ps_round($ecotax_tax_calculator->getTaxesTotalAmount($ecotax_price), _PS_PRICE_COMPUTE_PRECISION_);
					}
					break;
				case Order::ROUND_ITEM:
				default:
					//$product_price = $with_taxes ? $tax_calculator->addTaxes($price) : $price;
					$product_price = $with_taxes ? $tax_calculator->addTaxes($price_product_all_options_without_tax) : $price_product_all_options_without_tax;
					$products_total[$id_tax_rules_group] += Tools::ps_round($product_price, _PS_PRICE_COMPUTE_PRECISION_);

					if ($ecotax)
					{
						$ecotax_price = $with_taxes ? $ecotax_tax_calculator->addTaxes($ecotax) : $ecotax;
						$ecotax_total += Tools::ps_round($ecotax_price, _PS_PRICE_COMPUTE_PRECISION_) * (int)$product['cart_quantity'];
					}
					break;
			}
		}

		foreach ($products_total as $key => $price)
		{
			if ($with_taxes && Configuration::get('PS_ROUND_TYPE') == Order::ROUND_TOTAL)
			{
				$tmp = explode('_', $key);
				$address = Address::initialize((int)$tmp[1], true);
				$tax_calculator = TaxManagerFactory::getManager($address, $tmp[0])->getTaxCalculator();
				$order_total += Tools::ps_round($price, _PS_PRICE_COMPUTE_PRECISION_) + Tools::ps_round($tax_calculator->getTaxesTotalAmount($price), _PS_PRICE_COMPUTE_PRECISION_);
			}
			else
				$order_total += $price;
		}

		if ($ecotax_total && $with_taxes && Configuration::get('PS_ROUND_TYPE') == Order::ROUND_TOTAL)
			$ecotax_total = Tools::ps_round($ecotax_total, _PS_PRICE_COMPUTE_PRECISION_) + Tools::ps_round($ecotax_tax_calculator->getTaxesTotalAmount($ecotax_total), _PS_PRICE_COMPUTE_PRECISION_);

		$order_total += $ecotax_total;
		$order_total_products = $order_total;

		if ($type == Cart::ONLY_DISCOUNTS)
			$order_total = 0;

		// Wrapping Fees
		$wrapping_fees = 0;
		if ($this->gift)
			$wrapping_fees = Tools::convertPrice(Tools::ps_round($this->getGiftWrappingPrice($with_taxes), _PS_PRICE_COMPUTE_PRECISION_), Currency::getCurrencyInstance((int)$this->id_currency));
		if ($type == Cart::ONLY_WRAPPING)
			return $wrapping_fees;

		$order_total_discount = 0;
		if (!in_array($type, array(Cart::ONLY_SHIPPING, Cart::ONLY_PRODUCTS)) && CartRule::isFeatureActive())
		{
			// First, retrieve the cart rules associated to this "getOrderTotal"
			if ($with_shipping || $type == Cart::ONLY_DISCOUNTS)
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_ALL);
			else
			{
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_REDUCTION);
				// Cart Rules array are merged manually in order to avoid doubles
				foreach ($this->getCartRules(CartRule::FILTER_ACTION_GIFT) as $tmp_cart_rule)
				{
					$flag = false;
					foreach ($cart_rules as $cart_rule)
						if ($tmp_cart_rule['id_cart_rule'] == $cart_rule['id_cart_rule'])
							$flag = true;
					if (!$flag)
						$cart_rules[] = $tmp_cart_rule;
				}
			}

			$id_address_delivery = 0;
			if (isset($products[0]))
				$id_address_delivery = (is_null($products) ? $this->id_address_delivery : $products[0]['id_address_delivery']);
			$package = array('id_carrier' => $id_carrier, 'id_address' => $id_address_delivery, 'products' => $products);

			// Then, calculate the contextual value for each one
			foreach ($cart_rules as $cart_rule)
			{
				// If the cart rule offers free shipping, add the shipping cost
				if (($with_shipping || $type == Cart::ONLY_DISCOUNTS) && $cart_rule['obj']->free_shipping)
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_SHIPPING, ($param_product ? $package : null), $use_cache), _PS_PRICE_COMPUTE_PRECISION_);

				// If the cart rule is a free gift, then add the free gift value only if the gift is in this package
				if ((int)$cart_rule['obj']->gift_product)
				{
					$in_order = false;
					if (is_null($products))
						$in_order = true;
					else
						foreach ($products as $product)
							if ($cart_rule['obj']->gift_product == $product['id_product'] && $cart_rule['obj']->gift_product_attribute == $product['id_product_attribute'])
								$in_order = true;

					if ($in_order)
						$order_total_discount += $cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_GIFT, $package, $use_cache);
				}

				// If the cart rule offers a reduction, the amount is prorated (with the products in the package)
				if ($cart_rule['obj']->reduction_percent > 0 || $cart_rule['obj']->reduction_amount > 0)
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_REDUCTION, $package, $use_cache), _PS_PRICE_COMPUTE_PRECISION_);
			}
			$order_total_discount = min(Tools::ps_round($order_total_discount, 2), $wrapping_fees + $order_total_products + $shipping_fees);
			$order_total -= $order_total_discount;
		}

		if ($type == Cart::BOTH)
			$order_total += $shipping_fees + $wrapping_fees;

		if ($order_total < 0 && $type != Cart::ONLY_DISCOUNTS)
			return 0;

		if ($type == Cart::ONLY_DISCOUNTS)
			return $order_total_discount;

		return Tools::ps_round((float)$order_total, _PS_PRICE_COMPUTE_PRECISION_);
	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	protected function correctC3Attributes(&$product_data)
	{

		//c3 correct description of product attribute, add customer txt values
		$attributes_old_value = $product_data['attributes'];
		//new value to display in self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes']
		$attributes_new_value = '';
		//$current attribute part worked on (must support multiple customisation with multiple text values)
		$attributes_part = $attributes_old_value;
		$sql = 'SELECT `date_add`, `txtvalue`, `attribute_name`, `public_group_name`
		 FROM `'._DB_PREFIX_.'vc3_cart_product_customization`
		 WHERE `id_product_attribute` = '.(int)$product_data['id_product_attribute'].'
		 AND `id_shop` = '.(int)$product_data['id_shop'].'
		 AND `id_cart` = '.(int)$this->id.'
		 AND `id_lang` = '.(int)$this->id_lang;

		//get all txt values
		$txtValues = Db::getInstance()->executeS($sql);
		//date_add if != than $row['date_add'] -> new line
		$current_date_group = '';
		$first_row = true;
		$index_option = 1;
		foreach ($txtValues as $row)
		{
			if($first_row)
			{
				$first_row = false;
				$current_date_group = $row['date_add'];
			}
			//if new custom line, save attributes_part to attributes_new_value and reset attributes_part
			if($current_date_group != $row['date_add'] && $current_date_group != '')
			{
				$attributes_new_value .= 'option '.$index_option.' : ['.$attributes_part.'], ';
				$attributes_part = $attributes_old_value;
				$current_date_group = $row['date_add'];
				$index_option ++;
			}
			$old_attribute_desc = $row['public_group_name'].' : '.$row['attribute_name'];
			$new_attribute_desc = $row['public_group_name'].' : '.$row['txtvalue'];
			$attributes_part = str_replace($old_attribute_desc, $new_attribute_desc, $attributes_part);

			
		}
		//close with remaining value
		$attributes_new_value .= 'option '.$index_option.' : ['.$attributes_part.']';
		//store new value
		$product_data['attributes'] = $attributes_new_value;
		$product_data['attributes_small'] = $attributes_part;

	}


	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public static function cacheSomeAttributesLists($ipa_list, $id_lang)
	{
		if (!Combination::isFeatureActive())
			return;

		$pa_implode = array();

		foreach ($ipa_list as $id_product_attribute)
			if ((int)$id_product_attribute && !array_key_exists($id_product_attribute.'-'.$id_lang, self::$_attributesLists))
			{
				$pa_implode[] = (int)$id_product_attribute;
				self::$_attributesLists[(int)$id_product_attribute.'-'.$id_lang] = array('attributes' => '', 'attributes_small' => '', 'c3_special_group_type' => false);
			}

		if (!count($pa_implode))
			return;

		//c3 get if attribute is text
		$result = Db::getInstance()->executeS('
			SELECT pac.`id_product_attribute`, agl.`public_name` AS public_group_name, al.`name` AS attribute_name, ag.group_type AS group_type 
			FROM `'._DB_PREFIX_.'product_attribute_combination` pac
			LEFT JOIN `'._DB_PREFIX_.'attribute` a ON a.`id_attribute` = pac.`id_attribute`
			LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (
				a.`id_attribute` = al.`id_attribute`
				AND al.`id_lang` = '.(int)$id_lang.'
			)
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (
				ag.`id_attribute_group` = agl.`id_attribute_group`
				AND agl.`id_lang` = '.(int)$id_lang.'
			)
			WHERE pac.`id_product_attribute` IN ('.implode(',', $pa_implode).')
			ORDER BY agl.`public_name` ASC'
		);

		$first_row = true;

		foreach ($result as $row)
		{
			if(!$first_row)
			{
				self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes'] .= ', ';
				self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes_small'] .= ', ';
			}

			self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes'] .= $row['public_group_name'].' : '.$row['attribute_name'];
			self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes_small'] .= $row['attribute_name'];
			//if attribute is text type, text value has to be displayed 
			if($row['group_type'] == 'text')
				self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['c3_special_group_type'] = true;
				//$txtValueAttributs[$row['id_product_attribute']] = true;
			if($first_row)
				$first_row = false;
		}

		/*foreach ($pa_implode as $id_product_attribute)
		{
			self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes'] = rtrim(
				self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes'],
				', '
			);

			self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes_small'] = rtrim(
				self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes_small'],
				', '
			);
		}*/
	}

	/**
	 * Delete a product from the cart
	 *
	 * @param integer $id_product Product ID
	 * @param integer $id_product_attribute Attribute ID if needed
	 * @param integer $id_customization Customization id
	 * @return boolean result
	 */
	public function deleteProduct($id_product, $id_product_attribute = null, $id_customization = null, $id_address_delivery = 0)
	{
		if (isset(self::$_nbProducts[$this->id]))
			unset(self::$_nbProducts[$this->id]);

		if (isset(self::$_totalWeight[$this->id]))
			unset(self::$_totalWeight[$this->id]);

		if(Product::c3HasOptions((int)$id_product)){//remove all given options from user
			$result_remove = Db::getInstance()->delete('c3_cart_product_customization'
				, 'id_product  = '.(int)$id_product.' AND id_cart='.(int)$this->id).' AND id_shop='.(int)Context::getContext()->shop->id;
			if (!$result_remove)
				return false;
		}

		if ((int)$id_customization)
		{
			$product_total_quantity = (int)Db::getInstance()->getValue(
				'SELECT `quantity`
				FROM `'._DB_PREFIX_.'cart_product`
				WHERE `id_product` = '.(int)$id_product.'
				AND `id_cart` = '.(int)$this->id.'
				AND `id_product_attribute` = '.(int)$id_product_attribute);

			$customization_quantity = (int)Db::getInstance()->getValue('
			SELECT `quantity`
			FROM `'._DB_PREFIX_.'customization`
			WHERE `id_cart` = '.(int)$this->id.'
			AND `id_product` = '.(int)$id_product.'
			AND `id_product_attribute` = '.(int)$id_product_attribute.'
			'.((int)$id_address_delivery ? 'AND `id_address_delivery` = '.(int)$id_address_delivery : ''));

			if (!$this->_deleteCustomization((int)$id_customization, (int)$id_product, (int)$id_product_attribute, (int)$id_address_delivery))
				return false;

			// refresh cache of self::_products
			$this->_products = $this->getProducts(true);
			return ($customization_quantity == $product_total_quantity && $this->deleteProduct((int)$id_product, (int)$id_product_attribute, null, (int)$id_address_delivery));
		}

		/* Get customization quantity */
		$result = Db::getInstance()->getRow('
			SELECT SUM(`quantity`) AS \'quantity\'
			FROM `'._DB_PREFIX_.'customization`
			WHERE `id_cart` = '.(int)$this->id.'
			AND `id_product` = '.(int)$id_product.'
			AND `id_product_attribute` = '.(int)$id_product_attribute);

		if ($result === false)
			return false;

		/* If the product still possesses customization it does not have to be deleted */
		if (Db::getInstance()->NumRows() && (int)$result['quantity'])
			return Db::getInstance()->execute('
				UPDATE `'._DB_PREFIX_.'cart_product`
				SET `quantity` = '.(int)$result['quantity'].'
				WHERE `id_cart` = '.(int)$this->id.'
				AND `id_product` = '.(int)$id_product.
				($id_product_attribute != null ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '')
			);

		/* Product deletion */
		$result = Db::getInstance()->execute('
		DELETE FROM `'._DB_PREFIX_.'cart_product`
		WHERE `id_product` = '.(int)$id_product.'
		'.(!is_null($id_product_attribute) ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '').'
		AND `id_cart` = '.(int)$this->id.'
		'.((int)$id_address_delivery ? 'AND `id_address_delivery` = '.(int)$id_address_delivery : ''));

		if ($result)
		{
			$return = $this->update();
			// refresh cache of self::_products
			$this->_products = $this->getProducts(true);
			CartRule::autoRemoveFromCart();
			CartRule::autoAddToCart();

			return $return;
		}

		return false;
	}


}
