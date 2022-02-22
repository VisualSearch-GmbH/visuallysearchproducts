<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

class VisuallySearchProductsFrontController extends ModuleFrontController
{
    /**
     * @return bool
     */
    protected function checkAuthorization()
    {
        $keys = Db::getInstance()->ExecuteS('SELECT * FROM ' . _DB_PREFIX_ . 'visually_search_products' . ' LIMIT 1');

        foreach ($keys as $key => $token) {
            if (strcmp($token['id_visually_search_products'], $this->getBearerToken()) == 0) {
                return true;
            }
        }

        #
        # Report error in authorization
        #
        $message = 'Bearer token: '.$this->getBearerToken().'; ';
        $keys = Db::getInstance()->ExecuteS('SELECT * FROM ' . _DB_PREFIX_ . 'visually_search_products' . ' LIMIT 1');
        foreach ($keys as $key => $token) {
            $message = $message.'Database id_visually_search_products: '.$token['id_visually_search_products'].'; ';
        }

        $host = Context::getContext()->shop->getBaseURL(true);
        $url = 'https://api.visualsearch.wien/error_message';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Vis-API-KEY: marketing',
            'Vis-SYSTEM-HOSTS:'.$host,
            'Vis-SYSTEM-KEY:',
            'Vis-SYSTEM-TYPE: visually_search_products;prestashop;error: '.$message,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        return false;
    }

    /**
     * @return string|null
     */
    protected function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    protected function getAuthorizationHeader()
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();

            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    /**
     * @return bool
     *
     * @deprecated
     */
    protected function isLiveMode()
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
     *
     * @deprecated
     */
    protected function getApiKey()
    {
        return (string)Configuration::get(
            'VISUALLY_SEARCH_PRODUCTS_API_KEY',
            null,
            null,
            $this->context->shop->id
        );
    }

    /**
     * @return string
     */
    protected function getVersion()
    {
        return (string)Configuration::get(
            'VISUALLY_SEARCH_PRODUCTS_VERSION',
            null,
            null,
            $this->context->shop->id
        );
    }
}
