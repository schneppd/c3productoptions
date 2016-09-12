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

class CartController extends CartControllerCore
{
	//c3 contains all txt inputs
	
	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
protected  $c3_combination_data;

	/**
	 * Initialize cart controller
	 * @see FrontController::init()
	 */

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	public function init()
	{
		parent::init();
		//c3 get options selection if exists
		$this->c3_combination_data = Tools::getValue('data-c3-combination_data','');
	}


	/**
	 * This process add or update a product in the cart
	 */

	/*
	* module: c3productoptions
	* date: 2016-06-22 01:38:57
	* version: 1.0.0
	*/
	protected function processChangeProductInCart()
	{
		$mode = (Tools::getIsset('update') && $this->id_product) ? 'update' : 'add';

		if ($this->qty == 0)
			$this->errors[] = Tools::displayError('Null quantity.', !Tools::getValue('ajax'));
		elseif (!$this->id_product)
			$this->errors[] = Tools::displayError('Product not found', !Tools::getValue('ajax'));

		$product = new Product($this->id_product, true, $this->context->language->id);
		if (!$product->id || !$product->active)
		{
			$this->errors[] = Tools::displayError('This product is no longer available.', !Tools::getValue('ajax'));
			return;
		}

		$qty_to_check = $this->qty;
		$cart_products = $this->context->cart->getProducts();//c3-todo

		if (is_array($cart_products))//c3-todo
			foreach ($cart_products as $cart_product)
			{
				if (isset($this->id_product) && $cart_product['id_product'] == $this->id_product)
				{
					$qty_to_check = $cart_product['cart_quantity'];

					if (Tools::getValue('op', 'up') == 'down')
						$qty_to_check -= $this->qty;
					else
						$qty_to_check += $this->qty;

					break;
				}
			}

		// Check product quantity availability
                //p($product);
		if (!$product->checkQty($qty_to_check))
			$this->errors[] = Tools::displayError('There isn\'t enough product in stock.', !Tools::getValue('ajax'));
		
		// If no errors, process product addition
		if (!$this->errors && $mode == 'add')
		{
			// Add cart if no cart found
			if (!$this->context->cart->id)
			{
				if (Context::getContext()->cookie->id_guest)
				{
					$guest = new Guest(Context::getContext()->cookie->id_guest);
					$this->context->cart->mobile_theme = $guest->mobile_theme;
				}
				$this->context->cart->add();
				if ($this->context->cart->id)
					$this->context->cookie->id_cart = (int)$this->context->cart->id;
			}

			// Check customizable fields
			if ((!$product->hasAllRequiredCustomizableFields() || !$product->c3HasAllRequiredCustomizableFields($this->id_product, $this->c3_combination_data)) && !$this->customization_id)
				$this->errors[] = Tools::displayError('Please fill in all of the required fields, and then save your customizations.', !Tools::getValue('ajax'));

			if (!$this->errors)
			{
				$cart_rules = $this->context->cart->getCartRules();
				//c3 create or update order + save text inputs
				$update_quantity = $this->context->cart->updateC3Qty($this->qty, $this->id_product, $this->id_product_attribute, $this->customization_id, Tools::getValue('op', 'up'), $this->id_address_delivery, null, null, $this->c3_combination_data);
				if ($update_quantity < 0)
				{
					// If product has attribute, minimal quantity is set with minimal quantity of attribute
					$minimal_quantity = ($this->id_product_attribute) ? Attribute::getAttributeMinimalQty($this->id_product_attribute) : $product->minimal_quantity;
					$this->errors[] = sprintf(Tools::displayError('You must add %d minimum quantity', !Tools::getValue('ajax')), $minimal_quantity);
				}
				elseif (!$update_quantity)
					$this->errors[] = Tools::displayError('You already have the maximum quantity available for this product.', !Tools::getValue('ajax'));
				elseif ((int)Tools::getValue('allow_refresh'))
				{
					// If the cart rules has changed, we need to refresh the whole cart
					$cart_rules2 = $this->context->cart->getCartRules();
					if (count($cart_rules2) != count($cart_rules))
						$this->ajax_refresh = true;
					else
					{
						$rule_list = array();
						foreach ($cart_rules2 as $rule)
							$rule_list[] = $rule['id_cart_rule'];
						foreach ($cart_rules as $rule)
							if (!in_array($rule['id_cart_rule'], $rule_list))
							{
								$this->ajax_refresh = true;
								break;
							}
					}
				}
			}
		}
	
		$removed = CartRule::autoRemoveFromCart();
		CartRule::autoAddToCart();
		if (count($removed) && (int)Tools::getValue('allow_refresh'))
			$this->ajax_refresh = true;
	}

}
