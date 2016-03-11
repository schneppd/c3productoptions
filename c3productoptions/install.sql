CREATE TABLE IF NOT EXISTS ps_c3_option (
 id_option int(10) unsigned NOT NULL AUTO_INCREMENT,
 option_type enum('select','radio','checkbox','text') NOT NULL DEFAULT 'select',
 name TEXT  NOT NULL,
 PRIMARY KEY (id_option)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_option_lang (
 id_option int(10) unsigned NOT NULL
 ,id_lang int(10) unsigned NOT NULL
 ,public_name TEXT  NOT NULL
 ,PRIMARY KEY (id_option,id_lang)
 ,CONSTRAINT FOREIGN KEY (id_option) REFERENCES ps_c3_option (id_option) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_lang) REFERENCES ps_lang (id_lang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_option_shop (
 id_option int(10) unsigned NOT NULL
 ,id_shop int(10) unsigned NOT NULL
 ,PRIMARY KEY (id_option,id_shop)
 ,CONSTRAINT FOREIGN KEY (id_option) REFERENCES ps_c3_option (id_option) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_shop) REFERENCES ps_shop (id_shop) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



CREATE TABLE IF NOT EXISTS ps_c3_option_value (
 id_option_value int(10) unsigned NOT NULL AUTO_INCREMENT,
 name TEXT  NOT NULL,
 PRIMARY KEY (id_option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_option_value_lang (
 id_option_value int(10) unsigned NOT NULL
 ,id_lang int(10) unsigned NOT NULL
 ,public_name TEXT  NOT NULL
 ,PRIMARY KEY (id_option_value,id_lang)
 ,CONSTRAINT FOREIGN KEY (id_option_value) REFERENCES ps_c3_option_value (id_option_value) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_lang) REFERENCES ps_lang (id_lang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_option_value_shop (
 id_option_value int(10) unsigned NOT NULL
 ,id_shop int(10) unsigned NOT NULL
 ,PRIMARY KEY (id_option_value,id_shop)
 ,CONSTRAINT FOREIGN KEY (id_option_value) REFERENCES ps_c3_option_value (id_option_value) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_shop) REFERENCES ps_shop (id_shop) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_product_option_value (
 id_product int(10) unsigned NOT NULL
 ,id_option int(10) unsigned NOT NULL
 ,id_option_value int(10) unsigned NOT NULL
 ,position tinyint  NOT NULL DEFAULT 99
 ,reference TEXT  NOT NULL
 ,supplier_reference TEXT
 ,price decimal(20,6) NOT NULL DEFAULT 0.0
 ,ecotax decimal(17,6) NOT NULL DEFAULT 0.0
 ,quantity TINYINT(1) NOT NULL DEFAULT 0
 , weight decimal(20,6)	NOT NULL DEFAULT 0.0
 , minimal_quantity TINYINT(10) NOT NULL DEFAULT 1
 , available_date date NOT NULL DEFAULT '0000-00-00'
 ,PRIMARY KEY (id_product,id_option,id_option_value)
 ,CONSTRAINT FOREIGN KEY (id_product) REFERENCES ps_product (id_product) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_option) REFERENCES ps_c3_option (id_option) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_option_value) REFERENCES ps_c3_option_value (id_option_value) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ps_c3_product_option_value_shop (
 id_product int(10) unsigned NOT NULL
 ,id_option int(10) unsigned NOT NULL
 ,id_option_value int(10) unsigned NOT NULL
 ,id_shop int(10) unsigned NOT NULL
 ,PRIMARY KEY (id_product,id_option,id_option_value,id_shop)
 ,CONSTRAINT FOREIGN KEY (id_product) REFERENCES ps_product (id_product) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_option) REFERENCES ps_c3_option (id_option) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_option_value) REFERENCES ps_c3_option_value (id_option_value) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_shop) REFERENCES ps_shop (id_shop) ON DELETE CASCADE ON UPDATE CASCADE
 ,CONSTRAINT FOREIGN KEY (id_product,id_option,id_option_value) REFERENCES ps_c3_product_option_value (id_product,id_option,id_option_value) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
