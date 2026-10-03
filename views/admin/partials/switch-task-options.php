<?php
/**
 * A data task's choices in Review: where custom order statuses go, and
 * whether the district fills the second address line.
 */

defined('ABSPATH') || exit;
?>
<template x-if="task.options && task.options.type === 'status_map'">
    <table class="persian-kit-switch__table persian-kit-switch__statuses">
        <caption class="screen-reader-text"><?php esc_html_e('Where each order status goes', 'persian-kit'); ?></caption>
        <tbody>
            <template x-for="status in task.options.statuses" :key="status.slug">
                <tr>
                    <td>
                        <label :for="'persian-kit-status-' + status.slug" x-text="statusLabel(status)"></label>
                    </td>
                    <td>
                        <select :id="'persian-kit-status-' + status.slug" x-model="options.status_map[status.slug]">
                            <template x-for="(label, slug) in task.options.targets" :key="slug">
                                <option :value="slug" x-text="label" :selected="options.status_map[status.slug] === slug"></option>
                            </template>
                        </select>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</template>
<template x-if="task.options && task.options.type === 'district_line'">
    <label class="persian-kit-switch__option">
        <input type="checkbox" x-model="options.district_line">
        <span x-text="task.options.label"></span>
    </label>
</template>
<template x-if="task.options && task.options.notice">
    <p class="persian-kit-warning" x-text="task.options.notice"></p>
</template>
