<?php
/**
 * The switch's Review step: settings, links, data and what to sort out
 * before deactivating. Nothing changes here.
 */

defined('ABSPATH') || exit;
?>
<template x-if="step === 'review' && review !== null">
    <div class="persian-kit-switch__review">
        <p class="description" x-text="reviewIntro"></p>

        <div class="persian-kit-switch__summary">
            <template x-for="chip in summaryChips" :key="chip.status">
                <span class="persian-kit-chip" :class="'persian-kit-chip--' + chip.status" x-text="chip.text"></span>
            </template>
        </div>

        <!-- Notes: old links, and keep or switch -->
        <template x-for="note in review.notes" :key="note.key">
            <div class="persian-kit-switch__group">
                <h3 x-text="note.title"></h3>
                <div class="persian-kit-callout" :class="note.choice ? '' : 'persian-kit-callout--ok'">
                    <p x-text="note.text"></p>
                    <template x-if="note.example">
                        <p class="persian-kit-switch__example">
                            <code class="persian-kit-switch__code" x-text="note.example.before"></code>
                            <span aria-hidden="true">←</span>
                            <code class="persian-kit-switch__code" x-text="note.example.after"></code>
                        </p>
                    </template>
                    <template x-if="note.choice">
                        <fieldset class="persian-kit-switch__choice">
                            <legend class="screen-reader-text" x-text="note.title"></legend>
                            <template x-for="option in note.choice.options" :key="option.value">
                                <label>
                                    <input type="radio" :name="'persian-kit-choice-' + note.key" :value="option.value" :checked="options.keep_source === (option.value === 'keep')" @change="options.keep_source = option.value === 'keep'">
                                    <span>
                                        <strong x-text="option.label"></strong>
                                        <small x-text="option.description"></small>
                                    </span>
                                </label>
                            </template>
                        </fieldset>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="!keepingSource">
            <div>
                <!-- Settings -->
                <div class="persian-kit-switch__group" x-show="review.rows.length > 0">
                    <h3><?php esc_html_e('Settings', 'persian-kit'); ?></h3>
                    <div class="persian-kit-switch__scroll">
                        <table class="persian-kit-switch__table">
                            <thead>
                                <tr>
                                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Import', 'persian-kit'); ?></span></th>
                                    <th scope="col" x-text="current.name"></th>
                                    <th scope="col"><?php esc_html_e('Persian Kit', 'persian-kit'); ?></th>
                                    <th scope="col"><?php esc_html_e('Match', 'persian-kit'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in review.rows" :key="row.id">
                                    <tr :class="{ 'is-muted': !row.imports }">
                                        <td>
                                            <input
                                                type="checkbox"
                                                :id="'persian-kit-row-' + row.id"
                                                x-model="rows[row.id]"
                                                x-show="row.imports && !row.no_change"
                                                :aria-label="row.source_label"
                                            >
                                        </td>
                                        <td><label :for="'persian-kit-row-' + row.id" x-text="row.source_label"></label></td>
                                        <td>
                                            <span x-text="row.target_label || <?php echo esc_attr(wp_json_encode(__('Not in Persian Kit', 'persian-kit'))); ?>"></span>
                                            <template x-if="row.imports">
                                                <small class="persian-kit-switch__current" x-text="rowCurrent(row)"></small>
                                            </template>
                                            <small x-show="row.reason" x-text="row.reason"></small>
                                        </td>
                                        <td><span class="persian-kit-chip" :class="'persian-kit-chip--' + row.status" x-text="row.status_label"></span></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Data -->
                <div class="persian-kit-switch__group" x-show="review.tasks.length > 0">
                    <h3><?php esc_html_e('Data', 'persian-kit'); ?></h3>
                    <template x-for="task in review.tasks" :key="task.key">
                        <div class="persian-kit-switch__task">
                            <input type="checkbox" :id="'persian-kit-task-' + task.key" x-model="tasks[task.key]" :disabled="!task.available">
                            <div>
                                <label :for="'persian-kit-task-' + task.key">
                                    <strong x-text="task.label"></strong>
                                    <span x-show="task.available" x-text="' — ' + countText(task.count)"></span>
                                </label>
                                <small x-show="!task.available" class="persian-kit-warning" x-text="task.reason"></small>
                                <template x-if="task.samples.length > 0">
                                    <ul class="persian-kit-switch__samples">
                                        <template x-for="(sample, index) in task.samples" :key="index">
                                            <li>
                                                <span x-text="sample.label"></span>:
                                                <span class="persian-kit-switch__before" x-text="sample.before"></span>
                                                <span aria-hidden="true">←</span>
                                                <span class="persian-kit-switch__after" x-text="sample.after"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <?php \PersianKit\Components\View::load('admin/partials/switch-task-options'); ?>
                            </div>
                        </div>
                    </template>
                </div>

                <?php \PersianKit\Components\View::load('admin/partials/switch-checklist'); ?>
            </div>
        </template>

        <div class="persian-kit-switch__actions">
            <button type="button" class="button" @click="close()"><?php esc_html_e('Cancel', 'persian-kit'); ?></button>
            <span class="persian-kit-switch__spacer"></span>
            <button type="button" class="button button-primary" @click="continueFromReview()" :disabled="busy || keepingSource">
                <?php esc_html_e('Continue', 'persian-kit'); ?>
            </button>
        </div>
    </div>
</template>
