<?php
/*
* fix bug in productadmin panel when too much attribute problem
* @since 2016/11/01
*/

class Attribute extends AttributeCore
{

	/**
	 * Get all attributes for a given language
	 * remove attributes when number of attributes per attribute_group is more than 300
	 * @author Schnepp David <david.schnepp@schneppd.com>
	 * @since 2016/11/01
	 *
	 * @param integer $id_lang Language id
	 * @param boolean $notNull Get only not null fields if true
	 * @return array Attributes
	 */
	public static function getAttributes($id_lang, $not_null = false)
	{
		if (!Combination::isFeatureActive())
			return array();

		return Db::getInstance()->executeS('
			SELECT DISTINCT ag.*, agl.*, a.`id_attribute`, al.`name`, agl.`name` AS `attribute_group`
			FROM `'._DB_PREFIX_.'attribute_group` ag
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
				ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int)$id_lang.')
			LEFT JOIN `'._DB_PREFIX_.'attribute` a
				ON a.`id_attribute_group` = ag.`id_attribute_group`
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al
				ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int)$id_lang.')
			'.Shop::addSqlAssociation('attribute_group', 'ag').'
			'.Shop::addSqlAssociation('attribute', 'a').'
			'.($not_null ? 'WHERE a.`id_attribute` IS NOT NULL AND al.`name` IS NOT NULL AND agl.`id_attribute_group` IS NOT NULL' : '').'
			 AND ag.`id_attribute_group` NOT IN (SELECT a2.`id_attribute_group` FROM (SELECT  a3.`id_attribute_group`, COUNT(a3.`id_attribute_group`) AS nb_attributes FROM  `'._DB_PREFIX_.'attribute` AS a3 GROUP BY ag3.`id_attribute_group`) AS a2 WHERE  ag2.nb_attributes > 300) 
			ORDER BY agl.`name` ASC, a.`position` ASC
		');
	}

}
