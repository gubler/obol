// ABOUTME: Astro + Starlight config for the Obol end-user docs site (docs.dev88.work/obol-user).
// ABOUTME: Standalone from the dev docs; flat sidebar built from the content root so it populates as pages land.

import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mdx from '@astrojs/mdx';
import mermaid from 'astro-mermaid';
import rehypeRelativeMarkdownLinks from 'astro-rehype-relative-markdown-links';
import starlightLinksValidator from 'starlight-links-validator';
import fs from 'node:fs';

const CONTENT_ROOT = './src/content/docs';

/**
 * Loose `.md`/`.mdx` pages at the content root (excluding the home index), as direct sidebar links.
 * The user guide is flat - one page per top-level flow - so a `sidebar.order` frontmatter field
 * controls reading order; absent that, pages sort by slug. Returns [] before any page exists.
 */
function rootPages() {
  if (!fs.existsSync(CONTENT_ROOT)) return [];
  return fs.readdirSync(CONTENT_ROOT)
    .filter((e) => /\.mdx?$/.test(e) && e !== 'index.md' && e !== 'index.mdx')
    .sort()
    .map((e) => ({ slug: e.replace(/\.mdx?$/, '') }));
}

export default defineConfig({
  site: 'https://docs.dev88.work/obol-user',
  base: '/obol-user',
  integrations: [
    mermaid({ theme: 'dark', autoTheme: true }),
    starlight({
      title: 'Obol User Guide',
      plugins: [starlightLinksValidator()],
      logo: { src: './src/assets/obol-coin.svg', replacesTitle: false },
      favicon: '/obol-coin.svg',
      sidebar: rootPages(),
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
      [rehypeRelativeMarkdownLinks, { base: '/obol-user', collectionBase: false, trailingSlash: 'always' }],
    ],
  },
});
