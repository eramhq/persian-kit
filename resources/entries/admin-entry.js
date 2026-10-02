import Alpine from 'alpinejs';
import normalizeJob from '../js/normalize-job.js';
import settingsTabs from '../js/settings-tabs.js';

Alpine.data('persianKitNormalize', normalizeJob);
Alpine.data('persianKitTabs', settingsTabs);
Alpine.start();
