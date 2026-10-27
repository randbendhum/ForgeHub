import { initDropzone } from './modules/dropzone';
import { initTableSort } from './modules/table-sort';

document.addEventListener('DOMContentLoaded', () => {
    initDropzone();
    initTableSort();
});
