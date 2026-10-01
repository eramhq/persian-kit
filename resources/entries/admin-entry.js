import Alpine from 'alpinejs';
import normalizeJob from '../js/normalize-job.js';

window.Alpine = Alpine;
Alpine.data('persianKitNormalize', normalizeJob);
Alpine.start();
