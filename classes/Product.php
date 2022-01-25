<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

namespace VisuallySearchProducts\PrestaShop17;

use \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use \PrestaShop\PrestaShop\Adapter\Presenter\Object\ObjectPresenter;
use \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductPresenter;
use \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use \PrestaShop\PrestaShop\Core\Product\ProductExtraContentFinder;
use \PrestaShop\PrestaShop\Core\Product\ProductPresentationSettings;

class Product
{
    /**
     * @var \Product
     */
    protected $product;

    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var ObjectPresenter
     */
    protected $objectPresenter;

    /**
     * @var ProductPresentationSettings
     */
    protected $presentationSettings;

    /**
     * @var ProductPresenter
     */
    protected $presenter;

    /**
     * @var ProductExtraContentFinder
     */
    protected $extraContentFinder;

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
        $this->product = new \Product(
            (int)$productId,
            true,
            $context->language->id,
            $context->shop->id
        );

        $this->context = $context;
        $this->objectPresenter = new ObjectPresenter();

        $presenterFactory = new \ProductPresenterFactory(
            $this->context,
            new \TaxConfiguration()
        );

        $this->presentationSettings = $presenterFactory->getPresentationSettings();
        $this->presenter = $presenterFactory->getPresenter();
        $this->extraContentFinder = new ProductExtraContentFinder();
        $this->module = $module;
    }

    /**
     * @param int $checkedIdProductAttribute
     *
     * @return int
     */
    protected function tryToGetAvailableIdProductAttribute($checkedIdProductAttribute)
    {
        if (!\Configuration::get('PS_DISP_UNAVAILABLE_ATTR')) {
            $availableProductAttributes = $this->product->getAttributeCombinations();

            if (!\Product::isAvailableWhenOutOfStock($this->product->out_of_stock)) {
                $availableProductAttributes = array_filter(
                    $availableProductAttributes,
                    function ($elem) {
                        return $elem['quantity'] > 0;
                    }
                );
            }
            
            $availableProductAttribute = array_filter(
                $availableProductAttributes,
                function ($elem) use ($checkedIdProductAttribute) {
                    return $elem['id_product_attribute'] == $checkedIdProductAttribute;
                }
            );

            if (empty($availableProductAttribute) && count($availableProductAttributes)) {
                return (int)array_shift($availableProductAttributes)['id_product_attribute'];
            }
        }

        return $checkedIdProductAttribute;
    }

    /**
     * @param int $combinationId
     *
     * @return array|null
     */
    protected function findProductCombinationById($combinationId)
    {
        $foundCombination = null;
        $combinations = $this->product->getAttributesGroups($this->context->language->id);
        foreach ($combinations as $combination) {
            if ((int)($combination['id_product_attribute']) === $combinationId) {
                $foundCombination = $combination;
                break;
            }
        }

        return $foundCombination;
    }

    /**
     * @param array $product
     *
     * @return int
     */
    protected function getProductMinimalQuantity(array $product)
    {
        $minimalQuantity = 1;

        if ($product['id_product_attribute']) {
            $combination = $this->findProductCombinationById($product['id_product_attribute']);
            if ($combination['minimal_quantity']) {
                $minimalQuantity = $combination['minimal_quantity'];
            }
        } else {
            $minimalQuantity = $this->product->minimal_quantity;
        }

        return (int)$minimalQuantity;
    }

    /**
     * @param array $product
     *
     * @return int
     */
    protected function getRequiredQuantity(array $product)
    {
        $requiredQuantity = $this->getProductMinimalQuantity($product);
        if ($requiredQuantity < (int)$product['minimal_quantity']) {
            $requiredQuantity = (int)$product['minimal_quantity'];
        }

        return $requiredQuantity;
    }

    /**
     * @param array $productFull
     *
     * @return array
     */
    protected function addProductCustomizationData(array $productFull)
    {
        if ($productFull['customizable']) {
            $customizationData = array(
                'fields' => array(),
            );

            $customizedData = array();

            $alreadyCustomized = $this->context->cart->getProductCustomization(
                $productFull['id_product'],
                null,
                true
            );

            $customizationId = 0;

            foreach ($alreadyCustomized as $customization) {
                $customizationId = $customization['id_customization'];
                $customizedData[$customization['index']] = $customization;
            }

            $customizationFields = $this->product->getCustomizationFields($this->context->language->id);
            if (is_array($customizationFields)) {
                foreach ($customizationFields as $customizationField) {
                    // 'id_customization_field' maps to what is called 'index'
                    // in what Product::getProductCustomization() returns
                    $key = $customizationField['id_customization_field'];

                    $field = array();
                    $field['label'] = $customizationField['name'];
                    $field['id_customization_field'] = $customizationField['id_customization_field'];
                    $field['required'] = $customizationField['required'];

                    switch ($customizationField['type']) {
                        case \Product::CUSTOMIZE_FILE:
                            $field['type'] = 'image';
                            $field['image'] = null;
                            $field['input_name'] = 'file' . $customizationField['id_customization_field'];
                            break;

                        case \Product::CUSTOMIZE_TEXTFIELD:
                            $field['type'] = 'text';
                            $field['text'] = '';
                            $field['input_name'] = 'textField' . $customizationField['id_customization_field'];
                            break;

                        default:
                            $field['type'] = null;
                    }

                    if (array_key_exists($key, $customizedData)) {
                        $data = $customizedData[$key];
                        $field['is_customized'] = true;

                        switch ($customizationField['type']) {
                            case \Product::CUSTOMIZE_FILE:
                                $imageRetriever = new ImageRetriever($this->context->link);
                                $field['image'] = $imageRetriever->getCustomizationImage(
                                    $data['value']
                                );
                                $field['remove_image_url'] = $this->context->link->getProductDeletePictureLink(
                                    $productFull,
                                    $customizationField['id_customization_field']
                                );
                                break;

                            case \Product::CUSTOMIZE_TEXTFIELD:
                                $field['text'] = $data['value'];
                                break;
                        }
                    } else {
                        $field['is_customized'] = false;
                    }

                    $customizationData['fields'][] = $field;
                }
            }

            $productFull['customizations'] = $customizationData;
            $productFull['id_customization'] = $customizationId;
            $productFull['is_customizable'] = true;
        } else {
            $productFull['customizations'] = array(
                'fields' => array(),
            );

            $productFull['id_customization'] = 0;
            $productFull['is_customizable'] = false;
        }

        return $productFull;
    }

    /**
     * @return float
     */
    protected function getTaxesRate()
    {
        return (float)$this->product->getTaxesRate(
            new \Address(
                (int)$this->context->cart->{\Configuration::get('PS_TAX_ADDRESS_TYPE')}
            )
        );
    }

    /**
     * @param array $specificPrices
     * @param float $price
     * @param float $taxRate
     * @param float $ecotaxAmount
     *
     * @return array
     */
    protected function formatQuantityDiscounts($specificPrices, $price, $taxRate, $ecotaxAmount)
    {
        $priceFormatter = new PriceFormatter();

        foreach ($specificPrices as $key => &$row) {
            $row['quantity'] = &$row['from_quantity'];

            if ($row['price'] >= 0) {
                /** @var float $currentPriceDefaultCurrency current price with taxes in default currency */
                $currentPriceDefaultCurrency = (!$row['reduction_tax'] ?
                    $row['price'] :
                    $row['price'] * (1 + $taxRate / 100)
                ) + (float)$ecotaxAmount;

                $currentPriceCurrentCurrency = \Tools::convertPrice(
                    $currentPriceDefaultCurrency,
                    $this->context->currency,
                    true,
                    $this->context
                );

                if ($row['reduction_type'] == 'amount') {
                    $currentPriceCurrentCurrency -= $row['reduction_tax'] ?
                        $row['reduction'] :
                        $row['reduction'] / (1 + $taxRate / 100);

                    $row['reduction_with_tax'] = $row['reduction_tax'] ?
                        $row['reduction'] :
                        $row['reduction'] / (1 + $taxRate / 100);
                } else {
                    $currentPriceCurrentCurrency *= 1 - $row['reduction'];
                }

                $row['real_value'] = $price > 0 ?
                    $price - $currentPriceCurrentCurrency :
                    $currentPriceCurrentCurrency;

                $discountPrice = $price - $row['real_value'];

                if (\Configuration::get('PS_DISPLAY_DISCOUNT_PRICE')) {
                    if ($row['reduction_tax'] == 0 && !$row['price']) {
                        $row['discount'] = $priceFormatter->format($price - ($price * $row['reduction_with_tax']));
                    } else {
                        $row['discount'] = $priceFormatter->format($price - $row['real_value']);
                    }
                } else {
                    $row['discount'] = $priceFormatter->format($row['real_value']);
                }
            } else {
                if ($row['reduction_type'] == 'amount') {
                    if (\Product::$_taxCalculationMethod == PS_TAX_INC) {
                        $row['real_value'] = $row['reduction_tax'] == 1 ?
                            $row['reduction'] :
                            $row['reduction'] * (1 + $taxRate / 100);
                    } else {
                        $row['real_value'] = $row['reduction_tax'] == 0 ?
                            $row['reduction'] :
                            $row['reduction'] / (1 + $taxRate / 100);
                    }

                    $row['reduction_with_tax'] = $row['reduction_tax'] ?
                        $row['reduction'] :
                        $row['reduction'] + ($row['reduction'] * $taxRate) / 100;

                    $discountPrice = $price - $row['real_value'];

                    if (\Configuration::get('PS_DISPLAY_DISCOUNT_PRICE')) {
                        if ($row['reduction_tax'] == 0 && !$row['price']) {
                            $row['discount'] = $priceFormatter->format($price - ($price * $row['reduction_with_tax']));
                        } else {
                            $row['discount'] = $priceFormatter->format($price - $row['real_value']);
                        }
                    } else {
                        $row['discount'] = $priceFormatter->format($row['real_value']);
                    }
                } else {
                    $row['real_value'] = $row['reduction'] * 100;
                    $discountPrice = $price - $price * $row['reduction'];

                    if (\Configuration::get('PS_DISPLAY_DISCOUNT_PRICE')) {
                        if ($row['reduction_tax'] == 0) {
                            $row['discount'] = $priceFormatter->format($price - ($price * $row['reduction_with_tax']));
                        } else {
                            $row['discount'] = $priceFormatter->format($price - ($price * $row['reduction']));
                        }
                    } else {
                        $row['discount'] = $row['real_value'] . '%';
                    }
                }
            }

            $row['save'] = $priceFormatter->format(
                ($price * $row['quantity']) - ($discountPrice * $row['quantity'])
            );

            $row['nextQuantity'] = isset($specificPrices[$key + 1]) ?
                (int)$specificPrices[$key + 1]['from_quantity'] :
                -1;
        }

        return $specificPrices;
    }
    
    /**
     * @return array
     */
    protected function getQuantityDiscounts()
    {
        $customerId = isset($this->context->customer) ? (int)$this->context->customer->id : 0;
        $groupId = (int)\Group::getCurrent()->id;
        $countryId = $customerId ? (int)\Customer::getCurrentCountry($customerId) : (int)\Tools::getCountry();
        $tax = $this->getTaxesRate();
        $productPriceWithTax = \Product::getPriceStatic($this->product->id, true, null, 6);

        if (\Product::$_taxCalculationMethod == PS_TAX_INC) {
            $productPriceWithTax = \Tools::ps_round($productPriceWithTax, 2);
        }

        $currencyId = (int)$this->context->cookie->id_currency;
        $productId = (int)$this->product->id;

        $productAttributeId = $this->tryToGetAvailableIdProductAttribute(
            (int)\Product::getDefaultAttribute($this->product->id)
        );

        $shopId = $this->context->shop->id;

        $quantityDiscounts = \SpecificPrice::getQuantityDiscounts(
            $productId,
            $shopId,
            $currencyId,
            $countryId,
            $groupId,
            $productAttributeId,
            false,
            (int)$this->context->customer->id
        );
        
        foreach ($quantityDiscounts as &$quantityDiscount) {
            if ($quantityDiscount['id_product_attribute']) {
                $combination = new \Combination((int)$quantityDiscount['id_product_attribute']);
                $attributes = $combination->getAttributesName((int)$this->context->language->id);

                foreach ($attributes as $attribute) {
                    $quantityDiscount['attributes'] = $attribute['name'] . ' - ';
                }

                $quantityDiscount['attributes'] = rtrim($quantityDiscount['attributes'], ' - ');
            }

            if (((int)$quantityDiscount['id_currency'] == 0) &&
                ($quantityDiscount['reduction_type'] == 'amount')) {
                $quantityDiscount['reduction'] = \Tools::convertPriceFull(
                    $quantityDiscount['reduction'],
                    null,
                    \Context::getContext()->currency
                );
            }
        }

        $productPrice = $this->product->getPrice(
            \Product::$_taxCalculationMethod == PS_TAX_INC,
            $productAttributeId
        );

        return $this->formatQuantityDiscounts(
            $quantityDiscounts,
            $productPrice,
            (float)$tax,
            $this->product->ecotax
        );
    }

    /**
     * @return ProductLazyArray|null
     */
    public function present()
    {
        if (!\Validate::isLoadedObject($this->product)) {
            return null;
        }

        $product = $this->objectPresenter->present($this->product);
        $product['id_product'] = (int)$this->product->id;
        $product['out_of_stock'] = (int)$this->product->out_of_stock;
        $product['new'] = (int)$this->product->new;
        
        $product['id_product_attribute'] = $this->tryToGetAvailableIdProductAttribute(
            (int)\Product::getDefaultAttribute($this->product->id)
        );
        
        $product['minimal_quantity'] = $this->getProductMinimalQuantity($product);
        $product['quantity_wanted'] = $this->getRequiredQuantity($product);

        $product['extraContent'] = $this->extraContentFinder->addParams(
            array('product' => $this->product)
        )->present();
        
        $product['ecotax'] = \Tools::convertPrice(
            (float)$product['ecotax'],
            $this->context->currency,
            true,
            $this->context
        );
        
        $productFull = \Product::getProductProperties($this->context->language->id, $product, $this->context);
        $productFull = $this->addProductCustomizationData($productFull);

        $productFull['show_quantities'] = (bool)(
            \Configuration::get('PS_DISPLAY_QTIES') &&
            \Configuration::get('PS_STOCK_MANAGEMENT') &&
            ($this->product->quantity > 0) &&
            $this->product->available_for_order &&
            !\Configuration::isCatalogMode()
        );

        $productFull['quantity_label'] = ($this->product->quantity > 1) ?
            $this->module->l('Items', 'Product') :
            $this->module->l('Item', 'Product');
        
        $productFull['quantity_discounts'] = $this->getQuantityDiscounts();

        if ($productFull['unit_price_ratio'] > 0) {
            $unitPrice = ($this->productSettings->include_taxes) ?
                $productFull['price'] :
                $productFull['price_tax_exc'];
            
            $productFull['unit_price'] = $unitPrice / $productFull['unit_price_ratio'];
        }

        $groupReduction = \GroupReduction::getValueForProduct(
            $this->product->id,
            (int)\Group::getCurrent()->id
        );

        if ($groupReduction === false) {
            $groupReduction = \Group::getReduction((int) $this->context->cookie->id_customer) / 100;
        }

        $productFull['customer_group_discount'] = $groupReduction;
        
        $productFull['rounded_display_price'] = \Tools::ps_round(
            $productFull['price'],
            \Context::getContext()->currency->precision
        );
        
        return $this->presenter->present(
            $this->presentationSettings,
            $productFull,
            $this->context->language
        );
    }
}
