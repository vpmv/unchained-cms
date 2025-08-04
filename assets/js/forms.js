const $groupedSelects    = [];
const $conditionalFields = [];

function updateOptgroup($select, value)
{
    $select.selectpicker('destroy');

    let label = '[label="' + value + '"]';
    $('optgroup', $select).not(label).prop('disabled', true).children('option').prop({disabled: true, selected: false});
    $('optgroup' + label, $select).prop('disabled', false).children('option').prop({disabled: false, selected: false});


    $select.prop('disabled', $('optgroup', $select).length === $('optgroup:disabled', $select).length);

    $select.selectpicker();
}

function updateConditionalField($master, $elem)
{
    let isSelect = $elem.is('select');
    if (isSelect) {
        $elem.selectpicker('destroy');
    }

    let val = '';
    if ($master.is('select')) {
        val = $('option:selected', $master).val()
    } else if ($master.is('checkbox')) {
        val = $master.prop('checked');
    } else {
        val = $master.val();
    }

    $elem.prop('disabled', !val);
    if (isSelect) {
        $elem.selectpicker();
    }
}

// change events only registers once
function registerSelectMaster(groupId, $child)
{
    $groupedSelects.push($child);

    let $master  = $('select#form_' + groupId)
    let groupVal = $('option:selected', $master).text();
    $master.on('change', function () {
        let groupVal = $('option:selected', $master).text();

        $groupedSelects.forEach(function ($select) {
            updateOptgroup($select, groupVal);
        })
    });

    updateOptgroup($child, groupVal);
}

function registerConditionMaster(conditionId, $child)
{
    if ($conditionalFields.indexOf(conditionId) === -1) {
        $conditionalFields[conditionId] = [];
    }
    $conditionalFields[conditionId].push($child);

    let $master = $('#form_' + conditionId)
    $master.on('change', function () {
        let val = $('option:selected', $master).val();
        $conditionalFields[conditionId].forEach(function ($elem) {
            updateConditionalField($master, $elem);
        });
    });
    updateConditionalField($master, $child);
}

$(function () {
    let $scope = $('form');
    $('.selectpicker').selectpicker();

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
        let $mediaBox  = $(this).closest('.row').find('.image-preview .media-box');
        let $btnUpload = $(this).closest('.row').find('.btn-upload');
        if (!$mediaBox.length) {
            console.warn('no near ".media-box"');
            return;
        }
        previewImageForFileInput($(this), $mediaBox);
        $mediaBox.removeClass('d-none');
        $btnUpload.remove();
    });

    $('select[data-group]').each(function () {
        let $child  = $(this);
        let groupId = $child.attr('data-group');

        if (typeof groupId === 'string' && groupId !== 'true') {
            registerSelectMaster(groupId, $child);
        }
    })

    $('select[data-condition]').each(function () {
        let $child      = $(this);
        let conditionId = $child.attr('data-condition');

        if (typeof conditionId === 'string' && conditionId !== 'true') {
            registerConditionMaster(conditionId, $child);
        }
    });
});