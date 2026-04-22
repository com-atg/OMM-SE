import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux-pro/dist/flux.module.js';

window.Alpine = Alpine;
window.Livewire = Livewire;

Livewire.start();
