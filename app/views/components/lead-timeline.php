<?php
/**
 * Lead timeline component.
 *
 * Expects the following optional variables in scope:
 * - $timelineEvents: array<int, array<string, mixed>> List of timeline events.
 * - $prospeccaoId: int|string|null Identifier used to fetch events through the API.
 */

if (!function_exists('leadTimelineRelativeTime')) {
    function leadTimelineRelativeTime($timestamp): string
    {
        if (empty($timestamp)) {
            return '-';
        }

        try {
            $date = $timestamp instanceof DateTimeInterface ? $timestamp : new DateTime($timestamp);
        } catch (Exception $exception) {
            return '-';
        }

        $now = new DateTimeImmutable('now');
        $diffSeconds = $now->getTimestamp() - $date->getTimestamp();

        if ($diffSeconds <= 0) {
            return 'agora mesmo';
        }

        $minutes = (int) floor($diffSeconds / 60);
        $hours = (int) floor($diffSeconds / 3600);
        $days = (int) floor($diffSeconds / 86400);
        $months = (int) floor($diffSeconds / 2592000);
        $years = (int) floor($diffSeconds / 31536000);

        if ($diffSeconds < 60) {
            return sprintf('há %d segundos', $diffSeconds);
        }

        if ($minutes < 60) {
            return sprintf('há %d minuto%s', $minutes, $minutes > 1 ? 's' : '');
        }

        if ($hours < 24) {
            return sprintf('há %d hora%s', $hours, $hours > 1 ? 's' : '');
        }

        if ($days < 30) {
            return sprintf('há %d dia%s', $days, $days > 1 ? 's' : '');
        }

        if ($months < 12) {
            return sprintf('há %d mês%s', $months, $months > 1 ? 'es' : '');
        }

        return sprintf('há %d ano%s', $years, $years > 1 ? 's' : '');
    }
}

$timelineEvents = $timelineEvents ?? [];
$prospeccaoId = $prospeccaoId ?? null;
$baseAppUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';

$actionIcons = [
    'created' => 'fa-plus-circle',
    'qualified' => 'fa-rocket',
    'transferred' => 'fa-arrow-right',
    'accepted' => 'fa-check-circle',
    'rejected' => 'fa-times-circle',
    'first_contact' => 'fa-phone-alt',
    'meeting_scheduled' => 'fa-calendar',
    'proposal_sent' => 'fa-file-signature',
    'converted' => 'fa-trophy',
    'lost' => 'fa-ban',
    'note_added' => 'fa-sticky-note',
];

$positiveTypes = ['accepted', 'qualified', 'meeting_scheduled', 'proposal_sent', 'converted', 'first_contact'];
$negativeTypes = ['rejected', 'lost'];
?>

<?php if ($baseAppUrl): ?>
    <link rel="stylesheet" href="<?= $baseAppUrl; ?>/assets/css/components/lead-timeline.css">
<?php endif; ?>

<div class="lead-timeline" data-component="lead-timeline"<?= $prospeccaoId ? ' data-prospeccao-id="' . htmlspecialchars((string) $prospeccaoId) . '"' : '' ?>>
    <div class="lead-timeline__header">
        <div class="lead-timeline__filter">
            <label for="lead-timeline-filter" class="lead-timeline__filter-label">Filtrar eventos</label>
            <select id="lead-timeline-filter" class="lead-timeline__filter-select">
                <option value="">Todos</option>
                <?php foreach (array_keys($actionIcons) as $actionType): ?>
                    <option value="<?= htmlspecialchars($actionType) ?>"><?= ucwords(str_replace('_', ' ', $actionType)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lead-timeline__actions">
            <button type="button" class="lead-timeline__button lead-timeline__button--note" data-action="add-note">
                <i class="fas fa-sticky-note"></i>
                <span>Adicionar nota</span>
            </button>
            <button type="button" class="lead-timeline__button lead-timeline__button--export" data-action="export-pdf">
                <i class="fas fa-file-pdf"></i>
                <span>Exportar PDF</span>
            </button>
        </div>
    </div>
    <div class="timeline" data-role="timeline-container">
        <?php if (empty($timelineEvents)): ?>
            <div class="timeline__empty">Nenhum evento disponível para esta prospecção.</div>
        <?php else: ?>
            <?php foreach ($timelineEvents as $event): ?>
                <?php
                    $actionType = $event['action_type'] ?? 'note_added';
                    $icon = $actionIcons[$actionType] ?? 'fa-info-circle';
                    $isPositive = in_array($actionType, $positiveTypes, true);
                    $isNegative = in_array($actionType, $negativeTypes, true);
                    $metaClass = $isPositive ? 'timeline-item--positive' : ($isNegative ? 'timeline-item--negative' : 'timeline-item--neutral');
                    $actor = $event['user_name'] ?? $event['user'] ?? null;
                    $target = $event['target_user_name'] ?? $event['target_user'] ?? null;
                    $headline = $event['description'] ?? '';
                    if (!$headline && $actionType === 'transferred' && $target) {
                        $headline = 'Transferido para ' . $target;
                    }
                    $metadata = $event['metadata'] ?? [];
                    if (is_string($metadata)) {
                        $decoded = json_decode($metadata, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $metadata = $decoded;
                        }
                    }
                    $metadata = is_array($metadata) ? $metadata : [];
                ?>
                <div class="timeline-item <?= $metaClass ?>" data-action="<?= htmlspecialchars($actionType) ?>">
                    <div class="timeline-icon">
                        <i class="fas <?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <strong>
                                <?= htmlspecialchars($headline ?: ucwords(str_replace('_', ' ', $actionType))) ?>
                            </strong>
                            <span class="timeline-time">
                                <?= htmlspecialchars(leadTimelineRelativeTime($event['created_at'] ?? null)) ?>
                            </span>
                        </div>
                        <?php if (!empty($event['notes'])): ?>
                            <div class="timeline-body">Notas: <?= htmlspecialchars($event['notes']) ?></div>
                        <?php elseif (!empty($event['description'])): ?>
                            <div class="timeline-body"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($metadata)): ?>
                            <div class="timeline-metadata" data-role="metadata">
                                <button type="button" class="timeline-metadata__toggle" data-action="toggle-metadata">
                                    Ver detalhes
                                </button>
                                <div class="timeline-metadata__content" hidden>
                                    <dl>
                                        <?php foreach ($metadata as $key => $value): ?>
                                            <div class="timeline-metadata__row">
                                                <dt><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $key))) ?></dt>
                                                <dd><?= htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($actor): ?>
                            <div class="timeline-footer">
                                <small>Por <?= htmlspecialchars($actor) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($baseAppUrl): ?>
    <script src="<?= $baseAppUrl; ?>/assets/js/lead-timeline.js" defer></script>
<?php endif; ?>
