window.addEventListener('load',function(){ 

    $('.cambiar_estado_turno').click(function(e) {
        e.preventDefault();
        let parent = $(this).parent();
        sweetAlertConfirm($(this).attr('alert_title'))
            .then((result) => {
                if (result.isConfirmed) {
                    let url = yii.getBaseCurrentUrl() + $(this).attr('href');

                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: JSON.parse($(this).attr('post_data')),
                        success: function (data) {                                
                            alertaFlotante('Listo', 'success');
                            parent.html(data.msg);
                        },
                        error: function () {
                            alertaFlotante('Ocurrió un error', 'danger');
                        }
                    });
                }
            });
    });        

})