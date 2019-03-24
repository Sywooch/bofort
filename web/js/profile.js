$(document).ready(function() {

    var profileBlock = $('.profileBlock');

    function updateContainer(actionName) {
        $.ajax({
            url: '/user/get'+actionName,
            type: 'GET',
            success: function (data) {
                $('.profileBlock').html(data);
            }
        })
    }

    $(".list-group-item").on('click', function(){
        $('.active').removeClass('active');
        $(this).addClass('active');
    });

    $('.profileMenu li').on('click', function(){
        var actionName = $(this).data('container');
        updateContainer(actionName);
    });

    profileBlock.on('click', '.mainCard', function(){
         var id = $(this).data('id');

         $.ajax({
            url: '/user/change-card-state',
            data: {
                'id': id,
                'state': 1
            },
             success: function (data) {
                 if (data.result === false) alert('Ошибка!');
                 else profileBlock.html(data);
             }
         });
    });

    profileBlock.on('click', '.removeCard', function(){
        var id = $(this).data('id');

        $.ajax({
            url: '/user/remove-card',
            data: {
                'id': id,
            },
            success: function (data) {
                if (data.result === false) alert('Ошибка!');
                else profileBlock.html(data);
            }
        });
    });

    profileBlock.on('click', '#addNewCard', function() {
        $('#add-new-card-modal').modal('show');
    });

    profileBlock.on('click', '#confirm-add-new-card', function() {
        $('#add-new-card-modal').modal('hide');

        var widget = new cp.CloudPayments();
        widget.charge({
                publicId: cloud_id,
                description: 'Привязка карты',
                amount: 1,
                currency: 'RUB',
                invoiceId: 111111,
                accountId: user_id,
            },
            function (options) {
                updateContainer('cards');
            },
            function (reason, options) {
                alert(reason);
            });
    });


    profileBlock.on('submit', '#confirm-phone', (function(e) {
        e.preventDefault();

        var form = $(this);
        var url = form.attr('action');

        $.ajax({
            type: "POST",
            url: url,
            data: form.serialize(),
            success: function(data) {
                if (data.success === true) {
                    updateContainer('account');
                } else {
                    $('#code-error').html('Неверный код!');
                }
            }
        });

    }));

    profileBlock.on('submit', '#account-form', function (e) {
        e.preventDefault();

        var form = $(this);
        $.ajax({
            url: '/default/account-edit',
            type: "POST",
            data: form.serialize(),
            success: function (data) {
                $('#phone-code .modal-content').html(data);
                $('#phone-code').modal('show');
            }
        });
    });

    // jQuery.fn.preventDoubleSubmission = function() {
    //     $(this).on('submit',function(e){
    //         var $form = $(this);
    //
    //         if ($form.data('submitted') === true) {
    //             e.preventDefault();
    //         } else {
    //             $form.data('submitted', true);
    //             var url = $form.attr('action');
    //
    //             $.ajax({
    //                 type: "POST",
    //                 url: url,
    //                 data: $form.serialize(),
    //                 success: function(data) {
    //                     $('#phone-code .modal-content').html(data);
    //                     $('#phone-code').modal({show:true});
    //                 }
    //             });
    //         }
    //     });
    //
    //     return this;
    // };
    //
    // $("#account-form").preventDoubleSubmission();
});




