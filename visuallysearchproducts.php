<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

define('_VSP_PS16_', version_compare(_PS_VERSION_, '1.7', '<'));

class VisuallySearchProducts extends Module
{
    /**
     * @var array
     */
    protected $errors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'visuallysearchproducts';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'VisualSearch';
        $this->need_instance = 0;
        $this->controllers = array('VisualSearch');

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Visually Search Products');
        $this->description = $this->l('Offer your customers the possibility of finding products using single picture!');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install function
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        Configuration::updateValue('VISUALLY_SEARCH_PRODUCTS_API_KEY', '');
        Configuration::updateValue('VISUALLY_SEARCH_PRODUCTS_LIVE_MODE', false);
        Configuration::updateValue('VISUALLY_SEARCH_PRODUCTS_VERSION', $this->version);

        $host = Context::getContext()->shop->getBaseURL(true);

        $key = $this->uuid();
        Db::getInstance()->execute('
            INSERT INTO ' . _DB_PREFIX_ . 'visually_search_products (id_visually_search_products)
            VALUES (\''. $key .'\')
        ');

        $this->notify($host, $key, 'prestashop;install');

        return parent::install() && $this->registerHook(array(
            'apiKeyVerify',
            'getProducts',
            'statusVersion',
            'displayHeader',
            'displayTop',
        ));
    }

    /**
     * Uninstall function
     */
    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        Configuration::deleteByName('VISUALLY_SEARCH_PRODUCTS_API_KEY');
        Configuration::deleteByName('VISUALLY_SEARCH_PRODUCTS_LIVE_MODE');
        Configuration::deleteByName('VISUALLY_SEARCH_PRODUCTS_VERSION');

        $host = Context::getContext()->shop->getBaseURL(true);

        $this->notify($host, '', 'prestashop;uninstall');

        return parent::uninstall();
    }

    /**
     * Send notifaction about installation
     *
     * @param $hosts
     * @param $keys
     * @param $type
     */
    protected function notify($hosts, $key, $type)
    {
        $ch = curl_init('https://api.visualsearch.wien/installation_notify');

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Vis-API-KEY: marketing',
            'Vis-SYSTEM-HOSTS:'.$hosts,
            'Vis-SYSTEM-KEY:' . $key,
            'Vis-SYSTEM-TYPE: visually_search_products;'.$type,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $api_key_verify = $this->context->link->getModuleLink(
            'visuallysearchproducts',
            'ApiKeyVerify'
        );

        $get_products_link = $this->context->link->getModuleLink(
            'visuallysearchproducts',
            'GetProducts'
        );

        $status_version_link = $this->context->link->getModuleLink(
            'visuallysearchproducts',
            'StatusVersion'
        );

        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submit_visually_search_productsModule')) == true) {
            $this->postProcess();
        } elseif (Tools::isSubmit('submit_api_credentials_'.$this->name)) {
            $this->processApiCredentialsFormFields();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('api_key_verify', $api_key_verify);
        $this->context->smarty->assign('get_products_link', $get_products_link);
        $this->context->smarty->assign('status_version_link', $status_version_link);

        $output = '';

        if (count($this->errors)) {
            foreach ($this->errors as $error) {
                $output .= $this->displayError($error);
            }
        }

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $output .= $this->renderForm();
        $output .= $this->renderApiCredentialsForm();

        return $output;
    }

    /**
     * @return Controller|AdminController|FrontController
     */
    protected function getController()
    {
        return $this->context->controller;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_visually_search_productsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->getController()->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'VISUALLY_SEARCH_PRODUCTS_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'VISUALLY_SEARCH_PRODUCTS_LIVE_MODE' => Tools::getValue(
                'VISUALLY_SEARCH_PRODUCTS_LIVE_MODE',
                Configuration::get(
                    'VISUALLY_SEARCH_PRODUCTS_LIVE_MODE',
                    null,
                    null,
                    $this->context->shop->id
                )
            ),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        foreach (array_keys($this->getConfigFormValues()) as $key) {
            Configuration::updateValue(
                $key,
                Tools::getValue($key),
                false,
                null,
                $this->context->shop->id
            );
        }

        $this->redirectWithConfirmation();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     *
     * @return string
     */
    protected function renderApiCredentialsForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_api_credentials_'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).
            '&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getApiCredentialsFormFieldsValue(),
            'languages' => $this->getController()->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getApiCredentialsFormFields()));
    }

    /**
     * Set values for the inputs.
     *
     * @return array
     */
    protected function getApiCredentialsFormFieldsValue()
    {
        return array(
            'VISUALLY_SEARCH_PRODUCTS_API_KEY' => Tools::getValue(
                'VISUALLY_SEARCH_PRODUCTS_API_KEY',
                Configuration::get(
                    'VISUALLY_SEARCH_PRODUCTS_API_KEY',
                    null,
                    null,
                    $this->context->shop->id
                )
            ),
        );
    }

    /**
     * Create the structure of your form.
     *
     * @return array
     */
    protected function getApiCredentialsFormFields()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('API credentials'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API key'),
                        'name' => 'VISUALLY_SEARCH_PRODUCTS_API_KEY',
                        'required' => true,
                        'col' => 3,
                    ),
                    array(
                        'type' => 'html',
                        'name' => 'VISUALLY_SEARCH_PRODUCTS_GET_CREDENTIALS_LINK',
                        'html_content' =>
                            '<a href="https://www.visualsearch.at/index.php/credentials/" target="_blank">'.
                                $this->l('Please click here to get your API credentials').
                            '</a>',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Validate API credentials'),
                ),
            ),
        );
    }

    /**
     * Save API credentials.
     */
    protected function processApiCredentialsFormFields()
    {
        if (!$this->validateApiCredentialsFormFields()) {
            return;
        }

        foreach (array_keys($this->getApiCredentialsFormFieldsValue()) as $key) {
            Configuration::updateValue(
                $key,
                Tools::getValue($key),
                false,
                null,
                $this->context->shop->id
            );
        }

        $this->redirectWithConfirmation();
    }

    /**
     * Validate API credentials form fields.
     *
     * @return bool
     */
    protected function validateApiCredentialsFormFields()
    {
        $this->validateApiKey();

        return !count($this->errors);
    }

    /**
     * Validate the API key.
     */
    protected function validateApiKey()
    {
        $apiKey = Tools::getValue('VISUALLY_SEARCH_PRODUCTS_API_KEY');

        if (is_string($apiKey) && trim($apiKey)) {
            $handle = curl_init();
            $httpHeader = array(
                'Vis-API-KEY: '.$apiKey,
                'Vis-SOLUTION-TYPE: search',
            );

            curl_setopt($handle, CURLOPT_URL, 'https://api.visualsearch.wien/api_key_verify');
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($handle, CURLOPT_HTTPHEADER, $httpHeader);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($handle);

            curl_close($handle);

            if (($result === false) || !is_array($data = json_decode($result, true))) {
                $this->errors[] = $this->l('Failed to validate the API key.');

                return;
            }

            if (isset($data['code']) && ((int)$data['code'] === 200)) {
                return;
            }
        }

        $this->errors[] = $this->l('Invalid API key.');
    }

    /**
     * Redirect with confirmation.
     */
    protected function redirectWithConfirmation()
    {
        Tools::redirectAdmin($this->getModuleSettingsUrl(array(
            'conf' => 6,
            'token' => $this->getToken(),
        )));
    }

    /**
     * Get the module settings URL.
     *
     * @param array $extraParams
     *
     * @return string
     */
    protected function getModuleSettingsUrl(array $extraParams = array())
    {
        $params = array(
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        );

        if ($extraParams) {
            $params = array_merge($params, $extraParams);
        }

        return $this->context->link->getAdminLink('AdminModules', false).'&'.http_build_query($params);
    }

    /**
     * Get a token.
     *
     * @return string|bool
     */
    protected function getToken()
    {
        return Tools::getAdminTokenLite('AdminModules');
    }

    /**
     * Api key verify endpoint
     */
    public function hookApiKeyVerifyRoutes()
    {
        return array(
            'module-'.$this->name.'-api_key_verify' => array(
                'controller' => 'ApiKeyVerify',
                'rule' => $this->name.'/ApiKeyVerify',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                ),
            ),
        );
    }

    /**
     * Get products endpoint
     */
    public function hookGetProductsRoutes()
    {
        return array(
            'module-'.$this->name.'-get_products' => array(
                'controller' => 'GetProducts',
                'rule' => $this->name.'/GetProducts',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                ),
            ),
        );
    }

    /**
     * Status version endpoint
     */
    public function hookStatusVersionRoutes()
    {
        return array(
            'module-'.$this->name.'-status_version' => array(
                'controller' => 'StatusVersion',
                'rule' => $this->name.'/StatusVersion',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                ),
            ),
        );
    }

    /**
     * @param mixed $params
     */
    public function hookDisplayHeader($params)
    {
        if (_VSP_PS16_) {
            $this->getController()->addCss($this->_path . 'views/css/front/search-link-1.6.css');
        } else {
            $this->getController()->registerStylesheet(
                'modules-' . $this->name . '-search-link',
                'modules/' . $this->name . '/views/css/front/search-link.css',
                array(
                    'media' => 'all',
                    'priority' => 150,
                )
            );
        }
    }

    /**
     * @return bool
     */
    public function isLiveMode()
    {
        return (bool)Configuration::get(
            'VISUALLY_SEARCH_PRODUCTS_LIVE_MODE',
            null,
            null,
            $this->context->shop->id
        );
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return (string)Configuration::get(
            'VISUALLY_SEARCH_PRODUCTS_API_KEY',
            null,
            null,
            $this->context->shop->id
        );
    }

    /**
     * @param mixed $params
     */
    public function hookDisplayTop($params)
    {
        if ($this->getApiKey() && $this->isLiveMode()) {
            $this->context->smarty->assign(array(
                'visual_search_link' => array(
                    'href' => $this->context->link->getModuleLink($this->name, 'VisualSearch'),
                    'title' => $this->l('Find products using picture'),
                )
            ));
    
            return $this->display(__FILE__, '/views/templates/hook/search-link' . (_VSP_PS16_ ? '-1.6' : '') . '.tpl');
        }
    }

    /**
     * @param mixed $params
     */
    public function hookDisplayNav($params)
    {
        return $this->hookDisplayTop($params);
    }

    /**
     * @param mixed $params
     */
    public function hookisplayNav1($params)
    {
        return $this->hookDisplayTop($params);
    }

    /**
     * @param mixed $params
     */
    public function hookDisplayNav2($params)
    {
        return $this->hookDisplayTop($params);
    }

    /**
     * Return Uuid identifier
     *
     * @return string Uuid
     */
    private function uuid(): string
    {
        return sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * @param array $products
     *
     * @return string
     */
    public function renderProductsList(array $products)
    {
        $this->context->smarty->assign(array(
            'products' => $products
        ));

        return $this->display(
            __FILE__,
            '/views/templates/front/products-list' . (_VSP_PS16_ ? '-1.6' : '') . '.tpl'
        );
    }
}
