# Contributing to GitHub Deployer for WordPress

Thank you for considering contributing to GitHub Deployer for WordPress! This document provides guidelines and instructions for contributing to this project.

## How to Contribute

### Reporting Bugs

If you find a bug, please submit an issue with the following information:

1. A clear, descriptive title
2. Steps to reproduce the issue
3. Expected behavior
4. Actual behavior
5. WordPress version
6. PHP version
7. Any relevant screenshots

### Suggesting Features

Feature suggestions are welcome! Please submit an issue with:

1. A clear, descriptive title
2. A detailed description of the proposed feature
3. Any relevant examples, mockups, or use cases

### Pull Requests

1. Fork the repository
2. Create a new branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Commit your changes (`git commit -m 'Add some amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## Development Setup

1. Clone your fork of the repository
2. Set up a local WordPress development environment
3. Place the plugin in the `/wp-content/plugins/` directory
4. Activate the plugin through the WordPress admin

## Coding Standards

This project follows the [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/):

- PHP code should follow the [WordPress PHP Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/)
- JavaScript code should follow the [WordPress JavaScript Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/javascript/)
- CSS should follow the [WordPress CSS Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/css/)

## File Structure

```
github-deployer/
├── github-deployer.php         # Main plugin file
├── includes/                   # PHP classes
├── assets/                     # CSS and JavaScript
├── templates/                  # Template files
└── languages/                  # Translation files
```

## Testing

Before submitting a pull request, please test your changes thoroughly:

1. Test with the latest version of WordPress
2. Test with both public and private GitHub repositories
3. Test deploying both plugins and themes
4. Test with various branches, tags, and commits

## Documentation

If your changes require documentation updates, please include them in your pull request:

1. Update the README.md file if necessary
2. Update the readme.txt file for WordPress.org compatibility
3. Add inline documentation to any new code

## License

By contributing to this project, you agree that your contributions will be licensed under the project's GPL-2.0+ license.

## Questions?

If you have any questions about contributing, please feel free to reach out by opening an issue. 