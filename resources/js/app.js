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

// Initialize Select2
select2();

// CSS
import 'select2/dist/css/select2.min.css';

// Hitee Theme Scripts
import './hitee/helpers';
import './hitee/config';
import './hitee/menu';
import './hitee/main';
