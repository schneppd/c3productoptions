<?php

//if major problem with prestashop, abort
if (!defined('_PS_VERSION_'))
    exit;
/*
 * The module aims: add custom product's option logic to shop
 * - each product can have a free combination of options and there values, ex: Option: Size (list), values: [xxs,s,m,l]
 * - there is 4 option types:
 *     text: the customer needs to input some text to order the product, ex: what name to print on the shirt [bob]
 *     checkbox: the customer can check the option when he orders the product, ex: extend the guarantee 5 years
 *     radio: the customer needs to choose one of the value to order the product, ex: in what color do you whant the shirt (this option is kept for legacy purpose, all new product should use a list instead)
 *     list: the customer needs to choose one option to order the product, ex: Size values: [xxs,s,m,l]
 * - each option's value can influence the product final price
 * - when displayed in shelf, the product will show the lowest price combination
 * - each option may or may not be required
*/


require_once(dirname(__FILE__).'/c3module.php');


//declare the module
class C3ProductOptions extends Module
{

    function __construct()
    {
        //declare the module's infos
        $this->name = 'c3productoptions';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Schnepp David';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('C3ProductOptions block');
        $this->description = $this->l("Adds C3's custom product option logic.");
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    //steps to execute when the module is installed
    function install()
    {
        //execute installation sql commands
        if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'install'))
            return false;
        if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'test'))
            return false;
        //install module in needed prestashops's hooks
        if (!parent::install() ||
            !$this->registerHook('header') ||
            !$this->registerHook('productfooter') ||
            !$this->registerHook('footer') ||
            !Configuration::updateValue('C3PRODUCTOPTIONS_RESET', false)
        )
            return false;//abort if error
        return true;//install success
    }



    //steps to execute when the module is removed
    public function uninstall()
    {
        //execute uninstall sql commands
        if (!NsC3ProductOptions\C3Module::executeSqlFile(Db::getInstance(), 'uninstall'))
            return false;
                //uninstall module
        return parent::uninstall();
    }


    //add css file to header
    public function hookHeader($params)
    {
        $this->context->controller->addCSS(($this->_path).'views/css/c3productoptions.css', 'all');
    }
    //add block in footer
    public function hookFooter()
    {
        return $this->display(__FILE__, 'views/templates/front/footer.tpl');
    }
    //Add block under the product description
    public function hookProductFooter($params)
    {
        $id_category = (int)(Tools::getValue('id_category'));
        $cacheId = 'c3keywords_'.$id_category;
        $res =  $this->display(__FILE__, 'views/templates/front/c3productoptions.tpl', $this->getCacheId($cacheId));
        return trim($res);
    }

    //backend form checks
    public function getContent()
    {
                $output = '';
                $errors = array();
                if (Tools::isSubmit('submitC3ProductOptions')) {
                    //check if C3PRODUCTOPTIONS_RESET was provided
                        $c3po_reset = Tools::getValue('C3PRODUCTOPTIONS_RESET');
                        if (!strlen($c3po_reset))
                            $errors[] = $this->l('Please complete the data reset field.');
                        elseif (!Validate::isBool($c3po_reset))
                            $errors[] = $this->l('Invalid value for data reset. It has to be a boolean.');
                    //if errors, display error messages
                        if (count($errors))
                                $output = $this->displayError(implode('<br />', $errors));
                        else {
                                //update module values
                                Configuration::updateValue('C3PRODUCTOPTIONS_RESET', (bool)$c3po_reset);

                                $output = $this->displayConfirmation($this->l('Settings updated'));
                        }
                }
                return $output.$this->renderForm();
        }
    //backend form creation
    public function renderForm()
    {
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
                                            'name' => 'C3PRODUCTOPTIONS_RESET',
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
        $helper->submit_action = 'submitC3ProductOptions';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&c3productoptions_module='.$this->tab.'&module_name='.$this->name;
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
            'C3PRODUCTOPTIONS_RESET' => Tools::getValue('C3PRODUCTOPTIONS_RESET', (bool)Configuration::get('C3PRODUCTOPTIONS_RESET')),
        );
    }

}
