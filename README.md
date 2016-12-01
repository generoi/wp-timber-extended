# wp-timber-extended

### Timber additions

- `TimberExtended\Widget` a timber widget object for accessing ACF Widget content.
- `TimberExtended\Menu` an extended Timber\Menu which adds BEM classes to menu items.
- `TimberExtended\MenuItem` an extended Timber\MenuItem which adds BEM classes to menu item as well as their links.
- `TimberExtended\LanugageMenu` a TimberExtended\Menu-like dummy class which contains the WPML language as it's items.

### Other

- A debug bar panel for inspecting `timber-extended-templates` suggestions.

#### Theme modules you can activate.

##### `timber-extended-templates`

```php
add_theme_support('timber-extended-templates', [
  // Use archive as the template for all category-like pages.
  'normalize_archive_templates',
  // Use double dashes as the template variation separator.
  'bem_templates',
  // Attach all terms of the active category on an archive page.
  'context_add_terms',
]);
```

##### `timber-extended-password-inheritance`

```php
// If a post parent is password protected, so are it's children.
add_theme_support('timber-extended-password-inheritance');
```

##### `timber-extended-twig-extensions`

```php
// Add additional twig functions and filters.
add_theme_support('timber-extended-twig-extensions', [
  // Add some core functions and filters to twig.
  'core',
  // Add some contrib functions and filters to twig.
  'contrib',
  // Add some functional programming helpers to twig.
  'functional'
]);
```
