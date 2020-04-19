# Dynamic ACF Blocks
Adds ability to create ACF Blocks via WordPress admin panel.

## Requirements

- ACF Pro
- ACF Code Field
- Timber

## Usage

1. Install fresh WordPress and required plugins.
2. Visit "ACF Blocks > Block Groups" and create a dynamic block category like "my-acf-blocks / My ACF Blocks / dashboard"
3. Visit "ACF Blocks > ACF Blocks" and create a dynamic block like "my_custom_acf_block / My Custom ACF Block / ... / my"
4. Visit "ACF Blocks > My Custom ACF Block" and add some PHP, HTML, CSS and JS. Codes will be created under uploads/dynamic_acf_blocks folder. HTML codes will be called directly from DB but other files will be fetched from the file system.
5. Add a new post or page and search your block in components with your block title or keywords.
6. If you want to add some custom fields through ACF Fields UI, you can create a field group and send that fields to "Block == My Custom ACF Block" because it was created dynamically and that fields available in the PHP and HTML (Twig) settings.

.. then just experience it.

It is just a start. I want to reformat the code and add some other useful features like Import/Export, emmet (zen coding) support in code mirror, etc.

Feel free to ask via issues and contribute via prs.