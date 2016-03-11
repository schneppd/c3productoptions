insert into ps_c3_option(id_option,option_type,name) VALUES
 (2,'select','taille maillot')
,(3,'select','taille pantalon')
,(4,'checkbox','validation conditions')
,(5,'checkbox','gold plating')
,(6,'text','Nom maillot')
,(7,'text','Text Pantalon')
,(8,'radio','Choix couleur')
;

insert into ps_c3_option_lang(id_option,id_lang,public_name) VALUES
 (2,5,'Taille maillot')
,(3,5,'Taille pantalon')
,(4,5,'Conditions')
,(5,5,'Placage or?')
,(6,5,'Personnalisation maillot')
,(7,5,'Personnalisation pantalon')
,(8,5,'Couleur poches')
;

insert into ps_c3_option_shop(id_option,id_shop) VALUES
 (2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1)
;

insert into ps_c3_option_value(id_option_value,name) VALUES
 (2,'2XS'),(1,'XS'),(3,'S'),(4,'M'),(5,'L'),(6,'XL'),(7,'2XL')
,(8,'3XL pantalon'),(9,'Vous acceptez de bla bla'),(10,'Show how you are rich?')
,(11,'Text to show on the shirt'),(12,'Text to show on the trousers')
,(13,'Red'),(14,'Blue'),(15,'Green')
;

insert into ps_c3_option_value_lang(id_option_value,id_lang,public_name) VALUES
 (2,5,'2XS'),(1,5,'XS'),(3,5,'S'),(4,5,'M'),(5,5,'L'),(6,5,'XL'),(7,5,'2XL')
,(8,5,'3XL pantalon'),(9,5,'Vous acceptez de bla bla bla'),(10,5,'Option bling bling?')
,(11,5,'Texte perso maillot'),(12,5,'Texte perso pantalon')
,(13,5,'Rouge'),(14,5,'Bleu'),(15,5,'Vert')
;

insert into ps_c3_option_value_shop(id_option_value,id_shop) VALUES
 (1,1),(2,1),(3,1),(4,1),(5,1)
 ,(6,1),(7,1),(8,1),(9,1),(10,1)
 ,(11,1),(12,1),(13,1),(14,1),(15,1)
;

insert into ps_c3_product_option_value(id_product,id_option,id_option_value,reference,supplier_reference,price,quantity,available_date) VALUES
 (53998,2,1,'500-2xs','500-2xs-bb',0.0,50,'0000-00-00')
, (53998,2,2,'500-xs','500-xs-bb',0.0,50,'0000-00-00')
, (53998,2,3,'500-s','500-s-bb',0.0,50,'0000-00-00')
, (53998,2,4,'500-m','500-m-bb',0.0,50,'0000-00-00')
, (53998,2,5,'500-l','500-l-bb',0.0,50,'0000-00-00')
, (53998,2,6,'500-xl','500-xl-bb',0.0,50,'0000-00-00')
, (53998,2,7,'500-2xl','500-2xl-bb',0.0,50,'0000-00-00')
, (53998,3,1,'500-p-2xs','500-p-2xs-bb',15.0,50,NOW())
, (53998,3,2,'500-p-s','500-p-s-bb',15.0,50,NOW())
, (53998,3,8,'500-p-3xl','500-p-3xl-bb',18.0,50,NOW())
, (53998,4,9,'500-chk-cond','500-chk-cond-bb',18.0,50,NOW())
, (53998,5,10,'500-chk-bling','500-chk-bling-bb',55.0,50,NOW())
, (53998,6,11,'500-perso-maillot','500-perso-maillot-bb',55.0,50,NOW())
, (53998,7,12,'500-perso-pantalon','500-perso-pantalon-bb',55.0,50,NOW())
, (53998,8,13,'500-perso-color-red','500-perso-color-red-bb',55.0,50,NOW())
, (53998,8,14,'500-perso-color-blue','500-perso-color-blue-bb',55.0,50,NOW())
, (53998,8,15,'500-perso-color-green','500-perso-color-green-bb',55.0,50,NOW())
;

insert into ps_c3_product_option_value_shop(id_product,id_option,id_option_value,id_shop) VALUES 
 (53998,2,1,1)
, (53998,2,2,1)
, (53998,2,3,1)
, (53998,2,4,1)
, (53998,2,5,1)
, (53998,2,6,1)
, (53998,2,7,1)
, (53998,3,1,1)
, (53998,3,2,1)
, (53998,3,8,1)
, (53998,4,9,1)
, (53998,5,10,1)
, (53998,6,11,1)
, (53998,7,12,1)
, (53998,8,13,1)
, (53998,8,14,1)
, (53998,8,15,1)
;
