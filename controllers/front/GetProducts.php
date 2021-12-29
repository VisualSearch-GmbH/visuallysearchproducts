<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

require_once 'category.php';
require_once dirname(__FILE__).'/../../classes/VisuallySearchProductsFrontController.php';

class VisuallySearchProductsGetProductsModuleFrontController extends VisuallySearchProductsFrontController
{
    public function init()
    {
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        if (!$this->checkAuthorization()) {
            die(json_encode(["message" => "Authorization failed", "code" => 500]));
        }

        if (!$this->isLiveMode()) {
            die(json_encode(["message" => "Not in live mode", "code" => 500]));
        }

        // search for products
        $products = Product::getProducts($this->context->language->id, 0, -1, 'id_product', 'ASC', false, true);

        //
        // Prepare the products for curl request
        //
        $products_list = array();
        if (!empty($products)) {
            foreach ($products as $key => $prod) {
                // Get cover image for your product
                $image = Image::getCover($prod['id_product']);
                // Load Product Object
                $product = new Product($prod['id_product']);
                // Initialize the link object
                $link = new Link();
                // Categories
                $categories = Product::getProductCategoriesFull($prod['id_product']);

                $category_list = array();
                if (!empty($categories)) {
                    foreach ($categories as $cat) {
                        $category_list[] = $cat['name'];
                    }
                }

                $product_ID = $prod['id_product'];
                $product_name = $prod['name'];
                $product_category = $category_list;

                // Only products with valid images
                if ($image['id_image'] > 0) {
                    $image_name = $product->link_rewrite[Context::getContext()->language->id];
                    $product_image = $link->getImageLink(
                        $image_name,
                        $image['id_image'],
                        ImageType::{_VSP_PS16_ ? 'getFormatedName' : 'getFormattedName'}('home')
                    );
                    array_push($products_list, [$product_ID, $product_name, $product_category, '', $product_image]);
                }
            }
        } else {
            die(json_encode(["message" => "No products", "code" => 500]));
        }

        die(json_encode(["message" => "success", "products" => $products_list, "code" => 200]));
    }
}
