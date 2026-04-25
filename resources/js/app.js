import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux-pro/dist/flux.module.js';
import { bootStudentDetailCharts } from './student-detail-charts';

window.Alpine = Alpine;
window.Livewire = Livewire;

bootStudentDetailCharts(Livewire);
Livewire.start();
