<?php

use yii\db\Migration;

/**
 * Contenido informativo inicial (scope producto = global).
 */
class m260819_100100_info_content_seed extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $articles = [
            [
                'topic' => 'representacion',
                'title' => 'Representación y tutela de menores',
                'body' => <<<'MD'
En Bioenlace podés gestionar turnos y atención para otra persona de dos formas:

**Régimen A — Tutela de menores**
Si sos padre, madre o tutor de un menor que no tiene cuenta propia, podés vincularlo a tu cuenta. Esto te permite sacar turnos, ver su historia clínica y gestionar su atención.

Para vincular un menor:
1. Decile al asistente "quiero vincular a mi hijo/a" o usá el botón "Vincular menor".
2. Completá los datos del menor (nombre, DNI, fecha de nacimiento).
3. La solicitud queda pendiente hasta que el centro de salud la verifique.
4. Una vez aprobada, vas a poder operar en nombre del menor.

**Régimen B — Representante designado**
Si necesitás que otra persona con cuenta en Bioenlace pueda operar en tu nombre (por ejemplo, un familiar que te acompañe), podés designarla como representante.

Para designar un representante:
1. Decile al asistente "quiero designar un representante" o usá el botón correspondiente.
2. Indicá el DNI o datos de la persona que querés designar.
3. Esa persona va a poder sacar turnos y realizar gestiones por vos.

En ambos casos, la persona que opera mantiene su propia cuenta y puede alternar entre su identidad y la del representado.
MD,
                'scope' => 'producto',
                'keywords' => 'representacion,tutela,menor,hijo,hija,vincular,representante,delegar,operar por mi,mi nene,mi nena,mi hijo,mi hija',
                'priority' => 10,
            ],
            [
                'topic' => 'teleconsulta',
                'title' => '¿Qué es la teleconsulta?',
                'body' => <<<'MD'
La teleconsulta es una consulta médica por videollamada. Te permite atenderte desde tu casa o cualquier lugar con conexión a internet.

**¿Cuándo puedo usar teleconsulta?**
- Cuando el servicio y el profesional tienen habilitada la modalidad remota.
- Para consultas de seguimiento, control o consulta general.
- No está disponible para estudios, prácticas ni procedimientos que requieran presencia física.

**¿Cómo funciona?**
1. Al sacar turno, si la teleconsulta está disponible vas a ver la opción "Remoto" o "Teleconsulta".
2. Elegí esa modalidad y confirmá el turno.
3. El día del turno, ingresá a la app unos minutos antes. Vas a ver un botón para unirte a la videollamada.
4. Necesitás tener cámara y micrófono habilitados.

**¿Tiene costo adicional?**
No. La teleconsulta tiene el mismo valor que una consulta presencial.
MD,
                'scope' => 'producto',
                'keywords' => 'teleconsulta,videollamada,remoto,virtual,consulta online,desde casa',
                'priority' => 10,
            ],
            [
                'topic' => 'turnos',
                'title' => '¿Cómo saco un turno?',
                'body' => <<<'MD'
Para sacar un turno en Bioenlace tenés varias opciones:

**Desde el asistente:**
- Decí "quiero un turno" o "necesito un turno con..." y seguí las instrucciones.
- El asistente te va a guiar paso a paso: servicio → centro → profesional → día → horario.

**Si sabés con quién:**
- Mencioná directamente el servicio o profesional (ej. "turno con mi dentista").
- El sistema va a buscar disponibilidad para esa especialidad.

**Si no sabés con quién:**
- Contale al asistente qué te pasa (ej. "me duele la espalda").
- Te va a orientar hacia el servicio más adecuado y ayudarte a sacar turno.

**Turnos para otra persona:**
- Si tenés un menor vinculado o un representante designado, podés sacar turnos en su nombre.

**Cancelar o reprogramar:**
- Decí "quiero cancelar mi turno" o usá la sección "Mis turnos".
MD,
                'scope' => 'producto',
                'keywords' => 'turno,cita,sacar turno,como saco,cancelar turno,reprogramar,mis turnos',
                'priority' => 10,
            ],
            [
                'topic' => 'que_es_bioenlace',
                'title' => '¿Qué es Bioenlace?',
                'body' => <<<'MD'
Bioenlace es una plataforma de salud digital que te conecta con centros de salud y profesionales de la red.

**¿Qué podés hacer?**
- Sacar turnos con especialistas y centros de salud.
- Atenderte por videollamada (teleconsulta) cuando esté disponible.
- Ver tu historial de atenciones.
- Gestionar turnos para familiares (menores o representados).
- Solicitar estudios y prácticas.
- Comunicarte con tu profesional de salud.

**¿Quién puede usarla?**
Cualquier persona con DNI puede registrarse. Es gratuita para los pacientes.
MD,
                'scope' => 'producto',
                'keywords' => 'que es bioenlace,como funciona,para que sirve,ayuda,la app,la aplicacion',
                'priority' => 5,
            ],
        ];

        foreach ($articles as $a) {
            $a['activo'] = 1;
            $a['created_at'] = date('Y-m-d H:i:s');
            $a['updated_at'] = date('Y-m-d H:i:s');
            $this->insert(self::TABLE, $a);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        foreach (['representacion', 'teleconsulta', 'turnos', 'que_es_bioenlace'] as $topic) {
            $this->delete(self::TABLE, ['topic' => $topic, 'scope' => 'producto']);
        }
    }
}
