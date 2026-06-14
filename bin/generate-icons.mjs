// ABOUTME: Rasterizes the Obol coin SVG into the PNG favicon set under public/.
// ABOUTME: Run via `mise run icons` after editing assets/icons/obol-coin.svg; outputs are committed.

import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const master = await readFile(join(root, 'assets/icons/obol-coin.svg'), 'utf8');

// The beaded rim (the dashed circle) turns to noise below ~48px, so small
// favicons render from a flat-rim variant: the same coin with that one
// element stripped. Keeps a single SVG source of truth for the geometry.
const flat = master.replace(/^.*stroke-dasharray.*\n/m, '');

async function png(svg, size, out) {
  await sharp(Buffer.from(svg))
    .resize(size, size)
    .png()
    .toFile(join(root, 'public', out));
  console.log(`  public/${out} (${size}x${size})`);
}

console.log('Generating favicons:');
await png(flat, 16, 'favicon-16.png');
await png(flat, 32, 'favicon-32.png');
await png(master, 180, 'apple-touch-icon.png');
