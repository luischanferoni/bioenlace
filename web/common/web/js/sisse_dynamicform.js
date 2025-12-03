

    function initSelect2DropStyle(a,b,c) {
        initS2Loading(a,b,c);
    }

    function initSelect2Loading(a,b) {
        //b = $("#" + a).attr("data-s2-options");
        console.log("#");
        initS2Loading(a, b);        
    }

    $(document).ready(function() {
        $(document).on('select2:select', '.snomed_simple_select2', function (e) {
            document.getElementById($(this).attr("id") + "-termino").value = $(this).select2('data')[0].text; 
        });
    });

    $(".dynamicform_wrapper").on("afterInsert", function(e, item) {
        $(item).find(".id").val(null).trigger('change');        
        $(item).find(".snomed_simple_select2").val(null).trigger('change');
        $(item).find(".snomed_simple_select2").removeAttr('readonly');
        $(item).find(".termino").val("");
        $(item).find('.file-preview-thumbnails').html('');
        //jQuery('#consultapracticas-0-archivos_adjuntos').fileinput('destroy');
        //$(item).fileinput(eval($(this).attr('data-krajee-fileinput')));
        let fileInput = $(item).find('[data-krajee-fileinput]');
        if (fileInput.length == 0) {
            return;
        }
        fileInput.fileinput('destroy');
        const fileInputConfig = {
            "initialPreview": [],
            "initialPreviewAsData":true,
            "initialPreviewConfig":[],
            "overwriteInitial":false,
            "fileActionSettings": {
                "showZoom":true,
                "showRemove":true,
                "showRotate":false
            },
            "showCancel":false,
            "showUpload":false,
            "browseClass":"btn btn-soft-primary",
            "removeClass":"btn btn-soft-danger",
            "removeIcon":"\u003Ci class=\u0022bi bi-trash\u0022\u003E\u003C\/i\u003E",
            "language":"es",
            "resizeImage":false,
            "autoOrientImage":true
        };
        fileInput.fileinput(fileInputConfig);        
    });    