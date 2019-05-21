$(function () {
    let $scope = $('form');

    let previewImageForFileInput = function ($fileInput, $previewElement, $cloneToElement = null) {
        if (!$fileInput.get(0).files.length) {
            return;
        }

        let file = $fileInput.get(0).files[0];
        if (file.type.indexOf('image') === -1) {
            $(this).val('');
            alert('not an image');
            return;
        }

        let fileReader    = new FileReader();
        fileReader.onload = function (e) {
            let $pe = $previewElement;
            if ($cloneToElement && $cloneToElement.length) {
                $pe = $previewElement.clone();
                $pe.appendTo($cloneToElement);
            }
            $pe.find('img:first').attr('src', e.target.result).end().show();
        };
        fileReader.readAsDataURL(file);
    };


    $('.media-edit, .btn-upload', $scope).off('click.uploadfile').on('click.uploadfile', function () {
        $(this).closest('.row').find('span.form-control input[type="file"]').click();
    });

    $('span.form-control input[type="file"]', $scope).off('change').on('change', function () {
        if (!$(this).get(0).files.length) {
            return;
        }
        let $mediaBox = $(this).closest('.row').find('.image-preview .media-box');
        let $btnUpload = $(this).closest('.row').find('.btn-upload');
        if (!$mediaBox.length) {
            console.warn('no near ".media-box"');
            return;
        }
        previewImageForFileInput($(this), $mediaBox);
        $mediaBox.removeClass('d-none');
        $btnUpload.remove();
    });
});