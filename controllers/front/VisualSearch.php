<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

require_once dirname(__FILE__) . '/../../classes/VisuallySearchProductsFrontController.php';

class VisuallySearchProductsVisualSearchModuleFrontController extends VisuallySearchProductsFrontController
{
    /**
     * @var VisuallySearchProducts
     */
    public $module;

    /**
     * @return string
     */
    protected function getLocale()
    {
        $locale = _VSP_PS16_ ? $this->context->language->language_code : $this->context->language->locale;

        if (count($data = explode('-', $locale)) === 2) {
            $locale = implode('_', array(Tools::strtolower($data[0]), Tools::strtoupper($data[1])));
        }
        
        return $locale;
    }

    public function setMedia()
    {
        parent::setMedia();

        if (_VSP_PS16_) {
            $this->addJs(array(
                'https://releases.transloadit.com/uppy/v2.4.1/uppy.min.js',
                'https://releases.transloadit.com/uppy/locales/v2.0.5/' . $this->getLocale() . '.min.js',
                'modules/' . $this->module->name . '/views/js/front/visual-search-1.6.js',
            ));

            $this->addCss(array(
                'https://releases.transloadit.com/uppy/v2.4.1/uppy.min.css',
                'modules/' . $this->module->name . '/views/css/front/visual-search-1.6.css',
            ));

            if (!$this->useMobileTheme()) {
                $this->addCSS(array(
                    _THEME_CSS_DIR_ . 'scenes.css'       => 'all',
                    _THEME_CSS_DIR_ . 'category.css'     => 'all',
                    _THEME_CSS_DIR_ . 'product_list.css' => 'all',
                ));
            }
        } else {
            $this->registerJavascript(
                'modules-' . $this->module->name . '-uppy',
                'https://releases.transloadit.com/uppy/v2.4.1/uppy.min.js',
                array(
                    'position' => 'bottom',
                    'priority' => 150,
                    'server' => 'remote',
                )
            );

            $this->registerJavascript(
                'modules-' . $this->module->name . '-uppy-locale',
                'https://releases.transloadit.com/uppy/locales/v2.0.5/' . $this->getLocale() . '.min.js',
                array(
                    'position' => 'bottom',
                    'priority' => 150,
                    'server' => 'remote',
                )
            );

            $this->registerJavascript(
                'modules-' . $this->module->name . '-visual-search',
                'modules/' . $this->module->name . '/views/js/front/visual-search.js',
                array(
                    'position' => 'bottom',
                    'priority' => 150,
                )
            );

            $this->registerStylesheet(
                'modules-' . $this->module->name . '-uppy',
                'https://releases.transloadit.com/uppy/v2.4.1/uppy.min.css',
                array(
                    'media' => 'all',
                    'priority' => 150,
                    'server' => 'remote',
                )
            );

            $this->registerStylesheet(
                'modules-' . $this->module->name . '-visual-search',
                'modules/' . $this->module->name . '/views/css/front/visual-search.css',
                array(
                    'media' => 'all',
                    'priority' => 150,
                )
            );
        }
    }

    protected function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        if (isset($breadcrumb['links'])) {
            $breadcrumb['links'][] = array(
                'title' => $this->module->l('Visual search', 'VisualSearch'),
                'url' => $this->context->link->getModuleLink($this->module->name, 'VisualSearch'),
            );
        }

        return $breadcrumb;
    }

    public function initContent()
    {
        if (!$this->module->getApiKey() || !$this->module->isLiveMode()) {
            if ($this->ajax) {
                die(json_encode(array('reload' => true)));
            } else {
                Tools::redirect($this->context->link->getPageLink('index'));
            }
        }

        $this->context->smarty->assign(array(
            'visual_search' => array(
                'ajax_url' => $this->context->link->getModuleLink(
                    $this->module->name,
                    'VisualSearch',
                    array(
                        'ajax' => 1,
                        'action' => 'productsSearch',
                    )
                ),
                'locale' => $this->getLocale(),
            )
        ));

        parent::initContent();

        if (_VSP_PS16_) {
            $this->setTemplate('visual-search-1.6.tpl');
        } else {
            $this->setTemplate('module:' . $this->module->name . '/views/templates/front/visual-search.tpl');
        }
    }

    /**
     * @return array
     */
    protected function getProductsIds()
    {
        $filename = isset($_FILES['visual_search_file']['tmp_name']) ? $_FILES['visual_search_file']['tmp_name'] : '';
        if ($filename && file_exists($filename)) {
            $handle = curl_init();

            $httpHeaders = array(
                'Vis-API-KEY: ' . $this->module->getApiKey(),
                'Vis-SYSTEM-HOSTS: prestashop.visualsearch.at',
                'Vis-SYSTEM-TYPE: prestashop',
                'Content-Type: application/json',
            );

            $postFields = json_encode(array(
                'image_data' => base64_encode(Tools::file_get_contents($filename))
            ));
            
            curl_setopt($handle, CURLOPT_URL, 'https://api.visualsearch.wien/search_single_demo');
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($handle, CURLOPT_HTTPHEADER, $httpHeaders);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($handle);

            curl_close($handle);

            $responseData = @json_decode($result, true);
            $ids = array();

            if (isset($responseData['result']) && is_array($responseData['result'])) {
                foreach ($responseData['result'] as $id) {
                    $ids[] = (int)$id;
                }
            }

            return $ids;
        }
        
        return array();
    }

    public function displayAjaxProductsSearch()
    {
        $productsIds = $this->getProductsIds();
        $products = array();

        if (_VSP_PS16_) {
            require_once dirname(__FILE__) . '/../classes/Product16.php';

            foreach ($productsIds as $productId) {
                $product = (new VisuallySearchProducts\PrestaShop16\Product(
                    $productId,
                    $this->context,
                    $this->module
                ))->present();

                if ($product && $product['active']) {
                    $products[] = $product;
                }
            }
        } else {
            require_once dirname(__FILE__) . '/../classes/Product.php';

            foreach ($productsIds as $productId) {
                $product = (new VisuallySearchProducts\PrestaShop17\Product(
                    $productId,
                    $this->context,
                    $this->module
                ))->present();

                if ($product) {
                    $products[] = $product;
                }
            }
        }

        ob_end_clean();
        header('Content-Type: application/json');

        $json = json_encode(array(
            'products_list' => $this->module->renderProductsList($products)
        ));

        if (_VSP_PS16_) {
            echo $json;
        } else {
            $this->ajaxRender($json);
        }
    }
}
