// ABOUTME: Astro + Starlight config for the Obol developer docs site (docs.dev88.work/obol).
// ABOUTME: Builds the sidebar from the content tree so empty sections (pre-migration) drop out cleanly.

import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mdx from '@astrojs/mdx';
import mermaid from 'astro-mermaid';
import rehypeRelativeMarkdownLinks from 'astro-rehype-relative-markdown-links';
import starlightLinksValidator from 'starlight-links-validator';
import fs from 'node:fs';
import path from 'node:path';

const CONTENT_ROOT = './src/content/docs';

/**
 * Read the `sidebar.label` (preferred) or `title` from a markdown file's frontmatter.
 * Returns null if neither is found — the caller decides the fallback.
 */
function readSidebarTitle(file) {
  if (!fs.existsSync(file)) return null;
  const content = fs.readFileSync(file, 'utf8');
  const m = content.match(/^---\n([\s\S]*?)\n---/);
  if (!m) return null;
  const fm = m[1];
  const labelMatch = fm.match(/^\s+label:\s*['"]?(.+?)['"]?\s*$/m);
  if (labelMatch) return labelMatch[1];
  const titleMatch = fm.match(/^title:\s*['"]?(.+?)['"]?\s*$/m);
  return titleMatch ? titleMatch[1] : null;
}

/**
 * Build sidebar items for a directory by walking its immediate children:
 *   - a loose `.md`/`.mdx` file becomes a single link;
 *   - a subdirectory with only `index.{md,mdx}` becomes a single link;
 *   - a subdirectory with index + siblings becomes an expandable group led by an Overview.
 * Returns [] when the directory does not exist yet (so a not-yet-migrated section drops out).
 */
function buildSection(dir) {
  const fullDir = path.join(CONTENT_ROOT, dir);
  if (!fs.existsSync(fullDir)) return [];
  const entries = fs.readdirSync(fullDir).sort();
  const sectionIndex = entries.find((e) => e === 'index.md' || e === 'index.mdx');
  const items = [];
  if (sectionIndex) {
    items.push({ slug: dir, label: 'Overview' });
  }
  for (const entry of entries) {
    if (entry === sectionIndex) continue;
    const full = path.join(fullDir, entry);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      const indexMdx = path.join(full, 'index.mdx');
      const indexMd = path.join(full, 'index.md');
      const indexFile = fs.existsSync(indexMdx) ? indexMdx : (fs.existsSync(indexMd) ? indexMd : null);
      if (!indexFile) continue;
      const siblings = fs.readdirSync(full).filter((f) => {
        if (f === 'index.md' || f === 'index.mdx') return false;
        return f.endsWith('.md') || f.endsWith('.mdx');
      });
      if (siblings.length === 0) {
        items.push({ slug: `${dir}/${entry}` });
        continue;
      }
      const title = readSidebarTitle(indexFile) ?? entry;
      items.push({
        label: title,
        collapsed: true,
        items: [
          { slug: `${dir}/${entry}`, label: 'Overview' },
          ...siblings.sort().map((f) => ({ slug: `${dir}/${entry}/${f.replace(/\.mdx?$/, '')}` })),
        ],
      });
    } else if (/\.(mdx?|markdown)$/i.test(entry)) {
      items.push({ slug: `${dir}/${entry.replace(/\.mdx?$/, '')}` });
    }
  }
  return items;
}

/** A top-level group, included only when the section actually has content. */
function section(label, dir) {
  const items = buildSection(dir);
  return items.length ? { label, items } : null;
}

/** Loose `.md`/`.mdx` pages at the content root (excluding the home index), as direct links. */
function rootPages() {
  if (!fs.existsSync(CONTENT_ROOT)) return [];
  return fs.readdirSync(CONTENT_ROOT)
    .filter((e) => /\.mdx?$/.test(e) && e !== 'index.md' && e !== 'index.mdx')
    .sort()
    .map((e) => ({ slug: e.replace(/\.mdx?$/, '') }));
}

const sidebar = [
  ...rootPages(),
  section('Architecture', 'architecture'),
  section('Development', 'development'),
  section('Operations', 'operations'),
].filter(Boolean);

export default defineConfig({
  site: 'https://docs.dev88.work/obol',
  base: '/obol',
  integrations: [
    mermaid({ theme: 'dark', autoTheme: true }),
    starlight({
      title: 'Obol',
      plugins: [starlightLinksValidator()],
      logo: { src: './src/assets/obol-coin.svg', replacesTitle: false },
      favicon: '/obol-coin.svg',
      sidebar,
      expressiveCode: {
        shiki: {
          langAlias: {
            env: 'dotenv',
          },
        },
      },
    }),
    mdx(),
  ],
  // Rewrite relative `.md`/`.mdx` cross-links to their final routes. The `docs`
  // collection is served at the site root (Starlight strips `docs`), so
  // `collectionBase: false`; `base`/`trailingSlash` mirror the site config above.
  markdown: {
    rehypePlugins: [
      [rehypeRelativeMarkdownLinks, { base: '/obol', collectionBase: false, trailingSlash: 'always' }],
    ],
  },
});
