import Alpine from 'alpinejs';
import clock from './components/clock';
import confirmDialog from './components/confirm-dialog';
import recordForm from './components/record-form';
import searchableSelect from './components/searchable-select';
import serviceForm from './components/service-form';
import teamManager from './components/team-manager';

window.Alpine = Alpine;

Alpine.data('clock', clock);
Alpine.data('confirmDialog', confirmDialog);
Alpine.data('recordForm', recordForm);
Alpine.data('searchableSelect', searchableSelect);
Alpine.data('serviceForm', serviceForm);
Alpine.data('teamManager', teamManager);

Alpine.start();
