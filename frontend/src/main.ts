import { registerLocaleData } from '@angular/common';
import localeRu from '@angular/common/locales/ru';
import { bootstrapApplication } from '@angular/platform-browser';
import { App } from './app/app';
import { appConfig } from './app/app.config';

registerLocaleData(localeRu);

bootstrapApplication(App, appConfig).catch((err: unknown) => console.error(err));
