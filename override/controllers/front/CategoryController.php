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

class CategoryController extends CategoryControllerCore
{

	/**
	 * Assign list of products template vars
	 */
	public function assignProductList()
	{
		$hookExecuted = false;
		Hook::exec('actionProductListOverride', array(
			'nbProducts' => &$this->nbProducts,
			'catProducts' => &$this->cat_products,
			'hookExecuted' => &$hookExecuted,
		));

		// The hook was not executed, standard working
		if (!$hookExecuted)
		{
			$this->context->smarty->assign('categoryNameComplement', '');
			$this->nbProducts = $this->category->getProducts(null, null, null, $this->orderBy, $this->orderWay, true);
			$this->pagination((int)$this->nbProducts); // Pagination must be call after "getProducts"
			$this->cat_products = $this->category->getProducts($this->context->language->id, (int)$this->p, (int)$this->n, $this->orderBy, $this->orderWay);
		}
		// Hook executed, use the override
		else
			// Pagination must be call after "getProducts"
			$this->pagination($this->nbProducts);

		Hook::exec('actionProductListModifier', array(
			'nb_products' => &$this->nbProducts,
			'cat_products' => &$this->cat_products,
		));

		foreach ($this->cat_products as &$product)
			if ($product['id_product_attribute'] && isset($product['product_attribute_minimal_quantity']))
				$product['minimal_quantity'] = $product['product_attribute_minimal_quantity'];

		$this->addColorsToProductList($this->cat_products);

		$this->context->smarty->assign('nb_products', $this->nbProducts);
		
		//c3_option, add min price when product has options
		foreach ($this->cat_products as &$row){
			$id_product = (int)$row['id_product'];
			if(Product::c3HasOptions($id_product)){
				$row['price'] = (float)$row['price'] + Product::c3GetProductMinOptionsPrice($id_product);
				$row['price_tax_exc'] = (float)$row['price_tax_exc'] + Product::c3GetProductMinOptionsPrice($id_product);
			}

		}
	}


}

