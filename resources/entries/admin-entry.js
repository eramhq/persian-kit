import Alpine from 'alpinejs';
import importJob from '../js/import-job.js';
import normalizeJob from '../js/normalize-job.js';
import settingsTabs from '../js/settings-tabs.js';

Alpine.data('persianKitImport', importJob);
Alpine.data('persianKitNormalize', normalizeJob);
Alpine.data('persianKitTabs', settingsTabs);
Alpine.start();
