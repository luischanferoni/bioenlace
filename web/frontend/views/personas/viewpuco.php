
<div class="persona-index">
    <ul>
        
    <?php foreach ($coberturas["data"] as $cobertura) { ?>
        
        <li><?=$coberturas["statusCode"] == 200 ? $cobertura["cobertura"] : "No se encontraron coberturas."?></li>
    <?php } ?>
    </ul>
</div>



