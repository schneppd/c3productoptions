<?php
namespace NsC3ProductOptions;

if (!defined('_PS_VERSION_'))
    exit;

include_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');

//process ajax call
switch (Tools::getValue('controller')) {
	case 'c3productoptions2checks' :
		die(Tools::jsonEncode(array('result'=>'my_value')));
		break;
	default:
		exit;
}
exit;







