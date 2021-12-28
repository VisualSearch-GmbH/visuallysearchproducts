<?php
/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 * @author VisualSearch GmbH
 * @copyright VisualSearch GmbH
 * @license MIT License
 */

require_once dirname(__FILE__).'/../../classes/VisuallySearchProductsFrontController.php';

class VisuallySearchProductsStatusVersionModuleFrontController extends VisuallySearchProductsFrontController
{
    public function init()
    {
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        die(json_encode(["code" => 200, "message" => "Info VisVisuallySearchProducts: V" . $this->getVersion()]));
    }
}
