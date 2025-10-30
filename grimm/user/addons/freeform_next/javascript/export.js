(function ($) {
    var context = $('#export-csv-modal');
    if (context.data('initiated')) return;
    context.data('initiated', true);

    $('.checkbox-select', context).each(function () {
        if (!$(this).data('dragger')) $(this).data('dragger', true);
        // if you need sorting here, ensure jQuery UI sortable is loaded and call .sortable()
    });

    var modal = $('#export-modal-wrapper');
    $('.btn.submit', modal).on('click', function () {
        modal.data('modal') && modal.data('modal').hide();
    });
    $('.btn.cancel', modal).on('click', function () {
        modal.data('modal') && modal.data('modal').hide();
    });

    var formSelector = $('select[name=form_id]', context);
    formSelector.on('change', function () {
        var val = $(this).val();
        $('.form-field-list').addClass('hidden');
        $('.form-field-list[data-id="' + val + '"]').removeClass('hidden');
    });
})(jQuery);

