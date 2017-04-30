CREATE TABLE IF NOT EXISTS `PREFIX_c3_product_option` (
 id_product int(10) unsigned NOT NULL
 ,id_attribute_group int(10) unsigned NOT NULL
 ,required_option BOOLEAN DEFAULT false
 ,position TINYINT unsigned DEFAULT 0
 ,PRIMARY KEY (id_product,id_attribute_group)
 ,CONSTRAINT FOREIGN KEY (id_product) REFERENCES PREFIX_product (id_product) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_attribute_group) REFERENCES PREFIX_attribute_group (id_attribute_group) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `PREFIX_c3_product_option_value` (
 id_product int(10) unsigned NOT NULL
 ,id_attribute_group int(10) unsigned NOT NULL
 ,id_attribute int(10) unsigned NOT NULL
 ,available_option BOOLEAN DEFAULT false
 ,price decimal(20,6) DEFAULT 0.0
 ,PRIMARY KEY (id_product,id_attribute_group,id_attribute)
 ,CONSTRAINT FOREIGN KEY (id_product) REFERENCES PREFIX_product (id_product) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_attribute_group) REFERENCES PREFIX_attribute_group (id_attribute_group) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_attribute) REFERENCES PREFIX_attribute (id_attribute) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_product,id_attribute_group) REFERENCES PREFIX_c3_product_option (id_product,id_attribute_group) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `PREFIX_c3_cart_product_customization` (
 id_customization int(10) unsigned NOT NULL AUTO_INCREMENT
 ,id_cart int(10) unsigned NOT NULL
 ,id_product int(10) unsigned NOT NULL
 ,id_shop int(10) unsigned NOT NULL DEFAULT 1
 ,selection_value TEXT NOT NULL
 ,date_add datetime NOT NULL DEFAULT NOW()
 ,PRIMARY KEY (id_customization)
 ,CONSTRAINT FOREIGN KEY (id_cart) REFERENCES PREFIX_cart (id_cart) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_product) REFERENCES PREFIX_product (id_product) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_shop) REFERENCES PREFIX_shop (id_shop) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_option` AS SELECT
 cpo.`id_product` AS id_product, cpo.`id_attribute_group` AS id_attribute_group, cpo.`required_option` AS required_option
 ,ag.`group_type` AS group_type
 ,agl.`id_lang` AS id_lang, agl.`public_name` AS public_name
 FROM `PREFIX_c3_product_option` AS cpo
 INNER JOIN `PREFIX_attribute_group` AS ag ON (ag.id_attribute_group = cpo.id_attribute_group)
 INNER JOIN `PREFIX_attribute_group_lang` AS agl ON (agl.id_attribute_group = cpo.id_attribute_group)
 ORDER BY id_lang, id_product, cpo.`position`, id_attribute_group
;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_option_value` AS SELECT
 cpov.`id_product` AS id_product, cpov.`id_attribute_group` AS id_attribute_group, cpov.`id_attribute` AS id_attribute, cpov.`price` AS price
 ,al.`id_lang` AS id_lang, al.`name` AS name
 FROM `PREFIX_c3_product_option_value` AS cpov
 INNER JOIN `PREFIX_attribute_lang` AS al ON(al.id_attribute = cpov.id_attribute)
 WHERE cpov.available_option = true
 ORDER BY id_lang, id_product, id_attribute_group, id_attribute
;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_with_option_json_data` AS SELECT DISTINCT
 ps.id_product AS id_product
 ,ps.id_shop AS id_shop, ps.price AS price
 FROM
 `PREFIX_product_shop` AS ps 
 INNER JOIN `PREFIX_c3_product_option` AS po ON (po.id_product = ps.id_product)
 ORDER BY id_shop, id_product
;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_without_option_json_data` AS SELECT DISTINCT
 ps.`id_product` AS id_product, ps.`id_shop` AS id_shop, ps.`price` AS price
 FROM 
 `ps_product_shop` AS ps 
 LEFT JOIN `PREFIX_c3_product_option` AS po ON (po.id_product = ps.id_product)
 WHERE
  po.id_product IS NULL
 ORDER BY id_shop, id_product
;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_option_description` AS SELECT DISTINCT
 ag.id_attribute_group AS id_attribute_group, ag.group_type AS group_type
 , agl.id_lang AS id_lang, agl.public_name AS lbl_option
 , a.id_attribute AS id_attribute, al.name AS lbl_option_value
 FROM 
 `PREFIX_attribute_group` AS ag 
 INNER JOIN `PREFIX_attribute_group_lang` AS agl ON (agl.id_attribute_group = ag.id_attribute_group)
 INNER JOIN `PREFIX_attribute` AS a ON (a.id_attribute_group = ag.id_attribute_group)
 INNER JOIN `PREFIX_attribute_lang` AS al ON (al.id_attribute = a.id_attribute)
 ORDER BY id_attribute_group, id_attribute
;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_min_option_prices` AS SELECT 
 pov.id_product AS id_product, pov.id_attribute_group AS id_attribute_group, MIN(pov.price) AS price
 FROM 
 PREFIX_c3_product_option_value AS pov
 INNER JOIN PREFIX_product_shop AS ps ON (ps.id_product = pov.id_product)
 WHERE ((ps.price > 0 AND pov.price = 0) OR (ps.price = 0 AND pov.price > 0))
 GROUP BY id_product, pov.id_attribute_group
 ;
CREATE OR REPLACE VIEW `PREFIX_vc3_product_min_option_price` AS SELECT 
 dt.id_product AS id_product, SUM(dt.price) AS price
 FROM PREFIX_vc3_product_min_option_prices AS dt
 GROUP BY dt.id_product
 ;
