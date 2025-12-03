<?php

use common\models\snomed\SnomedProcedimientos;

?>

<table>
    <tr>
        <td colspan="2" style="width: 20%;border: 1px solid black;text-align:center;">
            <b>MINISTERIO DE SALUD</b>
            <p style="text-align:center;">SANTIAGO DEL ESTERO</p>

        </td>
        <td colspan="2" style="width: 20%;border: 1px solid black;text-align:center; font-size:60px">
            <strong>9</strong>
        </td>
        <td colspan="6" style="width: 60%;border: 1px solid black; text-align:center">
            <strong>RESUMEN MENSUAL ODONTOLÓGICO</strong>
        </td>
    </tr>

    <tr>
        <td colspan="8" style="width: 70%;border: 1px solid black; text-align:left;">

            <b>Establecimiento: </b><?= $nombreEfector ?>

        </td>
        <td colspan="2" style="width: 30%;border: 1px solid black;text-align:left;">
            <b>Fecha: </b> <?= $mes ?> / <?= $anio ?>
        </td>

    </tr>
    <tr>
        <td colspan="4" style="width: 34%;border: 1px solid balck; text-align:left;">

            <b>SERVICIO:</b> <?= $nombreServicio ?>

        </td>
        <td colspan="3" style="width: 33%;border: 1px solid balck;text-align:left;">
            <b> DPTO : </b> <?= $nombreDepartamento ?>
        </td>
        <td colspan="3" style="width: 33%;border: 1px solid balck;text-align:left;">
            <b> Zona Sanitaria </b>
        </td>
    </tr>
</table>




<table>
    <tr style="height:12pt">
        <td style="width:212pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt" colspan="2">
            <p class="s5" style="font-weight: bold;line-height: 11pt;text-align: center;">PRESTACIONES FINALES</p>
        </td>
        <td style="width:85pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt" rowspan="2">
            <p class="s5" style="font-weight: bold;padding-top: 6pt;text-align: center;">FACTOR DE PONDERACIÓN EN U.C</p>
        </td>
        <td style="width:128pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt" rowspan="2">
            <p class="s5" style="font-weight: bold;line-height: 12pt;text-align: center;">TOTAL PONDERADO DE UNIDADES ODONTOLÓGICAS REALIZADAS (11 Y 12)</p>
        </td>
        <td style="width:148pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt" rowspan="2">
            <p style="padding-top: 7pt;text-indent: 0pt;text-align: left;">
                <br />
            </p>
            <p class="s5" style="font-weight: bold;text-align: center;">OBSERVACIONES</p>
        </td>
    </tr>
    <tr style="height:35pt">
        <td style="width:128pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br />
            </p>
            <p class="s5" style="font-weight: bold;text-align: center;">CÓDIGOS</p>
        </td>
        <td style="width:84pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br />
            </p>

            <p class="s5" style="font-weight: bold;padding-left: 1pt;text-indent: 0pt;text-align: center;">TOTAL</p>
        </td>
    </tr>
    <?php

    foreach ($resultados as $record) { ?>
        <tr>
            <td style="width:128pt;border: 1px solid black; padding: 5px; text-align: center;">
                <?= $record['codigo'] . " - " . SnomedProcedimientos::getTerm($record['codigo']) ?>
            </td>
            <td style="width:84pt;border: 1px solid black;text-align: center;">
                <?= $record['cantidad'] ?>
            </td>
            <td style="width:85pt;border: 1px solid black;">
                <p style="text-indent: 0pt;text-align: left;">
                    <br />
                </p>
            </td>
            <td style="width:128pt;border: 1px solid black;">
                <p style="text-indent: 0pt;text-align: left;">
                    <br />
                </p>

            </td>
            <td style="width:148pt;border: 1px solid black;">
                <p style="text-indent: 0pt;text-align: left;">
                    <br />
                </p>
            </td>
        </tr>
    <?php } ?>

    <!--tr style="height:27pt">
        <td style="width:128pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
            <p style="padding-left: 18pt;text-indent: 0pt;text-align: left;"><span><table border="0" cellspacing="0" cellpadding="0"><tr><td><img width="137" height="26" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIkAAAAaCAYAAACD1n8kAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABtUlEQVRoge3bz2pTQRhA8d9tbm2bKriyiitb6FbalaiPUiz0SfRFChUfRcWV4lZQX0Hxv20aFzMXLgWZeDMRI9+BIVmEw9zJSVbfNPiJD/ihHg3WcQWf8B3Tf9i7rO5F7hnWcLXFRzzCa5xWkq/jDo7wBE/xtYJ3A/fwACd4jm8VvJ37Lg4X7H6MZ5XcY9zHAY7xQgqlBqu4jYetFMYbvJT+VWqwgWvSQbzHK3y+8Jmp9Evoym9m8G7iRva+lfbcj69zDmET17P7Xd7zl4Gui4yx9YfuWZ7lMm72vP3z6M526Hlckr7H0zaLJjjLqwaTvKY4r+g+y77a3mV1972T3qrBSnZNVyoJg/+YiCQoEpEERSKSoEhEEhSJSIIiEUlQJCIJikQkQZGIJCgSkQRFIpKgSEQSFIlIgiIRSVAkIgmKRCRBkYgkKBKRBEVaaVB2lN+fV/KO8mqkENu8+gwZhG6z73feeQZ/S+55GOKe5Vn63lFvMf8gdJtdTSuNzu9KE9c1r1RsS9PWt7Cn3pWKzruDfXWvPWz3Xvcqu3cW4B5L59t599W9UrGL1UZczlpm91+5nPUL6EqHO5qt0uAAAAAASUVORK5CYIIA"/></td></tr></table></span></p>
        </td>
        <td style="width:84pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
        </td>
        <td style="width:85pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
            <p style="padding-left: 23pt;text-indent: 0pt;text-align: left;"><span><table border="0" cellspacing="0" cellpadding="0"><tr><td><img width="81" height="22" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFEAAAAWCAYAAAC40nDiAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABA0lEQVRYhe3ZsUrDUBSH8V/am7oJARc3ty76Uj6JPofP5KSLg6OboK62tQ43Qqm1FDySIOeDkHATTv73407nNHjHS38fG1Mco8EbVsPG+cYMXcErrnCHxZCJtmhwiku0uMET1kOG2qDFBa6LKu4Bt4aRuFaF7eIMzzjCPR4PrGej5q76+/55KK16EhelX/jAsr/GxErd8Nr48jWqN5OBg/wLUmIAKTGAlBhASgwgJQaQEgNIiQGkxABSYgApMYCUGEBKDCAlBpASA/jqJ0765yG6xvsapNP+XaPmKz98t12Pv2/KFv0hLGqHdq7OWMY4HjhRM56jM67xwBxtIwdVv2GG7hPvbC5BQTHcHQAAAABJRU5ErkJgggAA"/></td></tr></table></span></p>
        </td>
        <td style="width:128pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
            <p style="padding-left: 21pt;text-indent: 0pt;text-align: left;"><span><table border="0" cellspacing="0" cellpadding="0"><tr><td><img width="141" height="24" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAI0AAAAYCAYAAADH9X5VAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAB6ElEQVRoge3bTWoUQRjG8V/PdILRYMwqCn6QVRaKC88g4h28gndQvIEIXsF7uNeFKOhK3YjgQoX4QZLJuKhqZhgap4ukZqHvH4quHnrqqXrrqV493eAAX/O1JiOcxQa+r0BvjE20We+osl6Lrayzj8kK9Lbz9Rt+Y1pZcx3bneADvMZhRcHzuIs7eII36m7kDu7hIp7io3pFbbCL+/iAZ/hSSYt0AK/hIa7iMZ5LxqnFGm7gUSsZ5R1e5P5UKkLH4v0i3Ub87RnSqbiOH3N6nWmWaSyjbw6XcRtnJIO+HTBGM3Aui880+Cm90T7jFT4NmfhA5s3eSKbZxy8c4z1e5vvFOZ60tt1Y65JxDtv8w0TawJon/0ha4Cr1prnV1mqkNR3nVltvZLY+VlfPCaajiiLBP0qYJigmTBMUE6YJignTBMWEaYJiwjRBMWGaoJgwTVBMmCYoJkwTFBOmCYoJ0wTFhGmCYsI0QTFhmqCYME1QTCulzsa53yXdasQ9W8mk83pDNZbRN4dubU3ut4t/6hnjJHHPsbS+0UC9Evrint36mNWzr6anFfdss07TSrnPPSkfXDtYfgXnsl7teOJObhekbPKm+sHyLSnIfhOXKmkxC5Zv5P4ubqkfLN/DWiM+YTkt/ptPWP4AWkR75wmJmJQAAAAASUVORK5CYIIA"/></td></tr></table></span></p>
        </td>
        <td style="width:148pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
        </td>
    </tr-->
    <tr style="height:24pt">
        <td style="width:297pt;border-top-style:solid;border-top-width:1pt;border-right-style:solid;border-right-width:1pt" colspan="3">
            <p style="text-indent: 0pt;text-align: left;">
                <br />
            </p>
        </td>
        <td style="width:128pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">

            <p class="s6" style="padding-left: 6pt;text-indent: 0pt;text-align: left;">TOTAL:</p>
        </td>
        <td style="width:148pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br />
            </p>
        </td>
    </tr>
</table>
<p style="padding-top: 1pt;padding-left: 5pt;text-indent: 0pt;text-align: left;">Firma del Medico:</p>