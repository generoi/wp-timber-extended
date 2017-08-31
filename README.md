# wp-timber-extended

> A wordpress plugin extending [Timber](https://github.com/timber/timber/) with various features such as automatic template loading.

## Features

- Woocommerce support for partial templates
- Tailor support for partial templates
- Widgets rendered through twig templates
- Inherit password protection from parent pages.
- BEM classes for Timber Menus.
- Language Menu for WPML and PLL.
- A debug bar panel for inspecting template suggestions.
- Basic additions to all timber contexts (theme mods, site icon, etc).

## Theme features

You need to activate the features in your theme using `add_theme_support`

### Automatic Timber templating system

> Replace the core PHP templating system with Timber and provide additional template suggestions.

To use this feature, clarify that your theme supports it with
`add_theme_support` and then create an `index.twig` file in the `TEMPLATEPATH` location, or a location specifically added to `Timber::$dirname` in your theme.

```php
add_theme_support('timber-extended-templates', [
  // Use double dashes as the template variation separator.
  'bem_templates',
  // Use timber to render widgets.
  'widget',
  // Use timber to WooCommerce templates.
  'woocommerce',
  // Use timber to Tailor templates.
  'tailor',
]);
```

Regular PHP files will still be supported but twig versions will take precedence if available. You can inspect the template suggestions for each page if you're using the _Debug_ plugin. If the page is a [`tailored`](https://github.com/andrew-worsfold/tailor) page, there will also exist a `<type>-tailor.twig` suggestion.

Depending on the type of page is being rendered, various global context variables will be available. Eg. category templates will have both `term` and `posts` available, while single posts have only a `post` object. In addition to these there's also `template_file` and `template_type` available.

### Password inheritance from parent posts

If a post parent is password protected, so are it's children.

```php
add_theme_support('timber-extended-password-inheritance');
```

### Additional twig extensions

Add additional twig functions and filters.

```php
add_theme_support('timber-extended-twig-extensions', [
  // Add some core functions and filters to twig.
  'core',
  // Add some contrib functions and filters to twig.
  'contrib',
  // Add some functional programming helpers to twig.
  'functional'
]);
```

## Bundled Timber classes

- `TimberExtended\Post`: Extended Timber\Post
- `TimberExtended\Term`: Extended Timber\Term
- `TimberExtended\Image`: Extended Timber\Image
- `TimberExtended\User`: Extended Timber\User
- `TimberExtended\Widget`: Widget object for ACFW or regular core widgets.
- `TimberExtended\Menu`: Extended Timber\Menu adding BEM classes to menu items.
- `TimberExtended\MenuItem`: Extended Timber\MenuItem adding BEM classes to menu item as well as their links.
- `TimberExtended\LanugageMenu`: TimberExtended\Menu-like dummy class which contains the site languages as it's items (WPML/PLL support).

## Filters API

```php
// Modify the template suggestions
add_filter('timber_extended/templates/suggestions', function ($templates) {
  return $templates;
});

// Disable Twig suggestions.
add_filter('timber_extended/templates/twig', '__return_false');

// Set custom timber subclasses.
add_filter('timber_extended/class', function ($class_name, $type, $object = null) {
  switch ($type) {
    case 'post': return __NAMESPACE__ . '\\Controller\\Post';
    case 'term': return __NAMESPACE__ . '\\Controller\\Term';
    case 'user': return __NAMESPACE__ . '\\Controller\\User';
    case 'image': return __NAMESPACE__ . '\\Controller\\Image';
    case 'widget': return __NAMESPACE__ . '\\Controller\\Widget';
  }
  return $class_name;
}, 10, 3);

// Set custom Timber subclasses.
add_filter('timber_extended/{user,widget,image,post,term,menu,menuitem}/class', function ($class_name, $object = null) {
  return __NAMESPACE__ . '\\User';
});
```
