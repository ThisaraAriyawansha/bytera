import Alpine from 'alpinejs';
import clock from './components/clock';
import confirmDialog from './components/confirm-dialog';
import searchableSelect from './components/searchable-select';

window.Alpine = Alpine;

Alpine.data('clock', clock);
Alpine.data('confirmDialog', confirmDialog);
Alpine.data('searchableSelect', searchableSelect);

Alpine.start();
