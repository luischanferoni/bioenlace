  $(function() {
      $("#referencia-id_motivo_derivacion").change(function () {
    if ($("#referencia-id_motivo_derivacion").val() == 1 || $("#referencia-id_motivo_derivacion").val() == 2) {
        $("#div_estcomp").hide("slow");
    } else {
        $("#div_estcomp").show("slow");
    }
});

$("input[name='Referencia[tratamiento_previo]']:radio").change(function () {
    if($("input[name='Referencia[tratamiento_previo]']:checked").val() == "SI"){
//        alert($("input[name='Referencia[tratamiento_previo]']:radio").val());
        $("#div_tratamiento").show("slow");
    }else{
//        alert($("input[name='Referencia[tratamiento_previo]']:radio").val());
        $("#div_tratamiento").hide("slow");
    }
});


if ($("#referencia-estudios_complementarios").val() != ''){
      $("#div_estcomp").show("slow");
};
if ($("#referencia-tratamiento").val() != ''){
      $("#div_tratamiento").show("slow");
};

})

