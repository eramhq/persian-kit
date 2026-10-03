import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';

// The components read these globals when they run.
const calls = [];
let responses = [];

globalThis.window = {
    wp: {
        i18n: {
            __: (text) => text,
            _n: (single, plural, count) => (count === 1 ? single : plural),
            sprintf: (format, ...values) => format.replace(/%(\d\$)?[ds]/g, () => values.shift()),
        },
    },
    persianKitSettings: { restUrl: 'https://example.test/wp-json/persian-kit/v1/', nonce: 'n' },
    sessionStorage: {
        store: {},
        getItem(key) {
            return this.store[key] || null;
        },
        setItem(key, value) {
            this.store[key] = value;
        },
    },
};
globalThis.document = {
    documentElement: { lang: 'en' },
    visibilityState: 'visible',
    addEventListener() {},
    getElementById() {
        return null;
    },
};
// Batches follow each other at once.
globalThis.setTimeout = (callback) => {
    callback();
    return 1;
};

/**
 * Answers fetch() with the next queued response, recording the request.
 * A response is {status, body} or an Error to throw (a network failure).
 */
globalThis.fetch = async (url, options = {}) => {
    calls.push({ url, method: options.method || 'GET', body: options.body ? JSON.parse(options.body) : null });
    const next = responses.shift();
    if (next instanceof Error) {
        throw next;
    }
    const { status = 200, body = {} } = next || {};

    return { ok: status < 400, status, statusText: `HTTP ${status}`, json: async () => body };
};

const { default: importJob } = await import('../../resources/js/import-job.js');
const { default: normalizeJob } = await import('../../resources/js/normalize-job.js');

function component(config = {}) {
    const instance = importJob(config);
    instance.$nextTick = (callback) => callback();
    instance.$refs = {};

    return instance;
}

function source(overrides = {}) {
    return {
        key: 'persian-woocommerce-shipping',
        name: 'Shipping',
        state: 'inactive',
        state_label: 'Inactive',
        active: false,
        can_undo: false,
        deactivate: { url: null, network: false, message: '' },
        job: null,
        ...overrides,
    };
}

function review({ source: sourceOverrides = {}, ...overrides } = {}) {
    return {
        source: source(sourceOverrides),
        rows: [
            { id: 'cities', status: 'close', imports: true, ticked: true, no_change: false },
            { id: 'more', status: 'close', imports: true, ticked: false, no_change: false },
            { id: 'methods', status: 'not_yet', imports: false, ticked: false, no_change: false },
        ],
        notes: [],
        tasks: [
            { key: 'customers', label: 'Customers', available: true, count: 3, samples: [], options: { type: 'district_line', default: true, label: 'District' } },
            {
                key: 'order_statuses',
                label: 'Statuses',
                available: true,
                count: 2,
                samples: [],
                options: { type: 'status_map', default: 'wc-processing', targets: { 'wc-processing': 'Processing' }, statuses: [{ slug: 'wc-pws-packaged', label: 'Packaged', count: 2 }] },
            },
            { key: 'acf_values', label: 'ACF', available: false, reason: 'Activate ACF', count: 0, samples: [] },
        ],
        checklist: [],
        ...overrides,
    };
}

function job(overrides = {}) {
    return {
        run_id: 'r1',
        source: 'persian-woocommerce-shipping',
        step: 'import',
        status: 'running',
        paused_reason: '',
        rows: ['cities'],
        task_keys: ['customers'],
        options: {},
        tasks: { settings: { status: 'done', total: 1, processed: 1 }, customers: { status: 'pending', total: 3, processed: 1 } },
        backup: true,
        acknowledged: [],
        progress: { processed: 2, total: 4, percent: 50 },
        ...overrides,
    };
}

beforeEach(() => {
    calls.length = 0;
    responses = [];
});

test('Review ticks the default rows and the tasks that can run', async () => {
    const instance = component();
    responses.push({ body: review() });

    await instance.open('persian-woocommerce-shipping');

    assert.equal(instance.step, 'review');
    assert.deepEqual(instance.rows, { cities: true, more: false, methods: false });
    assert.deepEqual(instance.tasks, { customers: true, order_statuses: true, acf_values: false });
    assert.deepEqual(instance.options.status_map, { 'wc-pws-packaged': 'wc-processing' });
    assert.equal(instance.options.district_line, true);
    assert.deepEqual(instance.summaryChips.map((chip) => chip.status), ['close', 'not_yet']);
});

test('Continuing while the source is active saves the choices and opens Deactivate', async () => {
    const instance = component();
    responses.push({ body: review({ source: { active: true, state: 'active' } }) });
    await instance.open('persian-woocommerce-shipping');
    instance.rows.more = true;
    instance.options.status_map['wc-pws-packaged'] = 'wc-completed';

    responses.push({ status: 409, body: { code: 'persian_kit_import_not_ready', message: 'Deactivate first.', data: { status: 409, job: job({ step: 'deactivate', status: 'waiting' }) } } });
    await instance.continueFromReview();

    assert.equal(instance.step, 'deactivate');
    assert.equal(instance.error, '');
    const start = calls.at(-1);
    assert.equal(start.method, 'POST');
    assert.match(start.url, /import\/persian-woocommerce-shipping\/start$/);
    assert.deepEqual(start.body.rows, ['cities', 'more']);
    assert.deepEqual(start.body.tasks, ['customers', 'order_statuses']);
    assert.equal(start.body.options.status_map['wc-pws-packaged'], 'wc-completed');
});

test('Deactivate opens only with the backup ticked and every item handled', async () => {
    const instance = component();
    responses.push({
        body: review({
            source: { active: true },
            checklist: [
                { key: 'theme_calls', blocking: true, acknowledge: false, entries: [], extra: {} },
                { key: 'gateways', blocking: false, acknowledge: true, entries: [], extra: {} },
                { key: 'info', blocking: false, acknowledge: false, entries: [], extra: {} },
            ],
        }),
    });
    await instance.open('persian-woocommerce-shipping');

    assert.equal(instance.canDeactivate, false);
    instance.backup = true;
    assert.equal(instance.canDeactivate, false);
    instance.acknowledged.theme_calls = true;
    assert.equal(instance.canDeactivate, false);
    instance.acknowledged.gateways = true;
    assert.equal(instance.canDeactivate, true);
});

test('An inactive source skips Deactivate and imports to the report', async () => {
    const instance = component();
    responses.push({ body: review() });
    await instance.open('persian-woocommerce-shipping');

    responses.push({ body: { job: job({ status: 'running' }) } }); // start
    responses.push({ body: { job: job() } }); // first batch
    responses.push({ body: { job: job({ step: 'report', status: 'done', progress: { processed: 4, total: 4, percent: 100 } }) } });
    responses.push({ body: review({ source: { state: 'imported', can_undo: true } }) }); // refresh
    responses.push({ body: { rows: [], counts: { changed: 4 }, total: 0, pages: 0 } }); // report

    await instance.continueFromReview();
    // The batches chain through setTimeout; let them settle.
    for (let i = 0; i < 10 && instance.step !== 'report'; i++) {
        await new Promise((resolve) => setImmediate(resolve));
    }

    assert.equal(instance.step, 'report');
    assert.equal(instance.running, false);
    assert.equal(instance.steps.find((step) => step.key === 'deactivate').done, true);
    assert.equal(instance.report.counts.changed, 4);
    const runs = calls.filter((call) => call.url.endsWith('import/run'));
    assert.equal(runs.length, 2);
    assert.match(runs[0].body.tab, /^[a-z0-9]+$/);
});

test('A switch in progress opens where it was left after a reload', async () => {
    responses.push({ body: review({ source: { job: job({ status: 'paused', paused_reason: 'Reactivated.' }) } }) });
    const instance = component({ job: job({ status: 'paused' }) });

    instance.init();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(instance.step, 'import');
    assert.deepEqual(instance.rows, { cities: true, more: false, methods: false });
    assert.match(instance.progressText, /Paused at 50%/);
    assert.equal(instance.backup, true);
});

test('Another tab working on the switch is a plain error, without the WP-CLI hint', async () => {
    const instance = component();
    responses.push({ body: review({ source: { job: job() } }) });
    await instance.open('persian-woocommerce-shipping');

    responses.push({ status: 409, body: { code: 'persian_kit_import_locked', message: 'Running in another tab.', data: { status: 409 } } });
    await instance.run();

    assert.equal(instance.running, false);
    assert.equal(instance.error, 'Running in another tab.');
    assert.equal(instance.cliCommand, '');
});

test('A blocked REST API shows the WP-CLI command', async () => {
    const instance = component();
    responses.push(new TypeError('Failed to fetch'));

    await instance.open('wp-parsidate');

    assert.equal(instance.error, 'Failed to fetch');
    assert.equal(instance.cliCommand, 'wp persian-kit import wp-parsidate');

    responses.push({ status: 403, body: { code: 'forbidden_by_firewall', message: 'Blocked' } });
    await instance.open('wp-parsidate');
    assert.equal(instance.cliCommand, 'wp persian-kit import wp-parsidate');
});

test('Undo runs until done and adds up the counts', async () => {
    const instance = component();
    responses.push({ body: review({ source: { job: job({ step: 'report', status: 'done' }), can_undo: true } }) });
    responses.push({ body: { rows: [], counts: {}, total: 0, pages: 0 } });
    await instance.open('persian-woocommerce-shipping');

    responses.push({ body: { done: false, cursor: 40, restored: 30, kept: 1, run_id: 'undo-1' } });
    responses.push({ body: { done: true, cursor: 2, restored: 5, kept: 0, run_id: 'undo-1' } });
    responses.push({ body: review({ source: { can_undo: false } }) });
    responses.push({ body: { rows: [], counts: {}, total: 0, pages: 0 } });
    await instance.undo();

    assert.deepEqual(instance.undoResult, { restored: 35, kept: 1 });
    const undos = calls.filter((call) => call.url.endsWith('/undo'));
    assert.deepEqual(undos.map((call) => call.body.cursor), [0, 40]);
    assert.deepEqual(undos.map((call) => call.body.run_id), ['', 'undo-1']);
});

test('The report pages through one outcome at a time', async () => {
    const instance = component();
    responses.push({ body: review({ source: { job: job({ step: 'report', status: 'done' }) } }) });
    responses.push({ body: { rows: [], counts: { changed: 120, attention: 3 }, total: 120, pages: 3 } });
    await instance.open('persian-woocommerce-shipping');

    responses.push({ body: { rows: [{ id: 1 }], counts: { changed: 120, attention: 3 }, total: 3, pages: 1 } });
    await instance.loadReport('attention', 1);

    assert.equal(instance.reportOutcome, 'attention');
    assert.match(calls.at(-1).url, /report\?outcome=attention&page=1$/);
    assert.deepEqual(instance.reportFilters.map((filter) => filter.text), ['Done (120)', 'Not imported (0)', 'Needs attention (3)']);
});

test('The letter fix still reads its status through the shared REST client', async () => {
    const instance = normalizeJob({ selected: ['post'] });
    responses.push({ body: { is_resuming: true, job: { status: 'running', processed: 10, modified: 2, post_types: ['page'] } } });

    await instance.checkStatus();

    assert.match(calls.at(-1).url, /normalize\/status$/);
    assert.equal(calls.at(-1).method, 'GET');
    assert.equal(instance.paused, true);
    assert.deepEqual(instance.postTypes, ['page']);

    responses.push({ status: 500, body: { message: 'Server error' } });
    await instance.checkStatus();
    assert.equal(instance.error, 'Server error');
});
