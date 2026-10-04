import Alpine from 'alpinejs';
import clock from './components/clock';
import confirmDialog from './components/confirm-dialog';
import documentView from './components/document-view';
import grnForm from './components/grn-form';
import grnView from './components/grn-view';
import productForm from './components/product-form';
import posCart from './components/pos-cart';
import productStock from './components/product-stock';
import recordForm from './components/record-form';
import searchableSelect from './components/searchable-select';
import serviceForm from './components/service-form';
import stockItemsForm from './components/stock-items-form';
import supplierView from './components/supplier-view';
import teamManager from './components/team-manager';

window.Alpine = Alpine;

Alpine.data('clock', clock);
Alpine.data('confirmDialog', confirmDialog);
Alpine.data('documentView', documentView);
Alpine.data('grnForm', grnForm);
Alpine.data('grnView', grnView);
Alpine.data('productForm', productForm);
Alpine.data('posCart', posCart);
Alpine.data('productStock', productStock);
Alpine.data('recordForm', recordForm);
Alpine.data('searchableSelect', searchableSelect);
Alpine.data('serviceForm', serviceForm);
Alpine.data('stockItemsForm', stockItemsForm);
Alpine.data('supplierView', supplierView);
Alpine.data('teamManager', teamManager);

Alpine.start();
