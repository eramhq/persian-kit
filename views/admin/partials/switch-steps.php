<?php
/**
 * The switch's Deactivate, Import and Report steps.
 */

defined('ABSPATH') || exit;
?>
<!-- Deactivate -->
<template x-if="step === 'deactivate'">
    <div class="persian-kit-switch__deactivate">
        <template x-if="current.active">
            <div>
                <p x-text="deactivateIntro"></p>

                <?php \PersianKit\Components\View::load('admin/partials/switch-checklist'); ?>

                <div class="persian-kit-callout">
                    <p x-text="quietNote"></p>
                </div>

                <p>
                    <label class="persian-kit-switch__option">
                        <input type="checkbox" x-model="backup">
                        <span><?php esc_html_e('I have a recent database backup', 'persian-kit'); ?></span>
                    </label>
                </p>

                <p x-show="current.deactivate.message" class="persian-kit-warning" x-text="current.deactivate.message"></p>

                <div class="persian-kit-switch__actions">
                    <button type="button" class="button" @click="goTo('review')"><?php esc_html_e('Back', 'persian-kit'); ?></button>
                    <span class="persian-kit-switch__spacer"></span>
                    <template x-if="current.deactivate.url && canDeactivate">
                        <a class="button button-primary" :href="current.deactivate.url" @click="saveChoices()" x-text="deactivateLabel"></a>
                    </template>
                    <template x-if="!current.deactivate.url || !canDeactivate">
                        <button type="button" class="button button-primary" disabled x-text="deactivateLabel"></button>
                    </template>
                </div>
                <p class="description" x-show="current.deactivate.url && !canDeactivate"><?php esc_html_e('Tick the backup box and each item above first.', 'persian-kit'); ?></p>
                <p class="description" x-show="current.deactivate.url"><?php esc_html_e('This is WordPress’s own Deactivate link, on the Plugins screen. Come back here afterwards; a link there brings you back.', 'persian-kit'); ?></p>
            </div>
        </template>

        <template x-if="!current.active">
            <div>
                <div class="persian-kit-callout persian-kit-callout--ok">
                    <p x-text="inactiveText"></p>
                </div>
                <div class="persian-kit-switch__actions">
                    <button type="button" class="button" @click="goTo('review')"><?php esc_html_e('Back', 'persian-kit'); ?></button>
                    <span class="persian-kit-switch__spacer"></span>
                    <button type="button" class="button button-primary" @click="startImport()" :disabled="busy"><?php esc_html_e('Import now', 'persian-kit'); ?></button>
                </div>
            </div>
        </template>
    </div>
</template>

<!-- Import -->
<template x-if="step === 'import' && job !== null">
    <div class="persian-kit-switch__import">
        <div class="persian-kit-switch__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="job.progress.percent" :aria-label="progressText">
            <span :style="'width:' + job.progress.percent + '%'"></span>
        </div>
        <p class="description" x-text="progressText"></p>

        <ul class="persian-kit-switch__task-status">
            <template x-for="item in jobTasks" :key="item.key">
                <li :class="'is-' + item.status">
                    <span x-text="item.label"></span>
                    <span class="persian-kit-switch__task-count" x-text="item.text"></span>
                </li>
            </template>
        </ul>

        <div x-show="job.status === 'paused' && job.paused_reason" class="persian-kit-callout">
            <p x-text="job.paused_reason"></p>
        </div>

        <div class="persian-kit-switch__actions">
            <button type="button" class="button" @click="cancel()" x-show="!running" :disabled="busy"><?php esc_html_e('Cancel the switch', 'persian-kit'); ?></button>
            <span class="persian-kit-switch__spacer"></span>
            <button type="button" class="button" @click="pause()" x-show="running"><?php esc_html_e('Pause', 'persian-kit'); ?></button>
            <button type="button" class="button button-primary" @click="run()" x-show="!running" :disabled="busy"><?php esc_html_e('Resume', 'persian-kit'); ?></button>
        </div>
    </div>
</template>

<!-- Report -->
<template x-if="step === 'report'">
    <div class="persian-kit-switch__report">
        <div class="persian-kit-callout persian-kit-callout--ok" x-show="!undoResult">
            <p><strong x-text="finishedText"></strong></p>
        </div>
        <div class="persian-kit-callout persian-kit-callout--ok" x-show="undoResult">
            <p x-text="undoText"></p>
        </div>

        <ul class="persian-kit-switch__tips" x-show="report && report.tips && report.tips.length > 0">
            <template x-for="(tip, index) in (report ? report.tips || [] : [])" :key="index">
                <li x-text="tip"></li>
            </template>
        </ul>

        <template x-if="report !== null">
            <div>
                <div class="persian-kit-switch__filters" role="group" aria-label="<?php esc_attr_e('Show', 'persian-kit'); ?>">
                    <template x-for="filter in reportFilters" :key="filter.outcome">
                        <button
                            type="button"
                            class="persian-kit-chip persian-kit-switch__filter"
                            :class="['persian-kit-chip--' + filter.chip, { 'is-active': reportOutcome === filter.outcome }]"
                            :aria-pressed="reportOutcome === filter.outcome ? 'true' : 'false'"
                            @click="loadReport(filter.outcome, 1)"
                            x-text="filter.text"
                        ></button>
                    </template>
                </div>

                <p x-show="report.rows.length === 0" class="description"><?php esc_html_e('Nothing here.', 'persian-kit'); ?></p>

                <div class="persian-kit-switch__scroll" x-show="report.rows.length > 0">
                    <table class="persian-kit-switch__table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Item', 'persian-kit'); ?></th>
                                <th scope="col"><?php esc_html_e('Change', 'persian-kit'); ?></th>
                                <th scope="col"><?php esc_html_e('Outcome', 'persian-kit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in report.rows" :key="row.id">
                                <tr>
                                    <td>
                                        <template x-if="row.url">
                                            <a :href="row.url" x-text="row.object"></a>
                                        </template>
                                        <template x-if="!row.url">
                                            <span x-text="row.object"></span>
                                        </template>
                                        <small x-show="row.field && row.object !== row.field" x-text="row.field"></small>
                                    </td>
                                    <td>
                                        <span x-show="row.before || row.after">
                                            <span class="persian-kit-switch__before" x-text="row.before"></span>
                                            <span aria-hidden="true" x-show="row.after">←</span>
                                            <span class="persian-kit-switch__after" x-text="row.after"></span>
                                        </span>
                                        <small x-show="row.reason" x-text="row.reason"></small>
                                    </td>
                                    <td x-text="row.outcome_label"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="persian-kit-switch__pages" x-show="report.pages > 1">
                    <button type="button" class="button" @click="loadReport(reportOutcome, reportPage - 1)" :disabled="reportPage <= 1"><?php esc_html_e('Previous', 'persian-kit'); ?></button>
                    <span x-text="pageText"></span>
                    <button type="button" class="button" @click="loadReport(reportOutcome, reportPage + 1)" :disabled="reportPage >= report.pages"><?php esc_html_e('Next', 'persian-kit'); ?></button>
                </div>
            </div>
        </template>

        <div class="persian-kit-switch__actions">
            <a class="button" :href="csvUrl" x-show="csvUrl"><?php esc_html_e('Download the report (CSV)', 'persian-kit'); ?></a>
            <span class="persian-kit-switch__spacer"></span>
            <button type="button" class="button" @click="confirmingUndo = true" x-show="current.can_undo && !confirmingUndo" :disabled="busy"><?php esc_html_e('Undo the import', 'persian-kit'); ?></button>
            <button type="button" class="button-link" @click="forget()" x-show="current.can_undo && !confirmingUndo" :disabled="busy"><?php esc_html_e('Forget undo data', 'persian-kit'); ?></button>
        </div>

        <div class="persian-kit-batch-confirm" x-show="confirmingUndo">
            <p><strong><?php esc_html_e('Put back the values from before the import?', 'persian-kit'); ?></strong></p>
            <p><?php esc_html_e('Settings and data are put back only where they still hold what the import wrote; anything changed since is kept.', 'persian-kit'); ?></p>
            <p x-show="undoWarning" class="persian-kit-warning" x-text="undoWarning"></p>
            <p class="persian-kit-batch-actions">
                <button type="button" class="button button-primary" @click="undo()" :disabled="busy"><?php esc_html_e('Undo now', 'persian-kit'); ?></button>
                <button type="button" class="button" @click="confirmingUndo = false"><?php esc_html_e('Cancel', 'persian-kit'); ?></button>
            </p>
        </div>
    </div>
</template>
