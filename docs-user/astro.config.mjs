// ABOUTME: Astro + Starlight config for the Obol end-user docs site (docs.dev88.work/obol-user).
// ABOUTME: Standalone from the dev docs; flat, curated sidebar in reading order (the home is reached via the logo).

import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mdx from '@astrojs/mdx';
import mermaid from 'astro-mermaid';
import rehypeRelativeMarkdownLinks from 'astro-rehype-relative-markdown-links';
import starlightLinksValidator from 'starlight-links-validator';

// Curated reading order rather than alphabetical; labels come from each page's frontmatter title.
const sidebar = [
  { slug: 'getting-started' },
  { slug: 'account' },
  { slug: 'emails' },
  { slug: 'passkeys' },
  { slug: 'subscriptions' },
  { slug: 'categories' },
  { slug: 'payment-sources' },
  { slug: 'payments' },
  { slug: 'reports' },
  { slug: 'savings' },
  { slug: 'currencies' },
];

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
      customCss: ['./src/styles/custom.css'],
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
      [rehypeRelativeMarkdownLinks, { base: '/obol-user', collectionBase: false, trailingSlash: 'always' }],
    ],
  },
});
