// ABOUTME: Ambient stubs for JS modules supplied at runtime by AssetMapper's importmap
// ABOUTME: (a Symfony bundle asset / vendored package), so tsc --checkJs can resolve the imports.

// These specifiers resolve at runtime via importmap.php, not via npm, so they are not in
// node_modules and tsc cannot find their types. Declared untyped here purely so the imports
// type-check. @symfony/stimulus-bundle is a bundle asset with no npm package; @tailwindplus/elements
// is a vendored importmap package. Ratchet later by adding a typed devDependency (pinned to the
// importmap version) for any specifier whose own types we want genuine coverage of.
declare module '@symfony/stimulus-bundle';
declare module '@tailwindplus/elements';
