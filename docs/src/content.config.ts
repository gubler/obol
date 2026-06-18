// ABOUTME: Defines the Starlight `docs` content collection and its Zod schema.
// ABOUTME: Uses Starlight's stock docsSchema - frontmatter (title/description/sidebar) only, no custom fields.

import { defineCollection } from 'astro:content';
import { docsLoader } from '@astrojs/starlight/loaders';
import { docsSchema } from '@astrojs/starlight/schema';

export const collections = {
  docs: defineCollection({ loader: docsLoader(), schema: docsSchema() }),
};
