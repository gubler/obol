// ABOUTME: Rasterizes the Obol coin SVG into the PNG favicon + home-screen icon set under public/.
// ABOUTME: Run via `mise run icons` after editing assets/icons/obol-coin.svg; outputs are committed.

import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const master = await readFile(join(root, 'assets/icons/obol-coin.svg'), 'utf8');
const coinUri = 'data:image/svg+xml;base64,' + Buffer.from(master).toString('base64');

// The dark surface tile the coin sits on for home-screen / PWA icons. The coin SVG is a bare
// circle on transparency, which iOS renders on black and Android floats; a filled tile fixes both.
// Value is --obol-surface (dark) from assets/styles/app.css.
const TILE_BG = '#1f1810';

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

// Renders the coin centered on the solid dark tile. `fraction` is the coin's share of the tile:
// ~0.86 for regular icons, smaller for maskable ones so the coin stays inside the safe zone
// (the central ~80% circle a launcher may crop a maskable icon to).
async function tile(size, fraction, out) {
  const coin = Math.round(size * fraction);
  const inset = Math.round((size - coin) / 2);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
    <rect width="${size}" height="${size}" fill="${TILE_BG}"/>
    <image href="${coinUri}" x="${inset}" y="${inset}" width="${coin}" height="${coin}"/>
  </svg>`;
  await png(svg, size, out);
}

console.log('Generating favicons:');
await png(flat, 16, 'favicon-16.png');
await png(flat, 32, 'favicon-32.png');

console.log('Generating home-screen / PWA icons:');
await tile(180, 0.86, 'apple-touch-icon.png');
await tile(192, 0.86, 'icon-192.png');
await tile(512, 0.86, 'icon-512.png');
await tile(192, 0.78, 'icon-192-maskable.png');
await tile(512, 0.78, 'icon-512-maskable.png');
