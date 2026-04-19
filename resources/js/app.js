import './bootstrap';

import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import * as Popper from '@popperjs/core';
window.Popper = Popper;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import PerfectScrollbar from 'perfect-scrollbar';
window.PerfectScrollbar = PerfectScrollbar;

import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
window.DataTable = DataTable;

// Helpers
import './hitee/helpers';

// Config
import './hitee/config';

// Menu
import './hitee/menu';

// Main
import './hitee/main';

// Theme Toggle Sync
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.querySelector('.dropdown-style-switcher a');
    if (themeToggle) {
        themeToggle.addEventListener('click', function(e) {
            // Let the server handle it via redirect, but we can also optimize here if needed
        });
    }
});
