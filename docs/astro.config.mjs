// ABOUTME: Astro + Starlight config for the Obol developer docs site (docs.dev88.work/obol).
// ABOUTME: Sidebar is curated (logical reading order, not alphabetical) rather than auto-generated.

import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import mdx from '@astrojs/mdx';
import mermaid from 'astro-mermaid';
import rehypeRelativeMarkdownLinks from 'astro-rehype-relative-markdown-links';
import starlightLinksValidator from 'starlight-links-validator';

// Curated reading order (Getting Started first, then logical groupings) rather than
// alphabetical. Labels default to each page's frontmatter title; section index pages
// are relabelled "Overview" so they don't echo the group name. The home page (index)
// is reached via the logo/title, so it is not a sidebar entry.
const sidebar = [
  { slug: 'getting-started' },
  {
    label: 'Architecture',
    items: [
      { slug: 'architecture', label: 'Overview' },
      { slug: 'architecture/domain-model' },
      { slug: 'architecture/cqrs' },
      { slug: 'architecture/controllers' },
      { slug: 'architecture/forms-and-dtos' },
    ],
  },
  {
    label: 'Frontend',
    items: [
      { slug: 'frontend', label: 'Overview' },
      { slug: 'palette' },
    ],
  },
  { slug: 'deployment' },
  { slug: 'ci-cd' },
  {
    label: 'Development',
    items: [
      { slug: 'development', label: 'Overview' },
      { slug: 'development/standards' },
      { slug: 'development/testing' },
      { slug: 'development/git-hooks' },
      { slug: 'development/mise-tasks' },
    ],
  },
  {
    label: 'Operations',
    items: [{ slug: 'operations/updates' }],
  },
];

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
