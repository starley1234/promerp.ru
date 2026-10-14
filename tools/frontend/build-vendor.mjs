import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
const here = dirname(fileURLToPath(import.meta.url));
const source = resolve(here, 'node_modules/qrcode-generator/dist/qrcode.js');
const target = resolve(here, '../../public_html/assets/vendor/qrcode-generator/qrcode.js');
await mkdir(dirname(target), { recursive: true });
await copyFile(source, target);
console.log('vendor asset: qrcode-generator → public_html/assets/vendor/qrcode-generator/qrcode.js');
