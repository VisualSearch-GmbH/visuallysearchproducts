/**
 * (c) VisualSearch GmbH <office@visualsearch.at>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
 */
 (function() {
    function scrollTo(selector, offset, callback) {
        $('html, body').animate({
            scrollTop: $(selector).offset().top + (typeof offset === 'number' ? parseInt(offset) : 0)
        }, 500, callback);
    }

    $(document).ready(function() {
        var $uploader = $('#drag_drop_area');
        if ($uploader.length !== 1) {
            return;
        }

        const uppy = new Uppy.Core({
            id: 'visual_search',
            autoProceed: true,
            allowMultipleUploadBatches: true,
            debug: false,
            restrictions: {
              maxFileSize: null,
              minFileSize: null,
              maxTotalFileSize: null,
              maxNumberOfFiles: 1,
              minNumberOfFiles: 1,
              allowedFileTypes: ['image/*'],
              requiredMetaFields: [],
            },
            meta: {},
            onBeforeFileAdded: function(currentFile, files) {
                return currentFile;
            },
            onBeforeUpload: function(files) {

            },
            locale: Uppy.locales[$uploader.data('locale')],
            logger: Uppy.debugLogger,
            infoTimeout: 5000,
        });

        uppy.use(Uppy.Dashboard, {
            inline: true,
            target: '#drag_drop_area',
            hidePauseResumeButton: true,
            disableThumbnailGenerator: true,
            proudlyDisplayPoweredByUppy: false,
        });

        uppy.use(Uppy.XHRUpload, {
            endpoint: $uploader.data('ajax_url'),
            fieldName: 'visual_search_file'
        });

        uppy.on('upload-success', function(file, response) {
            if ((typeof response.body.reload !== 'undefined') && response.body.reload) {
                document.location.reload();
            } else {
                $('#products_list').replaceWith(response.body.products_list);

                reloadProductComparison();
                compareButtonsStatusRefresh();
                totalCompareButtons();

                setTimeout(function() {
                    scrollTo('#products_list', 0, function() {
                        $('#products_list').animate({opacity: 1}, 750, function() {
                            uppy.reset();
                        });
                    });
                }, 250);
            }
        });
    }).on('click', '#choose_another_photo', function(e) {
        e.preventDefault();
        scrollTo($(this).attr('href'), -30);
    });
}());
