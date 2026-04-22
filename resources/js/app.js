import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux-pro/dist/flux.module.js';
import { bootScholarDetailCharts } from './scholar-detail-charts';

window.Alpine = Alpine;
window.Livewire = Livewire;

bootScholarDetailCharts(Livewire);
Livewire.start();
