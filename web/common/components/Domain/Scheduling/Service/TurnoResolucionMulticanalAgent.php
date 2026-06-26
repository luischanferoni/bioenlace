<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoResolucion;
use Yii;

/**
 * Agente A02 v1: escala a email/SMS con link firmado si el paciente no responde al push.
 */
final class TurnoResolucionMulticanalAgent
{
    public const AGENT_ID = 'turno-resolucion-multicanal';

    public const TRIGGER_TYPE = 'turno_resolucion_pending';

    private TurnoOutboundChannelStub $outbound;

    private TurnoResolucionLinkTokenService $linkTokens;

    private TurnoResolucionMulticanalScheduler $scheduler;

    public function __construct(
        ?TurnoOutboundChannelStub $outbound = null,
        ?TurnoResolucionLinkTokenService $linkTokens = null,
        ?TurnoResolucionMulticanalScheduler $scheduler = null
    ) {
        $this->outbound = $outbound ?? new TurnoOutboundChannelStub();
        $this->linkTokens = $linkTokens ?? new TurnoResolucionLinkTokenService();
        $this->scheduler = $scheduler ?? new TurnoResolucionMulticanalScheduler();
    }

    public function processScheduled(TurnoNotificacionProgramada $row, Turno $turno): string
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_multicanal_enabled'] ?? true)) {
            return 'cancelled';
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return 'cancelled';
        }

        $res = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($res === null || $turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
            return 'cancelled';
        }

        $meta = $row->payload_json ? json_decode($row->payload_json, true) : [];
        $channelIndex = is_array($meta) ? (int) ($meta['channel_index'] ?? 1) : 1;

        $channels = is_array($config['channels'] ?? null) ? $config['channels'] : [];
        $channel = $channels[$channelIndex] ?? null;
        if (!is_array($channel)) {
            return 'cancelled';
        }

        $channelId = (string) ($channel['id'] ?? '');
        if ($channelId === '' || $channelId === 'push') {
            return 'cancelled';
        }

        $legalTs = $this->scheduler->adjustToLegalWindow(time(), $config);
        if ($legalTs > time() + 60) {
            $row->run_at = date('Y-m-d H:i:s', $legalTs);
            $row->save(false);

            return 'deferred';
        }

        $ttlDays = (int) ($config['link_ttl_days'] ?? 7);
        $token = $this->linkTokens->issue((int) $res->id, (int) $turno->id_persona, $ttlDays * 86400);
        $link = $this->linkTokens->buildPublicUrl($token);
        $context = $this->buildContext($turno, $link);

        $sent = $this->dispatchChannel($channelId, (int) $turno->id_persona, $context, $config);

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $sent ? 'channel_' . $channelId : 'channel_' . $channelId . '_skipped',
            (int) $res->id,
            null,
            (int) $turno->id_persona,
            $channelId,
            [
                'id_turno' => (int) $turno->id_turnos,
                'channel_index' => $channelIndex,
            ],
            ['link' => $link, 'sent' => $sent]
        );

        $nextIndex = $channelIndex + 1;
        if (isset($channels[$nextIndex]) && (string) ($channels[$nextIndex]['id'] ?? '') !== 'push') {
            $this->scheduler->scheduleNextChannel($turno, $nextIndex, $config);
        }

        return 'sent';
    }

    /**
     * @param array<string, string> $context
     * @param array<string, mixed> $config
     */
    private function dispatchChannel(string $channelId, int $idPersona, array $context, array $config): bool
    {
        $templates = is_array($config['message_templates'] ?? null) ? $config['message_templates'] : [];

        if ($channelId === 'email') {
            $tpl = is_array($templates['email'] ?? null) ? $templates['email'] : [];
            $subject = $this->interpolate((string) ($tpl['subject'] ?? 'Tu turno requiere una nueva cita'), $context);
            $body = $this->interpolate((string) ($tpl['body'] ?? 'Elegí otro horario: {{link}}'), $context);

            return $this->outbound->sendEmail($idPersona, $subject, $body);
        }

        if ($channelId === 'sms') {
            $tpl = is_array($templates['sms'] ?? null) ? $templates['sms'] : [];
            $body = $this->interpolate((string) ($tpl['body'] ?? 'Reubicá tu turno: {{link}}'), $context);

            return $this->outbound->sendSms($idPersona, $body);
        }

        Yii::info('TurnoResolucionMulticanalAgent: canal no implementado ' . $channelId, 'turno-multicanal');

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function buildContext(Turno $turno, string $link): array
    {
        $nombre = '';
        $persona = Persona::findOne((int) $turno->id_persona);
        if ($persona !== null) {
            $nombre = trim((string) $persona->nombre);
        }

        return [
            'nombre' => $nombre !== '' ? $nombre : 'paciente',
            'fecha' => (string) $turno->fecha,
            'hora' => substr((string) $turno->hora, 0, 5),
            'link' => $link,
        ];
    }

    /**
     * @param array<string, string> $context
     */
    private function interpolate(string $template, array $context): string
    {
        $out = $template;
        foreach ($context as $key => $value) {
            $out = str_replace('{{' . $key . '}}', $value, $out);
        }

        return $out;
    }
}
