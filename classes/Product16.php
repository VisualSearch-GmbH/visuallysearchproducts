<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

namespace VisuallySearchProducts\PrestaShop16;

class Product
{
    /**
     * @var int
     */
    protected $productId;

    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var \Module
     */
    protected $module;

    /**
     * @param int $productId
     * @param \Context $context
     * @param \Module $module
     */
    public function __construct($productId, \Context $context, \Module $module)
    {
        $this->productId = (int)$productId;
        $this->context = $context;
        $this->module = $module;
    }

    /**
     * @return array|null
     */
    public function present()
    {
        if ($this->productId) {
            $front = in_array(
                $this->context->controller->controller_type,
                array('front', 'modulefront')
            );
    
            $nbDaysNewProduct = \Configuration::get('PS_NB_DAYS_NEW_PRODUCT');
            if (!\Validate::isUnsignedInt($nbDaysNewProduct)) {
                $nbDaysNewProduct = 20;
            }
    
            $sql = '
                SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) AS quantity' .
                    (\Combination::isFeatureActive() ? ', IFNULL(product_attribute_shop.id_product_attribute, 0) AS
                    id_product_attribute, product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity' :
                    '') . ', pl.`description`, pl.`description_short`, pl.`available_now`, pl.`available_later`,
                    pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`,
                    image_shop.`id_image` id_image, il.`legend` as legend, m.`name` AS manufacturer_name,
                    cl.`name` AS category_default, DATEDIFF(product_shop.`date_add`, DATE_SUB("' . date('Y-m-d') . '
                    00:00:00", INTERVAL ' . (int)$nbDaysNewProduct . ' DAY)) > 0 AS new, product_shop.price AS orderprice
                FROM `' . _DB_PREFIX_ . 'product` p ' .
                \Shop::addSqlAssociation('product', 'p') .
                (\Combination::isFeatureActive() ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop`
                    product_attribute_shop ON (p.`id_product` = product_attribute_shop.`id_product` AND
                    product_attribute_shop.`default_on` = 1 AND
                    product_attribute_shop.id_shop=' . (int)$this->context->shop->id . ')' : '') . ' ' .
                \Product::sqlStock('p', 0) . '
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (product_shop.`id_category_default` = cl.`id_category`
                    AND cl.`id_lang` = ' . (int)$this->context->language->id . \Shop::addSqlRestrictionOnLang('cl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = ' . (int)$this->context->language->id . \Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop ON (image_shop.`id_product` = p.`id_product`
                    AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$this->context->shop->id . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image`
                    AND il.`id_lang` = ' . (int)$this->context->language->id . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
                WHERE p.`id_product` = ' . $this->productId . '
                    AND product_shop.`id_shop` = ' . (int)$this->context->shop->id .
                    ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . ';
            ';
    
            if ($result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                $products = \Product::getProductsProperties(
                    $this->context->language->id,
                    $result
                );

                if ($this->context->controller instanceof \FrontController) {
                    $this->context->controller->addColorsToProductList($products);
                }

                $product = array_shift($products);
    
                if (isset($product['id_product_attribute']) &&
                    $product['id_product_attribute'] &&
                    isset($product['product_attribute_minimal_quantity'])) {
                    $product['minimal_quantity'] = $product['product_attribute_minimal_quantity'];
                }
    
                return $product;
            }
        }

        return null;
    }
}
