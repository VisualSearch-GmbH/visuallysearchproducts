{*
*
* (c) VisualSearch GmbH <office@visualsearch.at>
* For the full copyright and license information, please view the LICENSE
* file that was distributed with the source code.
*
*}
{extends file='page.tpl'}

{block name='page_header_container'}{/block}

{block name='page_content'}
    <div id="visual_search" class="visual-search">
        <h1 class="visual-search--title">{l s='Visual Search' mod='visuallysearchproducts'}</h1>
        <p class="visual-search--info">{l s='You are just a step away from finding your product' mod='visuallysearchproducts'}</p>
        <div id="products_list" class="visual-search--products-list"></div>
        <div id="file_uploader" class="visual-search--file-uploader">
            <div id="drag_drop_area" class="visual-search--drag-drop-area" data-ajax_url="{$visual_search.ajax_url}" data-locale="{$visual_search.locale}"></div>
        </div>
    </div>
{/block}
