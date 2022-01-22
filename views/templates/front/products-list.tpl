{*
*
* (c) VisualSearch GmbH <office@visualsearch.at>
* For the full copyright and license information, please view the LICENSE
* file that was distributed with the source code.
*
*}
<div id="products_list" class="visual-search--products-list" style="opacity: 0;">
    <h2 class="visual-search--products-list-head">
        <span>{l s='Searching results' mod='visuallysearchproducts'} <span class="visual-search--products-count">({$products|count})</span></span>
        <a id="choose_another_photo" href="#file_uploader" class="visual-search--choose-another-photo">{l s='Choose another photo' mod='visuallysearchproducts'}</a>
    </h2>
    <section id="products" class="visual-search--products-list-body">
        {if $products|count}
            <div id="js-product-list" class="visual-search--js-product-list">
                <div class="products row">
                    {foreach $products as $product}
                        {include file='catalog/_partials/miniatures/product.tpl' product=$product}
                    {/foreach}
                </div>
            </div>
        {else}
            <div class="visual-search--not-found">
                <p class="visual-search--not-found-message">{l s='We couldn\'t find a match for the uploaded photo. Try another photo.' mod='visuallysearchproducts'}</p>
            </div>
        {/if}
    </section>
</div>
