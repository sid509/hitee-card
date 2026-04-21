import './bootstrap';
import jQuery from 'jquery';
import * as Popper from '@popperjs/core';
import * as bootstrap from 'bootstrap';
import select2 from 'select2';
import PerfectScrollbar from 'perfect-scrollbar';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

// Set Globals
window.$ = window.jQuery = jQuery;
window.Popper = Popper;
window.bootstrap = bootstrap;
window.PerfectScrollbar = PerfectScrollbar;
window.DataTable = DataTable;

// Set DataTables Defaults
$.extend(true, $.fn.dataTable.defaults, {
    stateSave: false,
    stateSaveCallback: function(settings, data) {
        const page = Math.floor(data.start / data.length) + 1;
        const url = new URL(window.location.href);
        
        if (page > 1) {
            url.searchParams.set('page', page);
        } else {
            url.searchParams.delete('page');
        }
        
        window.history.replaceState(null, null, url);
        localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data));
    },
    stateLoadCallback: function(settings) {
        const urlParams = new URLSearchParams(window.location.search);
        const page = parseInt(urlParams.get('page'));
        
        // If no page param or page 1, ignore localStorage and start fresh
        if (isNaN(page) || page <= 1) {
            localStorage.removeItem('DataTables_' + settings.sInstance);
            return null;
        }
        
        const saved = JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance));
        if (saved) {
            saved.start = (page - 1) * (saved.length || 10);
            return saved;
        }
        return null;
    }
});

// Initialize Select2
select2();

// CSS
import 'select2/dist/css/select2.min.css';

// Hitee Theme Scripts
import './hitee/helpers';
import './hitee/config';
import './hitee/menu';
import './hitee/main';
