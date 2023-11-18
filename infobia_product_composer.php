<?php


class infobia_product_composer extends Module
{
    /* @var boolean error */
    protected $error = false;


    public function __construct()
    {
        $this->name = 'infobia_product_composer';
        $this->tab = 'front_office_features';

        $this->version = '1.2';
        $this->author = 'Infobia';
        $this->need_instance = 0;
        $this->class_name = 'AdminInfobiaModuleIPC';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Infobia Product Composer');
        $this->description = $this->l('Ce module permet de calculer dynamiquement le prix d\'un produit');
        $this->confirmUninstall = $this->l('Etes vous sûre de supprimer ce module ?');
    }

    public function install()
    {
        $this->changeOverrideBeforeinstall();
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayHome')
            && $this->registerHook('displayReassurance')
            && $this->registerHook('displayFooterProduct')
            && $this->registerHook('displayCartExtraProductActions')
            && $this->registerHook('displayProductActions') //quickview
            && $this->registerHook('displayProductPriceBlock')
            && $this->resetDb();
    }

    public function changeOverrideBeforeinstall()
    {

        $functionsNames = array("function getOrderTotal(", "function getProducts(", "function updateQty(");

        $cart = file_get_contents(dirname(__FILE__, 3) . '/classes/Cart.php');
        $override_cart = file_get_contents(dirname(__FILE__, 3) . '/modules/' . $this->name . '/override/classes/Cart.php');

        foreach ($functionsNames as $name) {

            $listeParams = array();
            {

                $index = strpos($cart, $name);
                $result = substr($cart, $index);

                $params = substr($result, 0, strpos($result, ')'));

                $index1 = strpos($cart, $name) + strlen($name);
                $result1 = substr($cart, $index1);
                $params1 = substr($result1, 0, strpos($result1, ')'));
                $listeParams = explode(",", $params1);

                $indexOv = strpos($override_cart, $name);
                $resultOv = substr($override_cart, $indexOv);
                $paramsOv = substr($resultOv, 0, strpos($resultOv, ')'));

                $override_cart = str_replace($paramsOv, $params, $override_cart);


                $listeParamsWtValue = "";
                foreach ($listeParams as $param) {
                    $val = explode('=', $param);
                    if ($listeParamsWtValue != "") $listeParamsWtValue = $listeParamsWtValue . ',';
                    $index2 = strpos($val[0], '$');
                    $result2 = substr($val[0], $index2);


                    $listeParamsWtValue .= $result2;
                }


                $name = str_replace("function ", "parent::", $name);
                $indexOv = strpos($override_cart, $name);
                $resultOv = substr($override_cart, $indexOv);
                $paramsOv = substr($resultOv, 0, strpos($resultOv, ')'));
                $override_cart = str_replace($paramsOv, $name . $listeParamsWtValue, $override_cart);


                file_put_contents(dirname(__FILE__, 3) . '/modules/' . $this->name . '/override/classes/Cart.php', $override_cart);

            }

        }

        return true;
    }

    private function resetDb()
    {
        $tab = new Tab();
        $tab->name = array();

        foreach (Language::getLanguages() as $language) {
            $tab->name[$language['id_lang']] = $this->displayName;
        }

        $tab->class_name = $this->class_name;
        $tab->id_parent = (int)Tab::getIdFromClassName('IMPROVE');//76 si prestashop 1.7
        $tab->module = $this->name;
        $tab->icon = "flash_on";
        $tab->add();

        $id_parent = (int)Tab::getIdFromClassName($this->class_name);

        $tabs = array("AdminInfobiaGroupe" => 'Liste Groupes', "AdminInfobiaOptions" => 'Liste Options', "AdminInfobiaAttribute" => 'Liste Attributs', "AdminInfobiaProd" => 'Configuration produits', "AdminInfobiaConfig" => 'Configuration interface');

        foreach ($tabs as $key => $val) {
            $tab = new Tab();
            $tab->name = array();
            foreach (Language::getLanguages() as $language) {
                $tab->name[$language['id_lang']] = $this->l($val);
            }

            $tab->class_name = $key;
            $tab->id_parent = $id_parent;
            $tab->module = $this->name;

            $tab->add();
        }

        $sql_file = "../modules/" . $this->name . "/sql-install.sql";
        if (!$this->loadSQLFile($sql_file)) {
            return false;
        }
        return true;
    }

    public function loadSQLFile($sql_file)
    {
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('ps_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", $sql_content);

        $result = true;
        foreach ($sql_requests as $request) {
            if (!empty($request)) $result = Db::getInstance()->execute(trim($request));
        }
        return $result;
    }


    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name)) {

            $myModuleName = strval(Tools::getValue('INFOBIA_COMPOSER_KEY'));
            if (!$myModuleName || empty($myModuleName) || !Validate::isGenericName($myModuleName)) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('INFOBIA_COMPOSER_KEY', $myModuleName);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }


            if (!self::sendCurl('CHECK_KEY')) {
                $output .= $this->displayError($this->l('Invalid key'));
            } else {
                $output .= $this->displayConfirmation($this->l('Licence valide'));
            }

        }
        return $output . $this->displayForm();
    }


    public function verificationToken($myModuleName)
    {
        return $this->sendCurl($key, $site, $module, 'CHECK_KEY');
    }


    public function sendCurl($fct)
    {

        $key = Configuration::get('INFOBIA_COMPOSER_KEY');
        $srv = $_SERVER;
        $module = $this->name;
        $url = "https://infobia-online.com/api/sec/?token=infobia";

        $data['key'] = $key;
        $data['srv'] = $srv;
        $data['module'] = $module;
        $data['fct'] = $fct;

        $ch = curl_init($url);


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "accept: application/json;version=1.0",
                "cache-control: no-cache",
                "Content-Type: application/json")
        );

        // Submit the POST request
        $result = curl_exec($ch);

        $result = json_decode($result);


        curl_close($ch);


        return $result;

    }

    public function displayForm()
    {
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Clé de licence'),
                    'name' => 'INFOBIA_COMPOSER_KEY',
                    'size' => 20,
                    'required' => false
                ]


            ],

            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        $helper->fields_value['INFOBIA_COMPOSER_KEY'] = Configuration::get('INFOBIA_COMPOSER_KEY');
        return $helper->generateForm($fieldsForm);
    }


    public function uninstall()
    {

        $sql_file = "../modules/" . $this->name . "/sql-uninstall.sql";
        if (!$this->loadSQLFile($sql_file)) {
            return false;
        }

        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    //ajout des js et css dans la partie front office
    public function hookDisplayHeader()
    {
        if ((Tools::getValue("controller", "") == "product") || (Tools::getValue("controller") == "index")) {
            $config = $this->getBcConfigProduct();

            if ((count($config) > 0) || (Tools::getValue("controller") == "index")) {
                $this->context->controller->addJquery();

                $this->context->controller->addJS($this->_path . 'views/js/script_front.js', 'all');

                $this->context->controller->registerStylesheet('infobia-back', $this->_path . 'views/css/infobia-back.css');
                $this->context->controller->addCSS($this->_path . 'views/css/infobia-front.css');

                $this->context->controller->addCSS('https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css');
                $this->context->controller->addJS($this->_path . 'js/jquery.dataTables.min.js', 'all');
            }
        }
        $this->context->controller->addJquery();

        $this->context->controller->addJS($this->_path . 'views/js/cart.js', 'all');
    }

    public function hookDisplayBackOfficeHeader()
    {
        $controller = Tools::getValue('controller', "");

        $tabs = array("AdminInfobiaGroupe", "AdminInfobiaOptions", "AdminInfobiaAttribute", "AdminInfobiaProd", "AdminInfobiaConfig", "AdminInfobiaSec");

        if (in_array($controller, $tabs)) {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path . 'views/js/script_back.js', 'all');
            $this->context->controller->addJS($this->_path . 'js/jquery.dataTables.min.js', 'all');
            $this->context->controller->addCSS($this->_path . 'views/css/infobia-back.css');
            $this->context->controller->addCSS('https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css');
        }
    }


    public function hookDisplayHome($params)
    {
        global $cookie;
        $id_lang = $cookie->id_lang;
        $token = Tools::getToken(false);

        $ProductHome = $this->getProductHome();

        if (isset($ProductHome['id_product'])) {

            $product = new Product($ProductHome['id_product'], true, $id_lang);
            $tax_rate = Tax::getProductTaxRate((int)$ProductHome['id_product'], null);

            $priceCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);

            if ($priceCalculationMethod == 0) //ttc
            {
                $price = Product::getPriceStatic($ProductHome['id_product'], true, null, 6, null, false, false);
            } else {
                $price = Product::getPriceStatic($ProductHome['id_product'], false, null, 6, null, false, false);
            }


            $images = Image::getImages((int)$id_lang, $ProductHome['id_product']);
            $id_image = Product::getCover($ProductHome['id_product']);
            // get Image by id
            if (sizeof($id_image) > 0) {
                $image = new Image($id_image['id_image']);
                $image_url = _PS_BASE_URL_ . _THEME_PROD_DIR_ . $image->getExistingImgPath() . ".jpg";
            }


            $apparence = $this->getApparence();

            $cur = new currency($cookie->id_currency);


            $usetax = true;
            if ($priceCalculationMethod == 1) {
                $usetax = false;
                $showPriceMethod = "HT";
            }
            $priceCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $price_without_reduction = Product::getPriceStatic($ProductHome['id_product'], $usetax, $id_product_attribute = null, $decimals = 6, $divisor = null, $only_reduc = false, $usereduc = false);

            $results = array(); // résultat à retourner
            $options = array();


            $prix_attrib = $this->getApparence('prix_attrib');
            $methodPriceAttr = $prix_attrib[0]['value']; // ttc or ht


            $cur = new currency($cookie->id_currency);


            $priceCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);


            $showPriceMethod = "TTC";
            $usetax = true;
            if ($priceCalculationMethod == 1) {
                $usetax = false;
                $showPriceMethod = "HT";
            }
            $tax_rate = Tax::getProductTaxRate((int)$ProductHome['id_product'], null);

            $specific_price = SpecificPrice::getSpecificPrice(
                (int)$ProductHome['id_product'],
                1,
                $cookie->id_currency,
                Context::getContext()->country->id,
                Group::getCurrent()->id,
                1
            );

            $reduction = "";
            $reduction_type = "";
            $reduction_tax = "";
            if ($specific_price) {
                $reduction = $specific_price['reduction'];
                $reduction_type = $specific_price['reduction_type'];
                $reduction_tax = $specific_price['reduction_tax'];

                if ($reduction_type != "percentage") {
                    if ($priceCalculationMethod == 1 && $reduction_tax == 1) {
                        $reduction = $reduction / (1 + ($tax_rate / 100));
                    }

                    if ($priceCalculationMethod == 0 && $reduction_tax == 0) {

                        $reduction = $reduction + (($reduction * $tax_rate) / 100);
                    }


                }
            }


            /******liste des groupes********/
            $groupes = $this->getGroups($ProductHome['id_product']);
            $groupe_option = array();
            foreach ($groupes as $groupe) {
                $res = $groupe;
                $options = $this->getOptions($groupe['id_groupe'], null, $ProductHome['id_product']);
                foreach ($options as &$option) {
                    $attributs = $this->getAttributes($option['id_option']);
                    foreach ($attributs as &$attribut) {
                        $attribut['hasFils'] = "1";


                        if ($priceCalculationMethod == 0 && $methodPriceAttr == "ht") {
                            $amount = $attribut['prix_attribut'] + (($attribut['prix_attribut'] * $tax_rate) / 100);
                            $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');
                            $attribut['prix_attribut'] = $amount;
                        }

                        if ($priceCalculationMethod == 1 && $methodPriceAttr == "ttc") {

                            $amount = $attribut['prix_attribut'] / (1 + ($tax_rate / 100));
                            $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');

                            $attribut['prix_attribut'] = $amount;
                        }

                        $options_enf = $this->getOptionsAttributes($attribut['id_attribut']);
                        if (empty($options_enf)) {
                            $attribut['hasFils'] = "0";
                        }
                        $attribut['fils'] = $options_enf;
                    }
                    $option['attributs'] = $attributs;
                }
                $res['values'] = $options;
                $results[] = $res;
            }

            $baseUrl = _PS_BASE_URL_SSL_ . __PS_BASE_URI__;
            $hide_name_group = $this->getHideNameGroup($ProductHome['id_product']);

            $this->context->smarty->assign(array(
                'initialprice' => $price,
                'url_image' => $image_url,
                'results' => $results,
                'apparence' => $apparence,
                'product' => $product,
                'token' => $token,
                'baseUrl' => $baseUrl,
                'currency_symbol' => $cur->symbol,
                'price_round' => Configuration::get('PS_PRICE_ROUND_MODE'),
                'module_name' => $this->name,
                'reduction' => $reduction,
                'reduction_type' => $reduction_type,
                'showPriceMethod' => $showPriceMethod,
                'hide_name_group' => $hide_name_group,
                'hide_min_max' => $this->getHideMinMax($ProductHome['id_product']),
                "price_without_reduction" => $price_without_reduction,
            ));

            return $this->display(__FILE__, 'home_productInfobia.tpl');
        }
    }


    public function hookDisplayReassurance($params)
    {

        global $cookie;
        $cur = new currency($cookie->id_currency);


        $priceCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);


        $showPriceMethod = "TTC";
        $usetax = true;
        if ($priceCalculationMethod == 1) {
            $usetax = false;
            $showPriceMethod = "HT";
        }

        $tax_rate = Tax::getProductTaxRate((int)Tools::getValue("id_product", 0), null);

        if (Tools::getValue("controller", "") == "product") {
            $config = $this->getBcConfigProduct();
            $apparence = $this->getApparence();


            $hide_name_group = $this->getHideNameGroup(Tools::getValue("id_product"));


            $prix_attrib = $this->getApparence('prix_attrib');
            $methodPriceAttr = $prix_attrib[0]['value']; // ttc or ht
            /********/
            $productId = (int)Tools::getValue('id_product');
            $product = new Product($productId);

            $results = array(); // résultat à retourner
            $options = array();
            if (count($config) > 0) {

                $specific_price = SpecificPrice::getSpecificPrice(
                    (int)Tools::getValue("id_product"),
                    1,
                    $cookie->id_currency,
                    Context::getContext()->country->id,
                    Group::getCurrent()->id,
                    1
                );

                $reduction = "";
                $reduction_type = "";
                if ($specific_price) {
                    $reduction = $specific_price['reduction'];
                    $reduction_type = $specific_price['reduction_type'];
                    $reduction_tax = $specific_price['reduction_tax'];

                    if ($reduction_type != "percentage") {
                        if ($priceCalculationMethod == 1 && $reduction_tax == 1) {
                            $reduction = $reduction / (1 + ($tax_rate / 100));
                        }

                        if ($priceCalculationMethod == 0 && $reduction_tax == 0) {

                            $reduction = $reduction + (($reduction * $tax_rate) / 100);
                        }


                    }
                }

                /******liste des groupes********/
                $groupes = $this->getGroups(Tools::getValue("id_product"));

                $groupe_option = array();

                foreach ($groupes as $groupe) {
                    $res = $groupe;
                    $options = $this->getOptions($groupe['id_groupe'], null, $productId);


                    foreach ($options as &$option) {
                        $attributs = $this->getAttributes($option['id_option'], $specific_price);

                        foreach ($attributs as &$attribut) {
                            if ($attribut['gestion_stock'] == 1) {
                                if ($attribut['qte_stock'] < $attribut['max_attribut']) {
                                    $attribut['max_attribut'] = $attribut['qte_stock'];
                                }
                            }
                            $attribut['hasFils'] = "1";


                            // if price attr HT et l'affichage TTC alors update price
                            if ($priceCalculationMethod == 0 && $methodPriceAttr == "ht") {
                                $amount = $attribut['prix_attribut'] + (($attribut['prix_attribut'] * $tax_rate) / 100);
                                $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');
                                $attribut['prix_attribut'] = $amount;
                            }

                            if ($priceCalculationMethod == 1 && $methodPriceAttr == "ttc")// boutique ht
                            {
                                $amount = $attribut['prix_attribut'] / (1 + ($tax_rate / 100));
                                $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');
                                $attribut['prix_attribut'] = $amount;
                            }
                            $options_enf = $this->getOptionsAttributes($attribut['id_attribut']);
                            if (empty($options_enf)) {
                                $attribut['hasFils'] = "0";
                            }


                            $attribut['fils'] = $options_enf;

                        }
                        $option['attributs'] = $attributs;

                    }

                    $res['values'] = $options;
                    $results[] = $res;

                }
                $baseUrl = _PS_BASE_URL_SSL_ . __PS_BASE_URI__;
                $this->context->smarty->assign(array(
                    //'initialprice' => $price,
                    // 'groupes' =>$groupes,
                    'results' => $results,
                    'config' => $config,
                    'apparence' => $apparence,
                    'currency_symbol' => $cur->symbol,
                    'baseUrl' => $baseUrl,
                    'module_name' => $this->name,
                    'hide_name_group' => $hide_name_group,
                    'hide_min_max' => $this->getHideMinMax(Tools::getValue("id_product")),
                    'price_round' => Configuration::get('PS_PRICE_ROUND_MODE'),

                    'reduction' => $reduction,
                    'reduction_type' => $reduction_type,

                    'url' => Context::getContext()->link->getModuleLink($this->name, 'ajax_module'),
                ));
                return $this->display(__FILE__, 'front_infobiaHook_module.tpl');
            }
        }
    }

    public function hookDisplayFooterProduct($params)
    {
        $config = $this->getBcConfigProduct();
        if (!empty($config)) {
            $this->context->smarty->assign(array(
                'cover' => $params['product']['cover'],
                'config' => $config,
                'product' => $params['product']
            ));
            return $this->display(__FILE__, 'displayFooterPrice.tpl');
        }
    }

    public function hookDisplayCartExtraProductActions($params)
    {


        $controller = Tools::getValue("controller");

        if ($controller == "orderconfirmation" || $controller == "cart") {
            $id_product = $params['product']['id_product'];
            $id_customization = $params['product']['id_customization'];

            $id_cart = $this->context->cart->id;

            if ($controller == "orderconfirmation") {
                $id_cart = Tools::getValue("id_cart");
            }
            if ($controller == "cart") {
                $id_cart = $this->context->cart->id;
            }

            $InfobiaProd = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'infobia_config_product WHERE id_product = ' . (int)$id_product);
            // var_dump( $id_customization);die();
            if ($InfobiaProd && $id_customization > 0 && $id_cart > 0) {
                $res = Db::getInstance()->executeS('SELECT * from ' . _DB_PREFIX_ . 'infobia_cart where id_product=' . (int)$id_product . ' and id_cart=' . (int)$id_cart . " and id_customization=" . (int)$id_customization);


                if (count($res) > 0) {

                    if (!empty($res[0]['attributes'])) {

                        $attributes = $res[0]['attributes'];

                        $attributes = json_decode($attributes);

                        $this->context->smarty->assign(array(
                                'res' => $res,
                                'attributes' => $attributes,
                                'urlUploads' => _PS_BASE_URL_SSL_ . __PS_BASE_URI__ . "modules/" . $this->name . "/uploads/",
                                'controller' => $controller,
                                'id_product' => $id_product,
                                'id_customization' => $id_customization,
                            )
                        );


                        return $this->display(__FILE__, 'displayCartExtraProductActions.tpl');
                    }
                }
            }
        }
    }

    public function hookDisplayProductPriceBlock($params)
    {

        return $this->hookDisplayCartExtraProductActions($params);
    }

    public function hookDisplayProductActions($params)
    {
        if (isset($_POST['action']) && $_POST['action'] == 'quickview') {
            $id_product = $_POST['id_product'];
            $config = $this->getBcConfigProducts($id_product);

            if ($config) {
                $apparence = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config` ');
                $this->context->smarty->assign(array(

                    'link' => $this->context->link->getProductLink($id_product),
                    'apparence' => $apparence,
                    'prod_id' => $id_product

                ));
                return $this->display(__FILE__, 'listing.tpl');
            }
        }

    }


    public function getHideNameGroup($id_product)
    {
        $sql = 'SELECT `hide_name_group` FROM`' . _DB_PREFIX_ . 'infobia_name_group`  
           WHERE id_product=' . $id_product;
        $hide_group_name = Db::getInstance()->executeS($sql);
        if (!empty($hide_group_name)) {
            return $hide_group_name[0];
        } else {
            return "";
        }

    }

    public function getHideMinMax($id_product)
    {
        $sql = 'SELECT `hide_min_max` FROM`' . _DB_PREFIX_ . 'infobia_name_group`  
           WHERE id_product=' . $id_product;
        $hide_min_max = Db::getInstance()->executeS($sql);
        if (!empty($hide_min_max)) {
            return $hide_min_max[0];
        } else {
            return "";
        }
    }

    public function getGroups($id_product)
    {

//        eval($this->sendCurl('GGF'));
//        return $groupes;

        $sql = 'SELECT g.*
FROM ps_infobia_groupe AS g
INNER JOIN ps_infobia_config_product AS cp ON g.id_groupe = cp.id_groupe
where cp.id_product =' . $id_product;
        $res = Db::getInstance()->executeS($sql);

        return ($res);
    }

    public function getBcConfigProduct()
    {

        global $cookie;

        $res = array();
        if (isset($_GET['id_product'])) {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config_product` where id_product =' . (int)$_GET['id_product'];

            $res = Db::getInstance()->executeS($sql);


        }

        return $res;

    }

    public function getBcConfigProducts($id)
    {

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config_product` where id_product =' . $id;
        $res = Db::getInstance()->executeS($sql);

        return ($res);
    }


    public function getGroupes()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_groupe` order by id_groupe desc ';
        return $data = Db::getInstance()->executeS($sql);
    }

    public function getOptions($id_groupe = null, $id_attrib = null, $id_product = null)
    {

        if ($id_groupe) {

            $sql = 'SELECT * FROM`' . _DB_PREFIX_ . 'infobia_config_product`  icp ,`' . _DB_PREFIX_ . 'infobia_option` io   WHERE icp.id_product=' . $id_product . ' and icp.id_groupe=' . $id_groupe . " and icp.id_option=io.id_option order by io.position_option";
        } elseif ($id_attrib) {
            $sql = 'SELECT * FROM`' . _DB_PREFIX_ . 'infobia_attribut_option_enfant`  iaoe ,`' . _DB_PREFIX_ . 'infobia_option` io   WHERE iaoe.id_option=io.id_option and iaoe.id_attribut=' . $id_attrib . " order by io.position_option";

        } else {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_option` order by id_option desc ';
        }
        return $data = Db::getInstance()->executeS($sql);
    }


    public function getOptionsAttributes($id_attrib)
    {


        $prix_attrib = $this->getApparence('prix_attrib');
        $methodPriceAttr = $prix_attrib[0]['value']; // ttc or ht

        $options = $this->getOptions(null, $id_attrib);

        $priceCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);
        $tax_rate = Tax::getProductTaxRate((int)Tools::getValue("id_product", 0), null);

        foreach ($options as &$option) {
            $attributs = $this->getAttributes($option['id_option']);

            foreach ($attributs as &$attribut) {
                if ($attribut['gestion_stock'] == 1) {
                    if ($attribut['qte_stock'] < $attribut['max_attribut']) {
                        $attribut['max_attribut'] = $attribut['qte_stock'];
                    }
                }
                if ($priceCalculationMethod == 0 && $methodPriceAttr == "ht") {
                    $amount = $attribut['prix_attribut'] + (($attribut['prix_attribut'] * $tax_rate) / 100);
                    $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');
                    $attribut['prix_attribut'] = $amount;
                }
                if ($priceCalculationMethod == 1 && $methodPriceAttr == "ttc") {
                    $amount = $attribut['prix_attribut'] / (1 + ($tax_rate / 100));
                    $amount = number_format($amount, Configuration::get('PS_PRICE_ROUND_MODE'), '.', '');
                    $attribut['prix_attribut'] = $amount;
                }


                $attribut['hasFils'] = "0";
            }
            $option['attributs'] = $attributs;

        }
        return $options;
    }


    public function getAttributes($id_opt = null, $specific_price = array())
    {


        if ($id_opt) {
            $sql = 'SELECT * FROM`' . _DB_PREFIX_ . 'infobia_attributs` WHERE id_opt=' . $id_opt . " and if(gestion_stock = 0 , true ,(qte_stock>=min_attribut and qte_stock>0)) and active=1 order by position_attribut";
        } else {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_attributs` order by id_attribut desc ';
        }
        $apply_reduct_ps = $this->getApparence("apply_reduct_ps");
        $reduction = "";
        $reduction_type = "";
        $datas = Db::getInstance()->executeS($sql);
        if ($apply_reduct_ps[0]['value'] == 1) {
            if ($specific_price) {
                $tax_rate = Tax::getProductTaxRate((int)Tools::getValue('id_product'), null);

                foreach ($datas as &$data) {
                    $reduction = $specific_price['reduction'];
                    $reduction_type = $specific_price['reduction_type'];
                    $reduction_tax = $specific_price['reduction_tax'];


                    $data['price_without_reduction'] = (float)$data['prix_attribut'];
                    if ($reduction_type == "percentage") {

                        $price_with_reduction = $data['prix_attribut'] - ($data['prix_attribut'] * $reduction);
                        $data['prix_attribut'] = $price_with_reduction;
                    }
                    if ($reduction_type == "amount") {
                        $prix_attrib = $this->getApparence('prix_attrib');
                        $methodPriceAttr = $prix_attrib[0]['value']; // ttc or ht

                        if ($methodPriceAttr == "ht" && $reduction_tax == 1)//ht
                        {

                            $reduction = $reduction - (float)($reduction * $tax_rate) / 100;
                        }
                        if ($methodPriceAttr == "ttc" && $reduction_tax == 0)//ht
                        {

                            $reduction = $reduction + (float)($reduction * $tax_rate) / 100;

                        }

                        $data['prix_attribut'] = $data['prix_attribut'] - (float)$reduction;
                    }

                }

            }
        }
        return $datas;
    }

    public function getConfigProduct($product_id)
    {
        $data = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config_product` where id_product=' . $product_id);
        $groupe_config = array();
        foreach ($data as $groupe) {
            $groupe_config[] = $groupe['id_groupe'];
        }

        return $groupe_config;
    }

    public function getApparence($name = "")
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config` order by id';

        if ($name != "") {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'infobia_config` where name="' . $name . '"';
        }

        $data = Db::getInstance()->executeS($sql);
        return $data;
    }

    public function getProductHome()
    {
        $data = Db::getInstance()->executeS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'infobia_name_group` where show_in_home=1');
        if ($data) {
            return $data[0];
        }
        return array();
    }
}

?>