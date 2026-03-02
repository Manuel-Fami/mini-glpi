import { startStimulusApp } from '@symfony/stimulus-bundle';
import FlashController from './controllers/flash_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('flash', FlashController);
