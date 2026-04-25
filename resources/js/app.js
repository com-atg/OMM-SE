import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux-pro/dist/flux.module.js';
import { registerCsvFileDrop } from './csv-file-drop';
import { bootStudentDetailCharts } from './student-detail-charts';

window.Alpine = Alpine;
window.Livewire = Livewire;

registerCsvFileDrop(Alpine);
bootStudentDetailCharts(Livewire);
Livewire.start();
