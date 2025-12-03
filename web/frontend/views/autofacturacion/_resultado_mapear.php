 <?php if (!isset($autofacturacion)) { ?>
     <div class="text-center">
         <button class="btn btn-primary position-relative py-2 mapear" data-id="<?= $id_consulta ?>">Generar códigos Sumar</button>
     </div>
 <?php } else { ?>

     <table class="table table-striped table-bordered">
         <thead>
             <tr class="bg-success text-white">
                 <th>Beneficiario/s:</th>
                 <th>Codigos:</th>
                 <th></th>
             </tr>
         </thead>
         <tbody>
             <tr class="bg-white">
                 <td>
                     <?php

                        count($beneficiarios) == 1 ? $checked = 'checked' : $checked = '';

                        foreach ($beneficiarios as $beneficiario) { ?>

                         <div class="form-check">
                             <input class="form-check-input <?= $id_consulta . '_radioBeneficiario' ?>" name="<?= $id_consulta . '_radioBeneficiario' ?>" type="radio" id="<?= $id_consulta . '_radioBeneficiario' ?>" value="<?= $beneficiario['clave_beneficiario'] ?>" <?= $checked?>>
                             <label class="form-check-label" for="<?= $id_consulta . '_radioBeneficiario' ?>">
                                 <?= strtoupper($beneficiario['apellido_benef']) . ', ' . strtoupper($beneficiario['nombre_benef']) . ' - ' . $beneficiario['numero_doc'] . ' - ' . $beneficiario['clave_beneficiario'] ?>
                             </label>
                         </div>


                     <?php } ?>

                 </td>
                 <td>
                     <?php
                        $codigos = json_decode($autofacturacion->codigos);

                        count($codigos) == 1 ? $checked2 = 'checked' : $checked2 ='';


                        foreach ($codigos as $codigo) { ?>

                         <div class="form-check">
                             <input class="form-check-input <?= $id_consulta . '_radio' ?>" name="<?= $id_consulta . '_radio' ?>" type="radio" id="<?= $id_consulta . '_radio' ?>" value="<?= $codigo->codigo ?>" <?= $checked2 ?>>
                             <label class="form-check-label" for="<?= $id_consulta . '_radio' ?>">
                                 <?= $codigo->codigo.' - '. $codigo->descripcion ?>
                             </label>
                         </div>

                     <?php } ?>
                 </td>
                 <td class="text-center">
                     <button class="btn btn-primary position-relative py-2 guardar_mapeo" data-id="<?= $id_consulta ?>">Enviar a Sumar</button>
                 </td>
             </tr>
         </tbody>
     </table>

 <?php } ?>