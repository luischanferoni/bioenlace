<?php
use common\models\ConsultaOdontologiaEstados;

?>

<?php 
    foreach($piezas as $pieza) { 
        $primerDigito = intval(substr($pieza, 0, 1)) - 1;
        $caras = ConsultaOdontologiaEstados::CARAS[$primerDigito];
        $segundoDigito = intval(substr($pieza, 1, 1));
        $centro = ($segundoDigito <= 3) ? ConsultaOdontologiaEstados::CENTROS[0] : ConsultaOdontologiaEstados::CENTROS[1];
    ?>
    <div class="vstack text-center">
        <span class="numero_pieza" style="font-size: 18px;" data-span_pieza="<?= $pieza?>"><?= $pieza?></span>  
        <div>
            <svg cursor="pointer" data-id_pieza="<?= $pieza?>" pointerEvents="all" class="svg_pieza_chica" width="50px" height="50px">
                <path data-parte="<?= strtolower($caras["DERE"])?>" 
                        stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px"
                        style="transform-box: fill-box; transform-origin: 50% 50%;" 
                        transform="matrix(1.618895, 1.56335, -1.530377, 1.584752, -369.902344, -276.025543)" 
                        d="M 407 296.56 A 10.44 10.44 0 0 1 417.44 307 L 413.264 307 A 6.264 6.264 0 0 0 407 300.736 Z" 
                        bx:shape="pie 407 307 6.264 10.44 0 90 1@1475414b"/>
                <path data-parte="<?= strtolower($caras["ABAJO"])?>"
                        stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px"
                        style="transform-box: fill-box; transform-origin: 50% 50%;" 
                        transform="matrix(1.618895, 1.56335, -1.530377, 1.584752, -385.879486, -269.920746)" 
                        d="M 417.44 307 A 10.44 10.44 0 0 1 407 317.44 L 407 313.264 A 6.264 6.264 0 0 0 413.264 307 Z" bx:shape="pie 407 307 6.264 10.44 90 180 1@59ac21a7"/>
                <path data-parte="<?= strtolower($caras["IZQ"])?>"
                        stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px"
                        style="transform-box: fill-box; transform-origin: 50% 50%;" 
                        transform="matrix(1.618895, 1.56335, -1.530377, 1.584752, -392.34079, -286.242096)" 
                        d="M 407 317.44 A 10.44 10.44 0 0 1 396.56 307 L 400.736 307 A 6.264 6.264 0 0 0 407 313.264 Z" bx:shape="pie 407 307 6.264 10.44 180 270 1@91955ee2"/>
                <path data-parte="<?= strtolower($caras["ARRIBA"])?>"
                        stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="0.5px"
                        style="transform-box: fill-box; transform-origin: 50% 50%;" 
                        transform="matrix(1.618895, 1.56335, -1.530377, 1.584752, -376.363647, -292.346893)" 
                        d="M 396.56 307 A 10.44 10.44 0 0 1 407 296.56 L 407 300.736 A 6.264 6.264 0 0 0 400.736 307 Z" bx:shape="pie 407 307 6.264 10.44 270 360 1@18f09e8f"/>
                <ellipse data-parte="<?= strtolower($centro)?>"
                        stroke="rgb(0, 0, 0)" fill="rgb(255, 255, 255)" stroke-width="1.2px"
                        style="transform-box: fill-box; transform-origin: 50% 50%;" 
                        cx="20" cy="23" rx="8" ry="8" 
                        transform="matrix(0.719339, 0.694659, -0.694659, 0.719339, 5.878411, 2.866092)"/>
            </svg>
        </div>
    </div>
<?php } ?>