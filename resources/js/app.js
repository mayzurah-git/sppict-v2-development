// Pemilih tarikh/masa (Flatpickr) — paparan dd/mm/yyyy, nilai Y-m-d dihantar ke pelayan.
import flatpickr from 'flatpickr';
import { Malaysian } from 'flatpickr/dist/l10n/ms.js';
import 'flatpickr/dist/flatpickr.min.css';

// Editor teks (Trix) untuk Agenda Mesyuarat.
import 'trix';
import 'trix/dist/trix.css';

// Format 12 jam: penanda "pagi" (sebelum 12 tengah hari) dan "petang" (12 tengah hari dan selepasnya).
flatpickr.localize({ ...Malaysian, amPM: ['pagi', 'petang'] });
window.flatpickr = flatpickr;
